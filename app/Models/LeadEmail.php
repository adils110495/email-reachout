<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadEmail extends Model
{
    protected $fillable = ['lead_id', 'subject', 'body', 'attachments', 'status', 'sent_at'];

    protected $casts = [
        'attachments' => 'json',
        'sent_at'     => 'datetime',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }
}
