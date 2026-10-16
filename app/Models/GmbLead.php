<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class GmbLead extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'category_id', 'keyword', 'place_id', 'name', 'type',
        'address', 'phone', 'rating', 'reviews', 'maps_url',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function whatsappMessages()
    {
        return $this->hasMany(WhatsappMessage::class);
    }
}
