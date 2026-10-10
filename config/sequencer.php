<?php

/*
|--------------------------------------------------------------------------
| Mail Sequencer
|--------------------------------------------------------------------------
|
| Everything tunable about the outreach engine lives here. Nothing in the
| sequencer code hardcodes a limit, URL or timezone - it all comes from this
| file (and through it, .env).
|
*/

return [

    // Sequence jobs run on the app's existing queues (QUEUE_CONNECTION=redis, worked by
    // the `queue` service): sends on "emails" next to the Leads sends, IMAP polling on
    // "default" next to Bulks / Finder. Rate limits, unique-job locks and overlap locks
    // use the default cache store, which must be shared by every worker (CACHE_STORE=redis).
    'queue' => [
        'send' => 'emails',
        'imap' => 'default',
    ],

    // Defaults offered when a user creates an account / sequence.
    'defaults' => [
        'daily_limit' => (int) env('SEQUENCER_DEFAULT_DAILY_LIMIT', 100),
        'rate_per_minute' => (int) env('SEQUENCER_DEFAULT_RATE_PER_MINUTE', 10),
        'sending_days' => [1, 2, 3, 4, 5],            // ISO: 1 = Monday ... 7 = Sunday
        'sending_start' => '09:00',
        'sending_end' => '17:00',
    ],

    // Scheduler -> queue hand-off.
    'dispatch' => [
        'batch_size' => 500,   // enrollments dispatched per scheduler run
        'lease_seconds' => 300,   // how long a dispatched enrollment is hidden from the scheduler
    ],

    'send' => [
        'tries' => 5,
        'backoff' => [30, 120, 600, 1800],   // seconds, exponential-ish
        'job_timeout' => 90,
        'smtp_timeout' => (int) env('SEQUENCER_SMTP_TIMEOUT', 30),
        // A log stuck in "sending" this long means a worker died mid-send. We cannot
        // know whether the SMTP server accepted it, so it is NOT re-sent (no duplicates).
        'stale_after_minutes' => 15,
    ],

    'imap' => [
        'lookback_days' => (int) env('SEQUENCER_IMAP_LOOKBACK_DAYS', 14),
        'max_per_run' => (int) env('SEQUENCER_IMAP_MAX_PER_RUN', 200),
        'timeout' => 20,
        // Validate IMAP TLS certificates. Only disable for self-signed development servers.
        'validate_certificates' => (bool) env('IMAP_VALIDATE_CERT', true),
        // Subject/sender hints for auto-replies that must not stop a sequence.
        'auto_reply_subjects' => ['out of office', 'automatic reply', 'auto-reply', 'autoreply', 'away from the office'],
    ],

    // Allow SMTP/IMAP hosts on private networks (docker, localhost). Off in production so
    // "test connection" cannot be used to probe the internal network (SSRF).
    'allow_private_hosts' => (bool) env('SEQUENCER_ALLOW_PRIVATE_HOSTS', in_array(env('APP_ENV'), ['local', 'testing'], true)),

    'tracking' => [
        // Base URL for tracking / unsubscribe links. Falls back to APP_URL.
        'public_url' => env('SEQUENCER_PUBLIC_URL') ?: env('APP_URL', 'http://localhost'),
    ],

    // Shown in every email footer (CAN-SPAM requires a postal address).
    'footer_address' => env('SEQUENCER_FOOTER_ADDRESS'),

    'import' => [
        'chunk' => 500,
        'preview_rows' => 20,
        'max_upload_kb' => 20480,
        'disk' => 'local',
    ],

    'bulk' => [
        'chunk' => 200,
    ],

    'api' => [
        'per_page' => 25,
        'max_per_page' => 100,
    ],
];
