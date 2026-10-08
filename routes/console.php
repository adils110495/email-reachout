<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Detect replies to sent emails (needs IMAP settings and the scheduler running).
Schedule::command('emails:check-replies')->everyTenMinutes()->withoutOverlapping();
