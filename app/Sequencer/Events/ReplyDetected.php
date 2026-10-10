<?php

namespace App\Sequencer\Events;

use App\Models\LeadEmail;

/** A genuine (non auto-reply) answer to one of our emails arrived. */
final class ReplyDetected
{
    public function __construct(public readonly LeadEmail $log) {}
}
