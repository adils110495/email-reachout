<?php

namespace App\Jobs;

use App\Http\Controllers\Concerns\SavesFinderResults;
use App\Models\AppNotification;
use App\Services\EmailFinderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs one Finder lookup (domain scrape or person-pattern search) off-request,
 * files the result exactly as the page used to, and raises a notification.
 */
class FinderSearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use SavesFinderResults;

    /** A retry would scrape the site a second time for nothing, so run once. */
    public int $tries = 1;

    /** Kept under the worker's --timeout=90 so the worker doesn't kill it mid-run. */
    public int $timeout = 85;

    public function __construct(
        public readonly string $mode,
        public readonly string $domain,
        public readonly string $name = '',
        public readonly ?int $categoryId = null,
    ) {}

    public function handle(EmailFinderService $finder): void
    {
        $result = $this->mode === 'person'
            ? $finder->findByPerson($this->name, $this->domain)
            : $finder->findByDomain($this->domain);

        // A domain search found a real, published address - file it against the
        // company's lead straight away. Only the best NON-guessed candidate:
        // a person search produces patterns, not confirmed addresses, and a
        // lead is keyed on its website so one address per company is enough.
        $saved = null;
        $best  = $result['candidates'][0] ?? null;

        if ($best && ! $best['guessed']) {
            $saved = $this->saveLead(
                $result['domain'],
                $best['email'],
                $result['company'] ?? null,
                $this->categoryId,
            );
        }

        // Record every candidate, not just the one that became a lead.
        $this->recordResults($result, $this->mode, $saved);

        $count = count($result['candidates']);

        if ($count === 0 && $this->mode === 'domain' && ! ($result['scraped'] ?? true)) {
            $message = "{$result['domain']}: the website could not be reached.";
        } elseif ($count === 0) {
            $message = "{$result['domain']}: no addresses found.";
        } else {
            $message = "{$result['domain']}: {$count} address" . ($count === 1 ? '' : 'es') . ' found'
                . ($saved ? ' and saved to your leads.' : '.');
        }

        AppNotification::raise('success', 'Finder search complete', $message, route('finder.index', [], false));

        Log::info('FinderSearchJob: complete', ['domain' => $result['domain'], 'mode' => $this->mode, 'found' => $count]);
    }

    public function failed(Throwable $e): void
    {
        Log::error('FinderSearchJob: failed', ['domain' => $this->domain, 'error' => $e->getMessage()]);

        AppNotification::raise('error', 'Finder search failed', "{$this->domain}: {$e->getMessage()}");
    }
}
