<?php

namespace App\Sequencer\Events;

use App\Models\LeadEmail;
use App\Models\TrackedLink;

final class LinkClicked
{
    public function __construct(
        public readonly LeadEmail $log,
        public readonly TrackedLink $link,
    ) {}
}
