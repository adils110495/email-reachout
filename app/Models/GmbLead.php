<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class GmbLead extends Model
{
    use BelongsToUser;

    /** place_id of a lead added by hand (GMB Leads > Add) - Google's own ids never start with it. */
    public const MANUAL_PREFIX = 'manual-';

    protected $fillable = [
        'category_id', 'keyword', 'place_id', 'name', 'type',
        'address', 'phone', 'rating', 'reviews', 'maps_url',
    ];

    public function isManual(): bool
    {
        return str_starts_with((string) $this->place_id, self::MANUAL_PREFIX);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function whatsappMessages()
    {
        return $this->hasMany(WhatsappMessage::class);
    }
}
