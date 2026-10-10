<?php

namespace App\Models;

use App\Sequencer\Enums\StepStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SequenceStep extends Model
{
    protected $fillable = ['subject', 'body', 'delay_minutes', 'delay_hours', 'delay_days', 'status'];

    protected function casts(): array
    {
        return [
            'status' => StepStatus::class,
            'step_number' => 'integer',
            'delay_minutes' => 'integer',
            'delay_hours' => 'integer',
            'delay_days' => 'integer',
        ];
    }

    public function sequence(): BelongsTo
    {
        return $this->belongsTo(Sequence::class);
    }

    public function leadEmails(): HasMany
    {
        return $this->hasMany(LeadEmail::class, 'sequence_step_id');
    }

    /** Total wait before this step, in minutes. */
    public function delayInMinutes(): int
    {
        return ($this->delay_days * 1440) + ($this->delay_hours * 60) + $this->delay_minutes;
    }

    /** "2 days", "3 hours 30 minutes", "Immediately". */
    public function delayLabel(): string
    {
        $parts = [];
        foreach ([['days', $this->delay_days], ['hours', $this->delay_hours], ['minutes', $this->delay_minutes]] as [$unit, $n]) {
            if ($n > 0) {
                $parts[] = $n.' '.($n === 1 ? rtrim($unit, 's') : $unit);
            }
        }

        return $parts ? implode(' ', $parts) : 'Immediately';
    }
}
