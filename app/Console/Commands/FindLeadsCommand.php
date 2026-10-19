<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\User;
use App\Services\EmailExtractorService;
use App\Services\LeadFinderService;
use App\Services\ScraperService;
use App\Services\SerpApiConfigService;
use App\Services\UserAccessService;
use Illuminate\Console\Command;

class FindLeadsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Usage:  php artisan leads:find "web design agency"
     *         php artisan leads:find "plumbing company" --limit=20 --user=jane
     */
    protected $signature = 'leads:find
        {keyword : The keyword to search for (e.g. "web design agency London")}
        {--limit=10 : Maximum number of leads to find}
        {--user= : Username or id of the account the leads belong to (default: the first super admin)}';

    protected $description = 'Find new leads by keyword using Google/DuckDuckGo and extract contact emails';

    public function __construct(
        private readonly LeadFinderService $leadFinder,
        private readonly ScraperService $scraper,
        private readonly EmailExtractorService $emailExtractor,
    ) {
        parent::__construct();
    }

    public function handle(UserAccessService $access): int
    {
        // A console run has no signed-in user, so say whose workspace the leads
        // go into - duplicates are also checked within that workspace only.
        $owner = User::resolveForConsole($this->option('user'));

        if (! $owner) {
            $this->error('No such user: ' . ($this->option('user') ?? '(no super admin found)'));
            return self::FAILURE;
        }

        return $access->runAs($owner, fn () => $this->find());
    }

    private function find(): int
    {
        $keyword = $this->argument('keyword');
        $limit   = (int) $this->option('limit');

        $this->info("Searching for leads: \"{$keyword}\" (limit: {$limit})");
        $this->newLine();

        // 1. Find candidate websites
        $this->line('  <fg=yellow>→</> Searching for websites...');
        // Country / language: the owner's Settings > SerpAPI, else LEAD_COUNTRY / LEAD_LANGUAGE.
        $serp    = app(SerpApiConfigService::class);
        $results = $this->leadFinder->find($keyword, $serp->country(), $serp->language());

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

            // 2. Scrape website. Budgeted per lead so one unresponsive site
            //    cannot stall a run of 25 behind it.
            $html = $this->scraper->fetch($result['url'], budgetSeconds: 45.0);

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
