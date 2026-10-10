<?php

namespace App\Sequencer\Services;

use App\Models\Lead;
use App\Models\LeadEmail;
use App\Models\MailSetting;
use App\Models\Sequence;
use App\Models\SequenceStep;
use App\Sequencer\Mail\OutboundEmail;

/** Everything phase B (the SMTP call) needs, produced under lock in phase A. */
final class SendClaim
{
    public function __construct(
        public readonly int $enrollmentId,
        public readonly LeadEmail $log,
        public readonly Lead $lead,
        public readonly Sequence $sequence,
        public readonly SequenceStep $step,
        public readonly MailSetting $account,
        public readonly OutboundEmail $email,
        public readonly string $sequenceDay,
        public readonly string $accountDay,
    ) {}
}
