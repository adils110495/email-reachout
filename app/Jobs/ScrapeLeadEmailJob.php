<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Services\EmailExtractorService;
use App\Services\ScraperService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ScrapeLeadEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 60;

    public function __construct(public readonly Lead $lead) {}

    public function handle(ScraperService $scraper, EmailExtractorService $extractor): void
    {
        // Skip if email already found by a previous attempt
        if (! empty($this->lead->email)) {
            return;
        }

        $html   = $scraper->fetch($this->lead->website);
        $emails = $extractor->extract($html);

        if (! empty($emails)) {
            $this->lead->update(['email' => $emails[0]]);

            Log::info('ScrapeLeadEmailJob: email found', [
                'lead_id' => $this->lead->id,
                'email'   => $emails[0],
            ]);
        } else {
            Log::debug('ScrapeLeadEmailJob: no email found', [
                'lead_id' => $this->lead->id,
                'website' => $this->lead->website,
            ]);
        }
    }
}
