<?php

namespace App\Models;

use App\Sequencer\Enums\EnrollmentStatus;
use App\Sequencer\Enums\StopReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A lead's progress through one sequence. */
class SequenceEnrollment extends Model
{
    // Written only by EnrollmentService / the send pipeline, never from request input.
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => EnrollmentStatus::class,
            'current_step' => 'integer',
            'started_at' => 'datetime',
            'next_action_at' => 'datetime',
            'completed_at' => 'datetime',
            'stopped_at' => 'datetime',
            'paused_at' => 'datetime',
            'dispatch_lease_until' => 'datetime',
        ];
    }

    public function sequence(): BelongsTo
    {
        return $this->belongsTo(Sequence::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function mailSetting(): BelongsTo
    {
        return $this->belongsTo(MailSetting::class);
    }

    public function leadEmails(): HasMany
    {
        return $this->hasMany(LeadEmail::class, 'enrollment_id');
    }

    public function activityEvents(): HasMany
    {
        return $this->hasMany(ActivityEvent::class, 'enrollment_id');
    }

    public function stopReasonEnum(): ?StopReason
    {
        return $this->stop_reason ? StopReason::tryFrom($this->stop_reason) : null;
    }
}
