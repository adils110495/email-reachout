<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailySendCounter extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['day' => 'date:Y-m-d', 'count' => 'integer'];
    }
}
