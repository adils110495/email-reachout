<?php

namespace App\Sequencer\Events;

use App\Models\LeadEmail;

/** A send attempt row was created for an enrollment step. */
final class EmailQueued
{
    public function __construct(public readonly LeadEmail $log) {}
}
