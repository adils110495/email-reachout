<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A link in a sent email, rewritten to /track/click/{token}. */
class TrackedLink extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'click_count' => 'integer',
            'first_clicked_at' => 'datetime',
            'last_clicked_at' => 'datetime',
        ];
    }

    public function leadEmail(): BelongsTo
    {
        return $this->belongsTo(LeadEmail::class);
    }
}
