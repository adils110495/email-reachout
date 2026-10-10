<?php

namespace App\Sequencer\Imap;

use Carbon\CarbonImmutable;

/** One message read from a mailbox, reduced to what reply / bounce detection needs. */
final class InboundEmail
{
    /**
     * @param  list<string>  $references  Message-IDs from the References header, without <>
     * @param  array<string, string>  $headers  lower-cased header name => (unfolded) value
     * @param  string|null  $rawSource  full source, only fetched for bounce candidates
     */
    public function __construct(
        public readonly int $uid,
        public readonly ?string $messageId,          // without <>
        public readonly ?string $inReplyTo,          // without <>
        public readonly array $references,
        public readonly ?string $fromEmail,
        public readonly string $subject,
        public readonly ?CarbonImmutable $date,
        public readonly array $headers = [],
        public readonly ?string $rawSource = null,
    ) {}

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /** Every Message-ID this message points back to (In-Reply-To first, then References, newest first). */
    public function referencedIds(): array
    {
        $ids = array_filter([$this->inReplyTo, ...array_reverse($this->references)]);

        return array_values(array_unique($ids));
    }
}
