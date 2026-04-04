<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Services\EmailExtractorService;
use App\Services\LeadFinderService;
use App\Services\ScraperService;
use Illuminate\Console\Command;

class FindLeadsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Usage:  php artisan leads:find "web design agency"
     *         php artisan leads:find "plumbing company" --limit=20
     */
    protected $signature = 'leads:find
        {keyword : The keyword to search for (e.g. "web design agency London")}
        {--limit=10 : Maximum number of leads to find}';

    protected $description = 'Find new leads by keyword using Google/DuckDuckGo and extract contact emails';

    public function __construct(
        private readonly LeadFinderService $leadFinder,
        private readonly ScraperService $scraper,
        private readonly EmailExtractorService $emailExtractor,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $keyword = $this->argument('keyword');
        $limit   = (int) $this->option('limit');

        $this->info("Searching for leads: \"{$keyword}\" (limit: {$limit})");
        $this->newLine();

        // 1. Find candidate websites
        $this->line('  <fg=yellow>→</> Searching for websites...');
        $results = $this->leadFinder->find($keyword, $limit);

        if (empty($results)) {
            $this->warn('No results found. Try a different keyword.');
            return self::FAILURE;
        }

        $this->line("  <fg=green>✓</> Found " . count($results) . " candidate websites.");
        $this->newLine();

        $newLeads = 0;
        $skipped  = 0;

        $progressBar = $this->output->createProgressBar(count($results));
        $progressBar->start();

        foreach ($results as $result) {
            // Skip duplicates
            if (Lead::where('website', $result['url'])->exists()) {
                $skipped++;
                $progressBar->advance();
                continue;
            }

            // 2. Scrape website
            $html = $this->scraper->fetch($result['url']);

            // 3. Extract emails
            $emails = $this->emailExtractor->extract($html);
            $email  = $emails[0] ?? null;

            // 4. Save lead
            Lead::create([
                'company_name' => $result['title'],
                'website'      => $result['url'],
                'email'        => $email,
                'status'       => Lead::STATUS_NEW,
            ]);

            $newLeads++;
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->table(
            ['Metric', 'Count'],
            [
                ['New leads saved', $newLeads],
                ['Duplicates skipped', $skipped],
                ['Total processed', count($results)],
            ]
        );

        $this->info('Done!');

        return self::SUCCESS;
    }
}
