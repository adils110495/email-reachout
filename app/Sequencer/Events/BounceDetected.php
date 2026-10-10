<?php

namespace App\Sequencer\Events;

use App\Models\Lead;
use App\Models\LeadEmail;

/** A hard bounce: the address does not accept mail. Soft bounces do not raise this. */
final class BounceDetected
{
    public function __construct(
        public readonly Lead $lead,
        public readonly ?LeadEmail $log,
        public readonly string $reason,
    ) {}
}
