<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduler
|--------------------------------------------------------------------------
| Production cron (one line, every minute):
|     * * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1
|
| Scheduled commands only DISPATCH jobs; the queue worker does the sending / polling.
*/

// Detect replies and bounces to every sent email (Leads compose + sequences) over IMAP.
// Queues one poll per account; each poll reads only mail that arrived since the last one.
Schedule::command('emails:check-replies')->everyTwoMinutes()->withoutOverlapping(10);

// Sequences: queue a send job for every enrollment whose next step is due.
Schedule::command('sequencer:dispatch-due')->everyMinute()->withoutOverlapping(5);

// Housekeeping: old send counters and inbound-message records.
Schedule::command('sequencer:prune')->dailyAt('03:30')->withoutOverlapping();
