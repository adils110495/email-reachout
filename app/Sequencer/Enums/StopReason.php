<?php

namespace App\Sequencer\Enums;

enum StopReason: string
{
    case Replied = 'replied';
    case EmailBounced = 'email_bounced';
    case Unsubscribed = 'unsubscribed';
    case ContactRemoved = 'contact_removed';
    case ContactInactive = 'contact_inactive';
    case ManuallyStopped = 'manually_stopped';
    case SendFailed = 'send_failed';
    case DeliveryUncertain = 'delivery_uncertain';
    case Completed = 'completed';

    public function label(): string
    {
        return ucfirst(str_replace('_', ' ', $this->value));
    }

    /** The enrollment status each reason ends in. */
    public function enrollmentStatus(): EnrollmentStatus
    {
        return match ($this) {
            self::Replied => EnrollmentStatus::Replied,
            self::EmailBounced => EnrollmentStatus::Bounced,
            self::Unsubscribed => EnrollmentStatus::Unsubscribed,
            self::ContactRemoved, self::ContactInactive, self::ManuallyStopped => EnrollmentStatus::Removed,
            self::SendFailed, self::DeliveryUncertain => EnrollmentStatus::Failed,
            self::Completed => EnrollmentStatus::Completed,
        };
    }
}
