<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BulkItem extends Model
{
    public const STATUS_PENDING    = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_DONE       = 'done';
    public const STATUS_FAILED     = 'failed';

    protected $fillable = [
        'bulk_id',
        'input',
        'extra',
        'status',
        'result_status',
        'result_value',
        'score',
        'message',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function bulk(): BelongsTo
    {
        return $this->belongsTo(Bulk::class);
    }

    /** Bootstrap contextual colour for the result badge. */
    public function getResultColourAttribute(): string
    {
        return match ($this->result_status) {
            'valid', 'found' => 'success',
            'invalid'        => 'danger',
            'risky'          => 'warning',
            'not_found'      => 'secondary',
            default          => 'dark',
        };
    }
}
