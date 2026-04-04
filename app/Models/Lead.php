<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    use HasFactory;

    // Lead status constants
    const STATUS_NEW = 'new';
    const STATUS_SENT = 'sent';
    const STATUS_FAILED = 'failed';
    const STATUS_REPLIED = 'replied';

    protected $fillable = [
        'company_name',
        'website',
        'email',
        'linkedin',
        'status',
        'platform_id',
    ];

    public function platform()
    {
        return $this->belongsTo(\App\Models\Platform::class);
    }

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Scope: only leads that haven't been emailed yet.
     */
    public function scopeNew($query)
    {
        return $query->where('status', self::STATUS_NEW);
    }

    /**
     * Scope: only leads with a valid email address.
     */
    public function scopeWithEmail($query)
    {
        return $query->whereNotNull('email')->where('email', '!=', '');
    }

    /**
     * Check if this lead has a usable email address.
     */
    public function hasEmail(): bool
    {
        return ! empty($this->email);
    }
}
