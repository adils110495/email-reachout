<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class MailSetting extends Model
{
    public const TYPE_SMTP = 'smtp';
    public const TYPE_IMAP = 'imap';

    protected $fillable = [
        'type', 'host', 'port', 'encryption', 'username', 'password',
        'from_address', 'from_name', 'folder', 'is_active',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'password'  => 'encrypted',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The saved, active setting for a type - or null when nothing usable has
     * been saved (callers then fall back to the .env values).
     */
    public static function active(string $type): ?self
    {
        if (! Schema::hasTable('mail_settings')) {
            return null;
        }

        $setting = static::where('type', $type)->where('is_active', true)->first();

        return $setting && $setting->host ? $setting : null;
    }
}
