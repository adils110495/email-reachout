<?php

namespace App\Sequencer\Enums;

enum ContactStatus: string
{
    case Active = 'active';
    case Unsubscribed = 'unsubscribed';
    case Bounced = 'bounced';
    case Invalid = 'invalid';
    case Archived = 'archived';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Unsubscribed => 'warning',
            self::Bounced, self::Invalid => 'danger',
            self::Archived => 'secondary',
        };
    }

    /** Only active contacts may receive sequence email. */
    public function canReceiveEmail(): bool
    {
        return $this === self::Active;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
