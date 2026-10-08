<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExportsCsv;
use App\Http\Controllers\Concerns\FiltersRequests;
use App\Http\Controllers\Concerns\RedirectsBack;
use App\Jobs\ProcessBulkJob;
use App\Models\Bulk;
use App\Models\BulkItem;
use App\Models\EmailVerification;
use App\Models\Lead;
use App\Services\EmailVerifierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Verifier: check whether an email address can actually receive mail.
 *
 *   verify()      one address, answered inline as JSON
 *   verifyMany()  a pasted list. Short lists run in the request; anything
 *                 longer is handed to the Bulks module so the user is not left
 *                 waiting on a page that may time out.
 *
 * Every check is recorded in email_verifications, which is what the history
 * table below the form reads.
 */
class VerifierController extends Controller
{
    use ExportsCsv, FiltersRequests, RedirectsBack;

    /**
     * Addresses accepted from the paste box. Above this the run is queued
     * instead - each address can cost a DNS round trip (and an SMTP one).
     */
    private const INLINE_LIMIT = 10;

    private const PASTE_LIMIT = 500;

    public function __construct(private readonly EmailVerifierService $verifier) {}

    /** Verification form plus the filterable history table. */
    public function index(Request $request): View
    {
        $term   = $this->strParam($request, 'q');
        $status = $this->enumParam($request, 'status', array_keys(EmailVerification::STATUSES));
        $source = $this->enumParam($request, 'source', ['single', 'bulk', 'finder']);

        $query = EmailVerification::with('lead')->orderByDesc('id');

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($source !== '') {
            $query->where('source', $source);
        }

        if ($term !== '') {
            $escaped = $this->likePattern($term);
            $query->where(fn ($q) => $q->where('email', 'like', $escaped)->orWhere('domain', 'like', $escaped));
        }

        $data = [
            'history'      => $query->paginate($this->perPage($request))->withQueryString(),
            'statusCounts' => $this->statusCounts(),
            'smtpProbe'    => (bool) config('services.email_verifier.smtp', false),
            'filters'      => ['q' => $term, 'status' => $status, 'source' => $source],
        ];

        if ($request->ajax()) {
            return view('verifier._history', $data);
        }

        return view('verifier.index', $data);
    }

    /** Verify a single address and record the result. */
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Deliberately not `email`: a malformed address is a legitimate
            // thing to ask about, and the verifier answers "invalid" for it.
            'email' => ['required', 'string', 'max:254'],
        ]);

        $email = strtolower(trim($validated['email']));

        try {
            $result = $this->verifier->verify($email);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'ok'      => false,
                'message' => 'Verification could not be completed. Please try again.',
            ], 500);
        }

        $record = $this->record($result, 'single');

        return response()->json([
            'ok'     => true,
            'result' => $this->present($result, $record->id),
        ]);
    }

    /**
     * Verify a pasted list. Small lists answer inline; larger ones become a
     * queued bulk run and the user is redirected to its progress page.
     */
    public function verifyMany(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'emails' => ['required', 'string', 'max:20000'],
        ]);

        $emails = $this->verifier->parseList($validated['emails']);

        if ($emails === []) {
            return response()->json(['ok' => false, 'message' => 'No email addresses found in that list.'], 422);
        }

        if (count($emails) > self::PASTE_LIMIT) {
            return response()->json([
                'ok'      => false,
                'message' => 'That is more than '.self::PASTE_LIMIT.' addresses - please upload it as a CSV from the Bulks module instead.',
            ], 422);
        }

        // Long list: hand it to the queue and let the Bulks UI report progress.
        if (count($emails) > self::INLINE_LIMIT) {
            $bulk = $this->queueBulk($emails);

            return response()->json([
                'ok'       => true,
                'queued'   => true,
                'bulk_id'  => $bulk->id,
                'redirect' => route('bulks.show', $bulk->id),
                'message'  => count($emails).' addresses queued for verification.',
            ]);
        }

        $results = [];

        foreach ($emails as $email) {
            try {
                $result   = $this->verifier->verify($email);
                $record   = $this->record($result, 'single');
                $results[] = $this->present($result, $record->id);
            } catch (\Throwable $e) {
                report($e);
                $results[] = [
                    'email'  => $email,
                    'status' => 'unknown',
                    'score'  => 0,
                    'reason' => 'Verification failed for this address.',
                    'checks' => [],
                ];
            }
        }

        return response()->json(['ok' => true, 'queued' => false, 'results' => $results]);
    }

    /** Remove one history entry. */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        EmailVerification::findOrFail($id)->delete();

        return redirect()
            ->route('verifier.index', $this->redirectQuery($request))
            ->with('success', 'Verification record deleted.');
    }

    /** Clear the whole history (respecting the active status filter). */
    public function clear(Request $request): RedirectResponse
    {
        $status = $this->enumParam($request, 'status', array_keys(EmailVerification::STATUSES));
        $query  = EmailVerification::query();

        if ($status !== '') {
            $query->where('status', $status);
        }

        $deleted = $query->delete();

        return redirect()
            ->route('verifier.index')
            ->with('success', "{$deleted} verification record(s) cleared.");
    }

    /** Export the history, honouring the current filters. */
    public function export(Request $request): StreamedResponse
    {
        $query  = EmailVerification::query()->orderByDesc('id');
        $status = $this->enumParam($request, 'status', array_keys(EmailVerification::STATUSES));
        $source = $this->enumParam($request, 'source', ['single', 'bulk', 'finder']);
        $term   = $this->strParam($request, 'q');

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($source !== '') {
            $query->where('source', $source);
        }

        if ($term !== '') {
            $escaped = $this->likePattern($term);
            $query->where(fn ($q) => $q->where('email', 'like', $escaped)->orWhere('domain', 'like', $escaped));
        }

        return $this->streamCsv(
            'email-verifications',
            ['ID', 'Email', 'Domain', 'Status', 'Score', 'Reason', 'MX', 'Disposable', 'Role', 'Free', 'Source', 'Checked At'],
            $query->cursor(),
            fn (EmailVerification $row) => [
                $row->id,
                $row->email,
                $row->domain,
                $row->status,
                $row->score,
                $row->reason,
                ! empty($row->checks['mx']) ? 'yes' : 'no',
                ! empty($row->checks['disposable']) ? 'yes' : 'no',
                ! empty($row->checks['role']) ? 'yes' : 'no',
                ! empty($row->checks['free']) ? 'yes' : 'no',
                $row->source,
                $row->created_at?->toDateTimeString(),
            ],
        );
    }

    /**
     * Turn a pasted list into a queued bulk run, reusing the Bulks pipeline so
     * there is one code path for progress, results and export.
     *
     * @param  string[]  $emails
     */
    private function queueBulk(array $emails): Bulk
    {
        $bulk = Bulk::create([
            'name'          => 'Pasted list - '.now()->format('j M Y, H:i'),
            'type'          => Bulk::TYPE_VERIFY,
            'status'        => Bulk::STATUS_PENDING,
            'total_records' => count($emails),
        ]);

        $now = now();

        // chunk() keeps a 500-address paste to a couple of inserts.
        foreach (array_chunk($emails, 200) as $chunk) {
            BulkItem::insert(array_map(static fn (string $email) => [
                'bulk_id'    => $bulk->id,
                'input'      => $email,
                'status'     => BulkItem::STATUS_PENDING,
                'created_at' => $now,
                'updated_at' => $now,
            ], $chunk));
        }

        ProcessBulkJob::dispatch($bulk->id)->onQueue('default');

        return $bulk;
    }

    /** Persist one verification result, linking it to its lead when there is one. */
    private function record(array $result, string $source): EmailVerification
    {
        return EmailVerification::create([
            'email'   => $result['email'],
            'domain'  => $result['domain'],
            'status'  => $result['status'],
            'score'   => $result['score'],
            'reason'  => $result['reason'],
            'checks'  => $result['checks'],
            'source'  => $source,
            'lead_id' => Lead::where('email', $result['email'])->value('id'),
        ]);
    }

    /** Shape one result for the front end, including the per-check breakdown. */
    private function present(array $result, int $recordId): array
    {
        $checks = $result['checks'];

        return [
            'id'     => $recordId,
            'email'  => $result['email'],
            'domain' => $result['domain'],
            'status' => $result['status'],
            'score'  => $result['score'],
            'reason' => $result['reason'],
            'colour' => EmailVerification::colourFor($result['status']),
            // Ordered for display; null means "not checked".
            'checks' => [
                ['label' => 'Syntax',            'pass' => $checks['syntax'],  'detail' => $checks['syntax'] ? 'Well-formed address' : 'Malformed'],
                ['label' => 'Domain resolves',   'pass' => $checks['domain'],  'detail' => $checks['domain'] ? 'DNS record found' : 'No DNS record'],
                ['label' => 'MX records',        'pass' => $checks['mx'],      'detail' => $checks['mx'] ? implode(', ', array_slice($checks['mx_hosts'], 0, 2)) : 'None published'],
                ['label' => 'Mailbox (SMTP)',    'pass' => $checks['smtp'],    'detail' => $checks['smtp'] === null ? 'Not probed' : ($checks['smtp'] ? 'Accepted' : 'Rejected')],
                ['label' => 'Not catch-all',     'pass' => $checks['catch_all'] === null ? null : ! $checks['catch_all'], 'detail' => $checks['catch_all'] === null ? 'Not probed' : ($checks['catch_all'] ? 'Domain accepts everything' : 'Specific mailboxes only')],
                ['label' => 'Not disposable',    'pass' => ! $checks['disposable'], 'detail' => $checks['disposable'] ? 'Throwaway provider' : 'Permanent provider'],
                ['label' => 'Not role-based',    'pass' => ! $checks['role'],  'detail' => $checks['role'] ? 'Shared mailbox' : 'Individual mailbox'],
                ['label' => 'Business domain',   'pass' => ! $checks['free'],  'detail' => $checks['free'] ? 'Free consumer provider' : 'Company domain'],
            ],
        ];
    }

    /** @return array<string,int> counts keyed by status, zero-filled */
    private function statusCounts(): array
    {
        $counts = EmailVerification::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $out = ['total' => (int) $counts->sum()];

        foreach (array_keys(EmailVerification::STATUSES) as $status) {
            $out[$status] = (int) ($counts[$status] ?? 0);
        }

        return $out;
    }
}
