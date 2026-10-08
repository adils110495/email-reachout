<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bulk extends Model
{
    public const TYPE_VERIFY = 'verify';
    public const TYPE_FIND   = 'find';

    public const STATUS_PENDING    = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED  = 'completed';
    public const STATUS_FAILED     = 'failed';
    public const STATUS_CANCELLED  = 'cancelled';

    protected $fillable = [
        'name',
        'type',
        'category_id',
        'original_filename',
        'file_path',
        'status',
        'total_records',
        'processed_records',
        'successful_records',
        'failed_records',
        'error',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(BulkItem::class);
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(EmailVerification::class);
    }

    /** Category that a "find" run files its discovered leads under. */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** Percentage of records processed so far (0-100). */
    public function getProgressAttribute(): int
    {
        if ($this->total_records < 1) {
            return $this->status === self::STATUS_COMPLETED ? 100 : 0;
        }

        return (int) min(100, round(($this->processed_records / $this->total_records) * 100));
    }

    public function isRunning(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING], true);
    }

    /** Bootstrap contextual colour for the status badge / progress bar. */
    public function getStatusColourAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_COMPLETED  => 'success',
            self::STATUS_PROCESSING => 'primary',
            self::STATUS_FAILED     => 'danger',
            self::STATUS_CANCELLED  => 'dark',
            default                 => 'warning',
        };
    }
}
