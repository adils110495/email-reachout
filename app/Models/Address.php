<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'address',
        'email',
        'phone',
        'alternate_phone',
        'website',
        'status',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
