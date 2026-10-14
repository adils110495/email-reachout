<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class MailSetting extends Model
{
    use BelongsToUser;

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
     * The acting user's saved, active setting for a type - or null when they
     * have not saved anything usable (callers then fall back to the .env values).
     *
     * mine(), not the global scope: a super admin must send from their own
     * mailbox, never from whichever user's row happens to come first.
     */
    public static function active(string $type): ?self
    {
        if (! Schema::hasTable('mail_settings')) {
            return null;
        }

        $setting = static::mine()->where('type', $type)->where('is_active', true)->first();

        return $setting && $setting->host ? $setting : null;
    }
}
