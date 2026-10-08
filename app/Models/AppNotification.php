<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class AppNotification extends Model
{
    protected $fillable = ['type', 'title', 'message', 'url', 'read_at'];

    protected $casts = ['read_at' => 'datetime'];

    /**
     * Raise a notification from a background job. Never throws: a failure to
     * notify must not fail (and so retry) the job that did the real work.
     */
    public static function raise(string $type, string $title, ?string $message = null, ?string $url = null): void
    {
        try {
            static::create(compact('type', 'title', 'message', 'url'));
        } catch (\Throwable $e) {
            Log::warning('AppNotification: could not store notification', ['error' => $e->getMessage()]);
        }
    }
}
