<?php

namespace App\Sequencer\Listeners;

use App\Models\SequenceEnrollment;
use App\Sequencer\Enums\StopReason;
use App\Sequencer\Events\ReplyDetected;
use App\Sequencer\Services\EnrollmentService;

/** A reply ends the sequence for that contact: no more follow-ups. */
class StopEnrollmentOnReply
{
    public function __construct(private readonly EnrollmentService $enrollments) {}

    public function handle(ReplyDetected $event): void
    {
        $enrollment = $event->log->enrollment_id ? SequenceEnrollment::find($event->log->enrollment_id) : null;

        if ($enrollment) {
            $this->enrollments->stop($enrollment, StopReason::Replied);
        }
    }
}
