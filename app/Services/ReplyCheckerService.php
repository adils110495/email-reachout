<?php

namespace App\Services;

use App\Models\LeadEmail;

/**
 * Marks sent emails as replied by looking through the IMAP inbox.
 *
 * A message counts as a reply when its In-Reply-To / References header carries
 * the Message-ID we set at send time, or - as a fallback for clients that drop
 * those headers - when it comes from the lead's address after we sent.
 */
class ReplyCheckerService
{
    public function __construct(private ImapService $imap) {}

    /**
     * @return int number of emails newly marked as replied
     * @throws \RuntimeException when the mailbox cannot be read
     */
    public function check(): int
    {
        $pending = LeadEmail::with('lead')
            ->where('status', 'sent')
            ->whereNull('replied_at')
            ->whereNotNull('sent_at')
            ->where('sent_at', '>=', now()->subDays(60))
            ->get();

        if ($pending->isEmpty()) {
            return 0;
        }

        $messages = $this->imap->recentInboxMessages($pending->min('sent_at'));
        $marked   = 0;

        foreach ($pending as $email) {
            // A reply may come from any address the lead has, not just the primary.
            $leadAddresses = array_map('strtolower', $email->lead?->email_list ?? []);

            foreach ($messages as $message) {
                $byHeader = $email->message_id
                    && str_contains($message['in_reply_to'] . ' ' . $message['references'], $email->message_id);

                $byAddress = in_array($message['from'], $leadAddresses, true)
                    && $message['date']->gte($email->sent_at);

                if ($byHeader || $byAddress) {
                    $email->update(['replied_at' => $message['date']]);
                    $marked++;
                    break;
                }
            }
        }

        return $marked;
    }
}
