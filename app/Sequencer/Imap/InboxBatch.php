<?php

namespace App\Sequencer\Imap;

/** The result of one mailbox poll. */
final class InboxBatch
{
    /**
     * @param  list<InboundEmail>  $messages  oldest first
     * @param  int  $uidNext  the mailbox's next UID (highest existing UID + 1); 0 when unknown
     * @param  bool  $truncated  true when more messages remain beyond the per-run cap
     */
    public function __construct(
        public readonly int $uidValidity,
        public readonly array $messages,
        public readonly int $uidNext = 0,
        public readonly bool $truncated = false,
    ) {}
}
