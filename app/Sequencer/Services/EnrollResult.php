<?php

namespace App\Sequencer\Services;

use App\Models\SequenceEnrollment;

/** Outcome of trying to enroll one contact. */
final class EnrollResult
{
    public const ENROLLED = 'enrolled';

    public const REACTIVATED = 'reactivated';

    public const ALREADY = 'already_enrolled';

    public const SKIPPED = 'skipped';

    public function __construct(
        public readonly string $outcome,
        public readonly ?SequenceEnrollment $enrollment = null,
        public readonly ?string $reason = null,
    ) {}

    public function wasEnrolled(): bool
    {
        return in_array($this->outcome, [self::ENROLLED, self::REACTIVATED], true);
    }
}
