<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A message found in an account's IMAP inbox, recorded so it is processed exactly once. */
class InboundMessage extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['received_at' => 'datetime'];
    }

    public function mailSetting(): BelongsTo
    {
        return $this->belongsTo(MailSetting::class);
    }

    public function leadEmail(): BelongsTo
    {
        return $this->belongsTo(LeadEmail::class);
    }
}
