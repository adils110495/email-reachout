<?php

namespace App\Sequencer\Events;

use App\Models\SequenceEnrollment;
use App\Sequencer\Enums\StopReason;

final class EnrollmentStopped
{
    public function __construct(
        public readonly SequenceEnrollment $enrollment,
        public readonly StopReason $reason,
    ) {}
}
