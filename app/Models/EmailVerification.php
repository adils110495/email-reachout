<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailVerification extends Model
{
    public const STATUS_VALID   = 'valid';
    public const STATUS_INVALID = 'invalid';
    public const STATUS_RISKY   = 'risky';
    public const STATUS_UNKNOWN = 'unknown';

    /** Statuses in the order they are shown in filters and stat cards. */
    public const STATUSES = [
        self::STATUS_VALID   => 'Valid',
        self::STATUS_RISKY   => 'Risky',
        self::STATUS_INVALID => 'Invalid',
        self::STATUS_UNKNOWN => 'Unknown',
    ];

    protected $fillable = [
        'email',
        'domain',
        'status',
        'score',
        'reason',
        'checks',
        'source',
        'lead_id',
        'bulk_id',
    ];

    protected $casts = [
        'checks' => 'array',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function bulk(): BelongsTo
    {
        return $this->belongsTo(Bulk::class);
    }

    /** Bootstrap contextual colour used by badges everywhere in the UI. */
    public function getStatusColourAttribute(): string
    {
        return self::colourFor($this->status);
    }

    public static function colourFor(?string $status): string
    {
        return match ($status) {
            self::STATUS_VALID   => 'success',
            self::STATUS_INVALID => 'danger',
            self::STATUS_RISKY   => 'warning',
            default              => 'secondary',
        };
    }
}
