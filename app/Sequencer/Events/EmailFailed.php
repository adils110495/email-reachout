<?php

namespace App\Sequencer\Events;

use App\Models\LeadEmail;

/** A send failed permanently (or exhausted its retries). */
final class EmailFailed
{
    public function __construct(
        public readonly LeadEmail $log,
        public readonly string $error,
    ) {}
}
