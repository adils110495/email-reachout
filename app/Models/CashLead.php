<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashLead extends Model
{
    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_LEAD   = 'lead';
    public const SOURCE_GMB    = 'gmb';

    public const SOURCES = [
        self::SOURCE_MANUAL => 'Added manually',
        self::SOURCE_LEAD   => 'From Leads',
        self::SOURCE_GMB    => 'From GMB Leads',
    ];

    protected $fillable = [
        'source', 'lead_id', 'gmb_lead_id', 'category_id',
        'company_name', 'email', 'phone', 'website', 'address', 'notes',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
