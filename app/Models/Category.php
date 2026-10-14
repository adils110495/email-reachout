<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use BelongsToUser;

    protected $fillable = ['name', 'status'];

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
