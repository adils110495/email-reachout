<?php

namespace App\Sequencer\Listeners;

use App\Sequencer\Enums\StopReason;
use App\Sequencer\Events\ContactUnsubscribed;
use App\Sequencer\Services\EnrollmentService;

/** After an unsubscribe nothing further may be sent, in any sequence. */
class StopEnrollmentsOnUnsubscribe
{
    public function __construct(private readonly EnrollmentService $enrollments) {}

    public function handle(ContactUnsubscribed $event): void
    {
        $this->enrollments->stopForLead($event->lead, StopReason::Unsubscribed);
    }
}
