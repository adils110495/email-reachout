<?php

namespace App\Jobs;

use App\Models\AppNotification;
use App\Models\Lead;
use App\Models\Platform;
use App\Services\LeadFinderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs a "Find Leads" search in the background.
 *
 * The SerpAPI call plus up to LEAD_FETCH_LIMIT inserts used to happen inside the
 * request, so the user sat on a spinner until the provider answered. This job
 * does that work off-request and then queues one ScrapeLeadEmailJob per new lead
 * to fill in the email address.
 *
 * Country/language are passed in rather than read here: env() is unreliable
 * inside a queued worker once config is cached.
 */
class FindLeadsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    /** Kept under the worker's --timeout=90 so the worker doesn't kill it mid-run. */
    public int $timeout = 85;

    public function __construct(
        public readonly string $keyword,
        public readonly int $categoryId,
        public readonly ?string $country = null,
        public readonly ?string $language = null,
    ) {}

    public function handle(LeadFinderService $leadFinder): void
    {
        $results = $leadFinder->find(
            keyword:  $this->keyword,
            country:  $this->country,
            language: $this->language,
        );

        $googlePlatform = Platform::where('name', 'Google')->first();
        $newLeadsCount  = 0;

        foreach ($results as $result) {
            // Skip duplicates
            if (Lead::where('website', $result['url'])->exists()) {
                continue;
            }

            // Save the lead immediately (no scraping yet)
            $lead = Lead::create([
                'company_name' => $result['title'],
                'website'      => $result['url'],
                'email'        => null,
                'status'       => Lead::STATUS_NEW,
                'platform_id'  => $googlePlatform?->id,
                'category_id'  => $this->categoryId,
            ]);

            // Extract the email in its own job so one slow site cannot stall the rest
            ScrapeLeadEmailJob::dispatch($lead)->onQueue('default');

            $newLeadsCount++;
        }

        AppNotification::raise(
            'success',
            'Lead search complete',
            "\"{$this->keyword}\": {$newLeadsCount} new lead" . ($newLeadsCount === 1 ? '' : 's') . ' added'
                . ($newLeadsCount > 0 ? ' (emails are being extracted in the background).' : '.'),
            route('leads.index', ['category' => $this->categoryId], false),
        );

        Log::info('FindLeadsJob: search complete', [
            'keyword'     => $this->keyword,
            'category_id' => $this->categoryId,
            'found'       => count($results),
            'new_leads'   => $newLeadsCount,
        ]);
    }

    public function failed(Throwable $e): void
    {
        Log::error('FindLeadsJob: search failed', [
            'keyword'     => $this->keyword,
            'category_id' => $this->categoryId,
            'error'       => $e->getMessage(),
        ]);

        AppNotification::raise('error', 'Lead search failed', "\"{$this->keyword}\": {$e->getMessage()}");
    }
}
