<?php

namespace App\Sequencer\Enums;

enum EmailLogStatus: string
{
    case Queued = 'queued';
    case Sending = 'sending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Bounced = 'bounced';
    case Opened = 'opened';
    case Clicked = 'clicked';
    case Replied = 'replied';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Queued => 'secondary',
            self::Sending => 'info',
            self::Sent => 'primary',
            self::Opened => 'info',
            self::Clicked, self::Replied => 'success',
            self::Failed, self::Bounced => 'danger',
        };
    }

    /**
     * Statuses meaning "the message left our server". Once a log is in one of
     * these the step must never be sent again (idempotency).
     *
     * @return list<self>
     */
    public static function delivered(): array
    {
        return [self::Sent, self::Opened, self::Clicked, self::Replied, self::Bounced];
    }

    public function wasSent(): bool
    {
        return in_array($this, self::delivered(), true);
    }

    /** Engagement only moves a log forward: sent < opened < clicked < replied. */
    public function rank(): int
    {
        return match ($this) {
            self::Queued => 0,
            self::Sending, self::Failed => 1,
            self::Sent => 2,
            self::Opened => 3,
            self::Clicked => 4,
            self::Replied => 5,
            self::Bounced => 6,
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
