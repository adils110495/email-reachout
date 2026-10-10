<?php

namespace App\Sequencer\Services;

/** What a delivery-failure notification (DSN) says. */
final class BounceInfo
{
    /**
     * @param  list<string>  $messageIds  every Message-ID found in the notification (the original is among them)
     */
    public function __construct(
        public readonly bool $hard,
        public readonly ?string $status,        // enhanced status code, e.g. 5.1.1
        public readonly ?string $recipient,
        public readonly string $reason,
        public readonly array $messageIds = [],
    ) {}
}
