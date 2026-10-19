<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class WhatsappMessage extends Model
{
    use BelongsToUser;

    public const CHANNEL_API   = 'api';   // sent by the server through the Cloud API
    public const CHANNEL_LINK  = 'link';  // opened in the user's own WhatsApp (wa.me)
    public const CHANNEL_PHONE = 'phone'; // coexistence: the WhatsApp Business app on the phone (echoes, imported history)

    public const CHANNEL_LABELS = [
        self::CHANNEL_API   => 'Cloud API',
        self::CHANNEL_LINK  => 'wa.me link',
        self::CHANNEL_PHONE => 'WhatsApp app',
    ];

    public const DIRECTION_OUT = 'out';
    public const DIRECTION_IN  = 'in';   // only imported history - live incoming messages stay replies

    public const TYPE_TEXT     = 'text';
    public const TYPE_TEMPLATE = 'template';

    public const STATUS_QUEUED    = 'queued';    // bulk send: waiting for its turn on the queue
    public const STATUS_SENDING   = 'sending';   // bulk send: claimed by the job, request in flight
    public const STATUS_SENT      = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_READ      = 'read';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_CANCELLED = 'cancelled'; // bulk send: stopped before its turn
    public const STATUS_MANUAL    = 'manual';    // wa.me chat opened; we cannot see what happened next
    public const STATUS_RECEIVED  = 'received';  // imported history: a message the contact sent

    public const ACTIVITY_REPLIED = 'replied';

    /** A bulk send still has work to do while any of its messages is in one of these. */
    public const PENDING = [self::STATUS_QUEUED, self::STATUS_SENDING];

    /** These never reached the contact, so they do not count as "already messaged". */
    public const NOT_DELIVERED = [self::STATUS_FAILED, self::STATUS_CANCELLED];

    /** What the activity filter and the badges show: the statuses, plus "replied". */
    public const ACTIVITIES = [
        self::STATUS_QUEUED     => 'Queued',
        self::STATUS_SENDING    => 'Sending',
        self::STATUS_SENT       => 'Sent',
        self::STATUS_DELIVERED  => 'Delivered',
        self::STATUS_READ       => 'Read',
        self::ACTIVITY_REPLIED  => 'Replied',
        self::STATUS_FAILED     => 'Failed',
        self::STATUS_CANCELLED  => 'Cancelled',
        self::STATUS_MANUAL     => 'Opened in WhatsApp',
        self::STATUS_RECEIVED   => 'Received',
    ];

    private const BADGES = [
        self::STATUS_QUEUED     => ['badge-light',     'bi-hourglass-split'],
        self::STATUS_SENDING    => ['badge-light',     'bi-arrow-repeat'],
        self::STATUS_SENT       => ['badge-secondary', 'bi-check'],
        self::STATUS_DELIVERED  => ['badge-info',      'bi-check2-all'],
        self::STATUS_READ       => ['badge-primary',   'bi-check2-all'],
        self::ACTIVITY_REPLIED  => ['badge-success',   'bi-reply'],
        self::STATUS_FAILED     => ['badge-danger',    'bi-x-circle'],
        self::STATUS_CANCELLED  => ['badge-dark',      'bi-slash-circle'],
        self::STATUS_MANUAL     => ['badge-warning',   'bi-box-arrow-up-right'],
        self::STATUS_RECEIVED   => ['badge-success',   'bi-box-arrow-in-down-left'],
    ];

    /** Webhook statuses only move forward: a late "delivered" must not undo "read". */
    private const RANK = [
        self::STATUS_SENT      => 1,
        self::STATUS_DELIVERED => 2,
        self::STATUS_READ      => 3,
    ];

    protected $fillable = [
        'batch_id', 'gmb_lead_id', 'cash_lead_id', 'contact_name', 'phone', 'channel', 'direction', 'type',
        'template_name', 'template_language', 'params', 'body', 'wamid', 'status', 'error',
        'sent_at', 'delivered_at', 'read_at', 'replied_at', 'reply_body',
    ];

    protected $casts = [
        'params'       => 'array',
        'sent_at'      => 'datetime',
        'delivered_at' => 'datetime',
        'read_at'      => 'datetime',
        'replied_at'   => 'datetime',
    ];

    public function gmbLead()
    {
        return $this->belongsTo(GmbLead::class);
    }

    public function cashLead()
    {
        return $this->belongsTo(CashLead::class);
    }

    /** A reply wins over every delivery status - it is the strongest signal. */
    public function getActivityAttribute(): string
    {
        return $this->replied_at ? self::ACTIVITY_REPLIED : $this->status;
    }

    /** @return array{class: string, icon: string, label: string} */
    public static function badgeFor(string $activity): array
    {
        [$class, $icon] = self::BADGES[$activity] ?? self::BADGES[self::STATUS_SENT];

        return ['class' => $class, 'icon' => $icon, 'label' => self::ACTIVITIES[$activity] ?? ucfirst($activity)];
    }

    public function getBadgeAttribute(): array
    {
        return self::badgeFor($this->activity);
    }

    /** "+919876543210" - how the number is shown. */
    public function getDisplayPhoneAttribute(): string
    {
        return '+' . $this->phone;
    }

    /**
     * Scope: messages whose activity (see getActivityAttribute) is $activity.
     */
    public function scopeActivity(Builder $query, string $activity): Builder
    {
        return $activity === self::ACTIVITY_REPLIED
            ? $query->whereNotNull('replied_at')
            : $query->whereNull('replied_at')->where('status', $activity);
    }

    /**
     * Apply a status from Meta's webhook. Statuses can arrive out of order, so
     * each one only fills its own timestamp and never moves the status back.
     */
    public function applyStatus(string $status, Carbon $at, ?string $error = null): void
    {
        if ($status === self::STATUS_FAILED) {
            $this->status = self::STATUS_FAILED;
            $this->error  = $error;
        } elseif (isset(self::RANK[$status])) {
            if ($status === self::STATUS_READ) {
                $this->read_at ??= $at;
                $this->delivered_at ??= $at;   // read implies delivered
            } elseif ($status === self::STATUS_DELIVERED) {
                $this->delivered_at ??= $at;
            }

            if (self::RANK[$status] > (self::RANK[$this->status] ?? 0)) {
                $this->status = $status;
            }
        } else {
            return;
        }

        $this->save();
    }
}
