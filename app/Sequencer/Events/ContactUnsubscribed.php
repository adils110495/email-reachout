<?php

namespace App\Sequencer\Events;

use App\Models\Lead;
use App\Models\LeadEmail;

final class ContactUnsubscribed
{
    public function __construct(
        public readonly Lead $lead,
        public readonly ?LeadEmail $log = null,
        public readonly string $source = 'link',
    ) {}
}
