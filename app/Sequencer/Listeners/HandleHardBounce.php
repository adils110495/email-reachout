<?php

namespace App\Sequencer\Listeners;

use App\Sequencer\Enums\ContactStatus;
use App\Sequencer\Enums\StopReason;
use App\Sequencer\Events\BounceDetected;
use App\Sequencer\Services\EnrollmentService;

/** A hard bounce poisons the address: mark the lead and stop every sequence it is in. */
class HandleHardBounce
{
    public function __construct(private readonly EnrollmentService $enrollments) {}

    public function handle(BounceDetected $event): void
    {
        $lead = $event->lead;

        // Unsubscribed is the stronger legal state; do not overwrite it with "bounced".
        if ($lead->contact_status !== ContactStatus::Unsubscribed) {
            $lead->forceFill(['contact_status' => ContactStatus::Bounced, 'bounced_at' => now()])->save();
        }

        $this->enrollments->stopForLead($lead, StopReason::EmailBounced);
    }
}
