<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GmbLead extends Model
{
    protected $fillable = [
        'category_id', 'keyword', 'place_id', 'name', 'type',
        'address', 'phone', 'rating', 'reviews', 'maps_url',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
