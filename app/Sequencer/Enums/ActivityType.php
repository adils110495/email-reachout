<?php

namespace App\Sequencer\Enums;

enum ActivityType: string
{
    case EmailQueued = 'email_queued';
    case EmailSent = 'email_sent';
    case EmailFailed = 'email_failed';
    case EmailOpened = 'email_opened';
    case LinkClicked = 'link_clicked';
    case ReplyReceived = 'reply_received';
    case BounceReceived = 'bounce_received';
    case SequenceStopped = 'sequence_stopped';
    case SequenceCompleted = 'sequence_completed';
    case Unsubscribed = 'unsubscribed';
    case Enrolled = 'enrolled';
    case Paused = 'paused';
    case Resumed = 'resumed';

    public function label(): string
    {
        return match ($this) {
            self::EmailQueued => 'Email queued',
            self::EmailSent => 'Email sent',
            self::EmailFailed => 'Email failed',
            self::EmailOpened => 'Opened',
            self::LinkClicked => 'Link clicked',
            self::ReplyReceived => 'Reply received',
            self::BounceReceived => 'Bounce received',
            self::SequenceStopped => 'Sequence stopped',
            self::SequenceCompleted => 'Sequence completed',
            self::Unsubscribed => 'Unsubscribed',
            self::Enrolled => 'Enrolled',
            self::Paused => 'Paused',
            self::Resumed => 'Resumed',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::EmailQueued => 'bi-hourglass-split',
            self::EmailSent => 'bi-send',
            self::EmailFailed => 'bi-x-octagon',
            self::EmailOpened => 'bi-envelope-open',
            self::LinkClicked => 'bi-cursor',
            self::ReplyReceived => 'bi-reply',
            self::BounceReceived => 'bi-exclamation-triangle',
            self::SequenceStopped => 'bi-stop-circle',
            self::SequenceCompleted => 'bi-check2-circle',
            self::Unsubscribed => 'bi-person-dash',
            self::Enrolled => 'bi-person-plus',
            self::Paused => 'bi-pause-circle',
            self::Resumed => 'bi-play-circle',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::EmailFailed, self::BounceReceived => 'danger',
            self::ReplyReceived, self::SequenceCompleted => 'success',
            self::SequenceStopped, self::Unsubscribed, self::Paused => 'warning',
            self::EmailOpened, self::LinkClicked => 'info',
            default => 'primary',
        };
    }
}
