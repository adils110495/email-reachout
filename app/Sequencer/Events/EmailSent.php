<?php

namespace App\Sequencer\Events;

use App\Models\LeadEmail;

/** A sequence email was handed to the SMTP server. */
final class EmailSent
{
    public function __construct(public readonly LeadEmail $log) {}
}
