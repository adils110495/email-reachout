<?php

namespace App\Models;

use App\Sequencer\Enums\SequenceStatus;
use App\Sequencer\Enums\StepStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sequence extends Model
{
    /** status is changed only through SequenceService (activate / pause / resume / archive). */
    protected $fillable = [
        'name', 'description', 'timezone', 'sending_start_time', 'sending_end_time',
        'sending_days', 'daily_limit', 'mail_setting_id', 'track_opens', 'track_clicks',
    ];

    protected function casts(): array
    {
        return [
            'status' => SequenceStatus::class,
            'sending_days' => 'array',
            'daily_limit' => 'integer',
            'track_opens' => 'boolean',
            'track_clicks' => 'boolean',
            'activated_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Default sending account for new enrollments. */
    public function mailSetting(): BelongsTo
    {
        return $this->belongsTo(MailSetting::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(SequenceStep::class)->orderBy('step_number')->orderBy('id');
    }

    public function activeSteps(): HasMany
    {
        return $this->steps()->where('status', StepStatus::Active->value);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(SequenceEnrollment::class);
    }

    public function leadEmails(): HasMany
    {
        return $this->hasMany(LeadEmail::class);
    }

    /** The sequence's own timezone, else its creator's, else the app default. */
    public function effectiveTimezone(): string
    {
        return $this->timezone ?: ($this->creator?->timezoneName() ?? config('app.timezone'));
    }

    public function isActive(): bool
    {
        return $this->status === SequenceStatus::Active;
    }

    /** "HH:MM" without seconds, for <input type="time"> and display. */
    public function startTime(): string
    {
        return substr((string) $this->sending_start_time, 0, 5);
    }

    public function endTime(): string
    {
        return substr((string) $this->sending_end_time, 0, 5);
    }
}
