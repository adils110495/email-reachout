<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // OpenAI configuration
    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-3.5-turbo'),
    ],

    // SerpAPI — Google Search JSON API
    // 100 free searches/month. Sign up at: https://serpapi.com
    'serpapi' => [
        'key' => env('SERPAPI_KEY'),
    ],

    // Email verification (Verifier / Bulks modules).
    //
    // The SMTP probe holds a real RCPT TO conversation with the recipient's
    // mail server, which is the only way to confirm a mailbox exists. It is off
    // by default because most hosts block outbound port 25 — with it blocked
    // every address would come back "unknown". Enable it only where port 25 is
    // open, and keep smtp_from on a domain you control: the probe identifies
    // itself with that address.
    'email_verifier' => [
        'smtp'         => filter_var(env('VERIFY_SMTP_PROBE', false), FILTER_VALIDATE_BOOLEAN),
        'smtp_timeout' => (int) env('VERIFY_SMTP_TIMEOUT', 8),
        'smtp_from'    => env('VERIFY_SMTP_FROM', env('MAIL_FROM_ADDRESS')),
        // Seconds a domain's DNS answer is reused for. One day keeps a bulk run
        // of 5,000 addresses down to one lookup per distinct domain.
        'cache_ttl'    => (int) env('VERIFY_CACHE_TTL', 86400),
        // Hard ceiling on rows accepted from one CSV upload.
        'bulk_max_rows' => (int) env('VERIFY_BULK_MAX_ROWS', 5000),
    ],

];
