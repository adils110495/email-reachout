<?php

namespace App\Sequencer\Mail;

final class SendResult
{
    public function __construct(
        public readonly string $messageId,
        public readonly ?string $providerReference = null,
    ) {}
}
