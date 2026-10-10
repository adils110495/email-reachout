<?php

namespace App\Models;

use App\Sequencer\Enums\EmailLogStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Every email sent to a lead - from the Leads compose window or from a sequence step.
 *
 * `status` is the delivery state (queued -> sending -> sent | failed | bounced).
 * Engagement is kept in timestamps/counters (first_opened_at, clicked_at, replied_at,
 * bounced_at) so it never fights the delivery state; engagement() derives one label.
 */
class LeadEmail extends Model
{
    public const ACTIVITY_NOT_OPENED = 'not_opened';
    public const ACTIVITY_OPENED     = 'opened';
    public const ACTIVITY_REPLIED    = 'replied';

    protected $fillable = [
        'lead_id', 'subject', 'body', 'attachments', 'status', 'sent_at',
        'tracking_token', 'message_id', 'open_count', 'first_opened_at', 'last_opened_at', 'replied_at',
        'sequence_id', 'sequence_step_id', 'enrollment_id', 'mail_setting_id', 'from_email', 'to_email',
        'attempts', 'claimed_at', 'clicked_at', 'click_count', 'bounced_at', 'error_message', 'metadata',
    ];

    /** The tracking token keys the public open pixel: never serialised. */
    protected $hidden = ['tracking_token'];

    protected $casts = [
        'attachments'     => 'json',
        'metadata'        => 'array',
        'sent_at'         => 'datetime',
        'first_opened_at' => 'datetime',
        'last_opened_at'  => 'datetime',
        'replied_at'      => 'datetime',
        'claimed_at'      => 'datetime',
        'clicked_at'      => 'datetime',
        'bounced_at'      => 'datetime',
        'attempts'        => 'integer',
        'open_count'      => 'integer',
        'click_count'     => 'integer',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function sequence(): BelongsTo
    {
        return $this->belongsTo(Sequence::class);
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(SequenceStep::class, 'sequence_step_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(SequenceEnrollment::class, 'enrollment_id');
    }

    public function mailSetting(): BelongsTo
    {
        return $this->belongsTo(MailSetting::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(TrackedLink::class);
    }

    /** Delivery status as an enum (unknown legacy values read as "sent"). */
    public function deliveryStatus(): EmailLogStatus
    {
        return EmailLogStatus::tryFrom((string) $this->status) ?? EmailLogStatus::Sent;
    }

    /** True once the message left our server (it must never be sent again). */
    public function wasSent(): bool
    {
        return $this->deliveryStatus()->wasSent();
    }

    /** One label for lists and the API: bounced > replied > clicked > opened > delivery state. */
    public function engagement(): EmailLogStatus
    {
        return match (true) {
            $this->bounced_at !== null || $this->status === EmailLogStatus::Bounced->value => EmailLogStatus::Bounced,
            $this->replied_at !== null => EmailLogStatus::Replied,
            $this->clicked_at !== null => EmailLogStatus::Clicked,
            $this->open_count > 0 => EmailLogStatus::Opened,
            default => $this->deliveryStatus(),
        };
    }

    /**
     * replied > opened > not_opened. A reply wins because it is the stronger signal.
     */
    public function getActivityAttribute(): string
    {
        if ($this->replied_at) {
            return self::ACTIVITY_REPLIED;
        }

        return $this->open_count > 0 ? self::ACTIVITY_OPENED : self::ACTIVITY_NOT_OPENED;
    }
}
