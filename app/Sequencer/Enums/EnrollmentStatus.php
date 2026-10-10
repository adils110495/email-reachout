<?php

namespace App\Sequencer\Enums;

enum EnrollmentStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Paused = 'paused';
    case Completed = 'completed';
    case Replied = 'replied';
    case Bounced = 'bounced';
    case Unsubscribed = 'unsubscribed';
    case Removed = 'removed';
    case Failed = 'failed';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'secondary',
            self::Active => 'primary',
            self::Paused => 'warning',
            self::Completed => 'success',
            self::Replied => 'info',
            self::Bounced, self::Failed => 'danger',
            self::Unsubscribed => 'warning',
            self::Removed => 'dark',
        };
    }

    /** Statuses that can still progress (or be resumed): pending, active, paused. */
    public function isOpen(): bool
    {
        return in_array($this, self::open(), true);
    }

    /** Final states reached by a stop condition or completion. */
    public function isTerminal(): bool
    {
        return ! $this->isOpen();
    }

    /** @return list<self> */
    public static function open(): array
    {
        return [self::Pending, self::Active, self::Paused];
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
