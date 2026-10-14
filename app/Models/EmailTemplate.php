<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    use BelongsToUser;

    protected $fillable = ['name', 'subject', 'body', 'status', 'attachments'];

    protected $casts = ['attachments' => 'array'];
}
