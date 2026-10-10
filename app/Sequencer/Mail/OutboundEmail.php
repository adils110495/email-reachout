<?php

namespace App\Sequencer\Mail;

/** Everything a provider needs to deliver one message. Provider-agnostic. */
final class OutboundEmail
{
    /**
     * @param  array<string, string>  $headers  extra headers, e.g. List-Unsubscribe
     */
    public function __construct(
        public readonly string $fromEmail,
        public readonly string $fromName,
        public readonly string $toEmail,
        public readonly ?string $toName,
        public readonly string $subject,
        public readonly string $html,
        public readonly string $text,
        public readonly string $messageId,          // without angle brackets
        public readonly array $headers = [],
        public readonly ?string $replyTo = null,
    ) {}
}
