<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadEmail extends Model
{
    public const ACTIVITY_NOT_OPENED = 'not_opened';
    public const ACTIVITY_OPENED     = 'opened';
    public const ACTIVITY_REPLIED    = 'replied';

    protected $fillable = [
        'lead_id', 'subject', 'body', 'attachments', 'status', 'sent_at',
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
