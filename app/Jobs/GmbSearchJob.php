<?php

namespace App\Jobs;

use App\Models\AppNotification;
use App\Models\GmbLead;
use App\Services\GmbFinderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs a GMB search (up to 3 SerpAPI pages) off-request, stores the businesses
 * that have no website, and raises a notification when it is done.
 */
class GmbSearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** A retry would spend SerpAPI credits again, so run once. */
    public int $tries = 1;

    /** Kept under the worker's --timeout=90 so the worker doesn't kill it mid-run. */
    public int $timeout = 85;

    public function __construct(
        public readonly string $keyword,
        public readonly int $categoryId,
    ) {}

    public function handle(GmbFinderService $finder): void
    {
        $result = $finder->find($this->keyword);

        $existing = GmbLead::whereIn('place_id', array_column($result['leads'], 'place_id'))->pluck('place_id')->all();
        $added    = 0;

        foreach ($result['leads'] as $lead) {
            if (in_array($lead['place_id'], $existing, true)) {
                continue;
            }

            // firstOrCreate, not create: a concurrent search must not crash on the unique place_id.
            $saved = GmbLead::firstOrCreate(
                ['place_id' => $lead['place_id']],
                $lead + ['keyword' => $this->keyword, 'category_id' => $this->categoryId],
            );

            if ($saved->wasRecentlyCreated) {
                $added++;
            }
        }

        AppNotification::raise(
            'success',
            'GMB search complete',
            "\"{$this->keyword}\": {$result['seen']} listings, {$result['with_website']} skipped (have a website), "
                . "{$added} new without a website added"
                . (count($existing) ? ', ' . count($existing) . ' already saved' : '') . '.',
            route('gmb-leads.index', ['category' => $this->categoryId], false),
        );

        Log::info('GmbSearchJob: complete', ['keyword' => $this->keyword, 'added' => $added]);
    }

    public function failed(Throwable $e): void
    {
        Log::error('GmbSearchJob: failed', ['keyword' => $this->keyword, 'error' => $e->getMessage()]);

        AppNotification::raise('error', 'GMB search failed', "\"{$this->keyword}\": {$e->getMessage()}");
    }
}
