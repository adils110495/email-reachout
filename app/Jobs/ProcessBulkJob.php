<?php

namespace App\Jobs;

use App\Models\AppNotification;
use App\Models\Bulk;
use App\Models\BulkItem;
use App\Models\EmailVerification;
use App\Models\Lead;
use App\Models\Platform;
use App\Services\EmailFinderService;
use App\Services\EmailVerifierService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Works through one bulk operation a chunk at a time.
 *
 * A 5,000-row CSV cannot be processed inside a single job: the worker runs with
 * --timeout=90 and would kill it halfway, leaving the run stuck at "processing".
 * So each run claims up to CHUNK pending items, processes them, updates the
 * counters, and re-dispatches itself while work remains. That also makes the
 * run resumable - after a worker restart the next job simply picks up whatever
 * is still `pending`.
 */
class ProcessBulkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Items handled per run, sized so a chunk finishes well inside $timeout.
     *
     * A verify item costs a DNS lookup that is cached per domain, so twenty fit
     * comfortably. A find item fetches a whole website, so it is budgeted at
     * FIND_BUDGET seconds each - four of those is 60s worst case, leaving room
     * under the 85s timeout.
     */
    private const CHUNK_VERIFY = 20;

    private const CHUNK_FIND = 3;

    /**
     * Wall-clock budget for one find item, in seconds.
     *
     * Sized to leave room for EmailFinderService's link-following round after
     * the guessed paths come up empty - that is where an address published only
     * on a job posting or a team bio is actually found. 3 x 20s = 60s, inside
     * the 85s timeout.
     */
    private const FIND_BUDGET = 20.0;

    public int $tries = 2;

    /** Kept under the worker's --timeout=90 so the worker does not kill it mid-chunk. */
    public int $timeout = 85;

    public function __construct(public readonly int $bulkId) {}

    public function handle(EmailVerifierService $verifier, EmailFinderService $finder): void
    {
        $bulk = Bulk::find($this->bulkId);

        if (! $bulk || ! $bulk->isRunning()) {
            return; // Deleted, cancelled, or already finished.
        }

        if ($bulk->status === Bulk::STATUS_PENDING) {
            $bulk->update([
                'status'     => Bulk::STATUS_PROCESSING,
                'started_at' => $bulk->started_at ?? now(),
            ]);
        }

        $items = $bulk->items()
            ->where('status', BulkItem::STATUS_PENDING)
            ->orderBy('id')
            ->limit($bulk->type === Bulk::TYPE_FIND ? self::CHUNK_FIND : self::CHUNK_VERIFY)
            ->get();

        foreach ($items as $item) {
            // Claim the row first, so a duplicate job cannot process it twice.
            $item->update(['status' => BulkItem::STATUS_PROCESSING]);

            try {
                $bulk->type === Bulk::TYPE_FIND
                    ? $this->findFor($item, $bulk, $finder)
                    : $this->verifyFor($item, $bulk, $verifier);
            } catch (Throwable $e) {
                Log::warning('ProcessBulkJob: item failed', [
                    'bulk_id' => $bulk->id,
                    'item_id' => $item->id,
                    'input'   => $item->input,
                    'error'   => $e->getMessage(),
                ]);

                $item->update([
                    'status'        => BulkItem::STATUS_FAILED,
                    'result_status' => $bulk->type === Bulk::TYPE_FIND ? 'not_found' : 'unknown',
                    'message'       => 'Processing error: '.$e->getMessage(),
                ]);
            }
        }

        $this->syncCounters($bulk);

        $remaining = $bulk->items()->where('status', BulkItem::STATUS_PENDING)->exists();

        if ($remaining) {
            self::dispatch($bulk->id)->onQueue('default');

            return;
        }

        // Nothing left to claim. Anything still stuck at "processing" belongs to
        // a run that died mid-chunk; mark it failed rather than hang forever.
        $bulk->items()
            ->where('status', BulkItem::STATUS_PROCESSING)
            ->update([
                'status'        => BulkItem::STATUS_FAILED,
                'result_status' => 'unknown',
                'message'       => 'Interrupted before the result was recorded.',
            ]);

        $this->syncCounters($bulk);

        $bulk->update([
            'status'       => Bulk::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        AppNotification::raise(
            'success',
            'Bulk run complete',
            "\"{$bulk->name}\": {$bulk->successful_records} of {$bulk->total_records} succeeded, {$bulk->failed_records} failed.",
            route('bulks.show', $bulk->id, false),
        );

        Log::info('ProcessBulkJob: bulk complete', [
            'bulk_id'    => $bulk->id,
            'type'       => $bulk->type,
            'total'      => $bulk->total_records,
            'successful' => $bulk->successful_records,
            'failed'     => $bulk->failed_records,
        ]);
    }

    /** Verify one address and record it in the verification history. */
    private function verifyFor(BulkItem $item, Bulk $bulk, EmailVerifierService $verifier): void
    {
        $result = $verifier->verify($item->input);

        // Link back to the lead holding this address, when there is one, so the
        // history shows which lead a result belongs to.
        $leadId = Lead::holdingAddress($result['email'])->value('id');

        EmailVerification::create([
            'email'   => $result['email'],
            'domain'  => $result['domain'],
            'status'  => $result['status'],
            'score'   => $result['score'],
            'reason'  => $result['reason'],
            'checks'  => $result['checks'],
            'source'  => 'bulk',
            'lead_id' => $leadId,
            'bulk_id' => $bulk->id,
        ]);

        $item->update([
            'status'        => BulkItem::STATUS_DONE,
            'result_status' => $result['status'],
            'result_value'  => $result['email'],
            'score'         => $result['score'],
            'message'       => $result['reason'],
            'meta'          => $result['checks'],
        ]);
    }

    /**
     * Find the best address for one domain and file it as a lead, so a bulk
     * find run feeds straight into the existing Leads module.
     */
    private function findFor(BulkItem $item, Bulk $bulk, EmailFinderService $finder): void
    {
        $result = $finder->findByDomain($item->input, self::FIND_BUDGET);
        $best   = $result['candidates'][0] ?? null;

        if (! $best) {
            $item->update([
                'status'        => BulkItem::STATUS_DONE,
                'result_status' => 'not_found',
                'message'       => $result['scraped']
                    ? 'No email address published on this website.'
                    : 'Website could not be reached.',
                'meta'          => ['domain' => $result['domain'], 'scraped' => $result['scraped']],
            ]);

            return;
        }

        $lead = $this->storeLead($result, $best, $bulk, $item);

        EmailVerification::create([
            'email'   => $best['email'],
            'domain'  => $result['domain'],
            'status'  => $best['status'],
            'score'   => $best['score'],
            'reason'  => $best['reason'],
            'checks'  => $best['checks'],
            'source'  => 'bulk',
            'lead_id' => $lead?->id,
            'bulk_id' => $bulk->id,
        ]);

        $item->update([
            'status'        => BulkItem::STATUS_DONE,
            'result_status' => 'found',
            'result_value'  => $best['email'],
            'score'         => $best['score'],
            'message'       => sprintf(
                '%s (%d other address%s found)',
                ucfirst($best['status']),
                max(0, count($result['candidates']) - 1),
                count($result['candidates']) === 2 ? '' : 'es',
            ),
            'meta' => [
                'domain'     => $result['domain'],
                'company'    => $result['company'],
                'lead_id'    => $lead?->id,
                'candidates' => array_column($result['candidates'], 'email'),
            ],
        ]);
    }

    /**
     * Create the lead, or fill in the email on one that already exists for this
     * website. Never overwrites an address a user already has.
     */
    private function storeLead(array $result, array $best, Bulk $bulk, BulkItem $item): ?Lead
    {
        $website = rtrim($result['url'], '/');
        $company = $item->extra ?: ($result['company'] ?: $result['domain']);

        $lead = Lead::where('website', $website)
            ->orWhere('website', $website.'/')
            ->first();

        if ($lead) {
            if (empty($lead->email)) {
                $lead->update(['email' => $best['email']]);
            }

            return $lead;
        }

        return Lead::create([
            'company_name' => $company,
            'website'      => $website,
            'email'        => $best['email'],
            'status'       => Lead::STATUS_NEW,
            'platform_id'  => Platform::where('name', 'Google')->value('id'),
            'category_id'  => $bulk->category_id,
        ]);
    }

    /**
     * Recompute the run's counters from the item rows.
     *
     * Derived rather than incremented so a retried chunk can never double-count.
     *   successful = the operation produced a usable answer
     *                (verify: valid or risky - both are real mailboxes;
     *                 find: an address was discovered)
     *   failed     = everything else, including per-item errors.
     */
    private function syncCounters(Bulk $bulk): void
    {
        $processed = $bulk->items()
            ->whereIn('status', [BulkItem::STATUS_DONE, BulkItem::STATUS_FAILED])
            ->count();

        $successful = $bulk->items()
            ->where('status', BulkItem::STATUS_DONE)
            ->whereIn('result_status', ['valid', 'risky', 'found'])
            ->count();

        $bulk->update([
            'processed_records'  => $processed,
            'successful_records' => $successful,
            'failed_records'     => $processed - $successful,
        ]);

        $bulk->refresh();
    }

    public function failed(Throwable $e): void
    {
        Log::error('ProcessBulkJob: run failed', [
            'bulk_id' => $this->bulkId,
            'error'   => $e->getMessage(),
        ]);

        Bulk::where('id', $this->bulkId)->update([
            'status'       => Bulk::STATUS_FAILED,
            'error'        => $e->getMessage(),
            'completed_at' => now(),
        ]);

        AppNotification::raise('error', 'Bulk run failed', $e->getMessage(), route('bulks.show', $this->bulkId, false));
    }
}
