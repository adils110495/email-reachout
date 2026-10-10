<?php

namespace App\Sequencer\Events;

use App\Models\LeadEmail;

/** The tracking pixel was loaded for the first time. */
final class EmailOpened
{
    public function __construct(public readonly LeadEmail $log) {}
}
