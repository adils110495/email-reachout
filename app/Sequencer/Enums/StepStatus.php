<?php

namespace App\Sequencer\Enums;

enum StepStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function badge(): string
    {
        return $this === self::Active ? 'success' : 'secondary';
    }
}
