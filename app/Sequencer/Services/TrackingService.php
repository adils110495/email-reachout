<?php

namespace App\Sequencer\Services;

use App\Models\LeadEmail;
use App\Models\TrackedLink;
use App\Sequencer\Events\EmailOpened;
use App\Sequencer\Events\LinkClicked;

/**
 * Records opens and clicks for every email in lead_emails (Leads compose sends and
 * sequence steps). Both entry points take only the random token from the URL;
 * database ids are never exposed and an unknown token reveals nothing.
 */
class TrackingService
{
    /** Called by the tracking pixel. Silent on unknown tokens. */
    public function recordOpen(string $token): void
    {
        $email = LeadEmail::where('tracking_token', $token)->first();
        if (! $email || ! $email->wasSent()) {
            return;
        }

        $this->touchOpened($email, announce: true);
    }

    /**
     * Called by /track/click/{token}.
     *
     * @return string|null the stored destination, or null for an unknown token
     */
    public function recordClick(string $token): ?string
    {
        $link = TrackedLink::where('token', $token)->first();
        if (! $link) {
            return null;
        }

        $url = $link->url;
        if (! preg_match('#^https?://#i', $url)) {
            return null;   // defence in depth: only ever redirect to http(s)
        }

        $email = LeadEmail::find($link->lead_email_id);
        if (! $email) {
            return $url;
        }

        $now = now();

        TrackedLink::whereKey($link->id)->whereNull('first_clicked_at')->update(['first_clicked_at' => $now]);
        TrackedLink::whereKey($link->id)->increment('click_count', 1, ['last_clicked_at' => $now]);

        LeadEmail::whereKey($email->id)->whereNull('clicked_at')->update(['clicked_at' => $now]);
        LeadEmail::whereKey($email->id)->increment('click_count');

        // A click proves the message was opened even if images are blocked.
        $this->touchOpened($email, announce: false);

        event(new LinkClicked($email->refresh(), $link->refresh()));

        return $url;
    }

    private function touchOpened(LeadEmail $email, bool $announce): void
    {
        $now = now();

        $first = LeadEmail::whereKey($email->id)->whereNull('first_opened_at')->update(['first_opened_at' => $now]) === 1;
        LeadEmail::whereKey($email->id)->increment('open_count', 1, ['last_opened_at' => $now]);

        if ($first && $announce) {
            event(new EmailOpened($email->refresh()));
        }
    }
}
