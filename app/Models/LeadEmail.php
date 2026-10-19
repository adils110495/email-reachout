<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class LeadEmail extends Model
{
    use BelongsToUser;

    public const STATUS_QUEUED    = 'queued';    // bulk send: waiting for its turn on the queue
    public const STATUS_SENDING   = 'sending';   // bulk send: claimed by the job, SMTP in flight
    public const STATUS_SENT      = 'sent';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_CANCELLED = 'cancelled'; // bulk send: stopped before its turn

    /** A bulk send still has work to do while any of its emails is in one of these. */
    public const PENDING = [self::STATUS_QUEUED, self::STATUS_SENDING];

    /** These never reached the contact, so they do not count as "already emailed". */
    public const NOT_DELIVERED = [self::STATUS_FAILED, self::STATUS_CANCELLED];

    public const ACTIVITY_NOT_OPENED = 'not_opened';
    public const ACTIVITY_OPENED     = 'opened';
    public const ACTIVITY_REPLIED    = 'replied';

    protected $fillable = [
        'batch_id', 'lead_id', 'cash_lead_id', 'contact_name', 'to_email',
        'subject', 'body', 'attachments', 'status', 'error', 'sent_at',
        'tracking_token', 'message_id', 'open_count', 'first_opened_at', 'last_opened_at', 'replied_at',
    ];

    protected $casts = [
        'attachments'     => 'json',
        'sent_at'         => 'datetime',
        'first_opened_at' => 'datetime',
        'last_opened_at'  => 'datetime',
        'replied_at'      => 'datetime',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function cashLead()
    {
        return $this->belongsTo(CashLead::class);
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

    /**
     * Who it went to. Bulk emails keep a snapshot of the name; emails sent from
     * the Leads compose modal only have their lead.
     */
    public function getRecipientNameAttribute(): ?string
    {
        return $this->contact_name ?? $this->lead?->company_name ?? $this->cashLead?->company_name;
    }

    public function getRecipientEmailAttribute(): ?string
    {
        return $this->to_email ?? $this->lead?->email;
    }
}
