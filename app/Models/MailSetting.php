<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use App\Models\Concerns\ForgetsUnreadableSecrets;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class MailSetting extends Model
{
    use BelongsToUser, ForgetsUnreadableSecrets;

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
     * The password, or null when there is none - or when this server cannot
     * decrypt it: it was saved under another APP_KEY (a database copied from
     * another server, a regenerated key). Reading $this->password would throw.
     */
    public function readablePassword(): ?string
    {
        if (blank($this->getRawOriginal('password'))) {
            return null;
        }

        try {
            return $this->password;
        } catch (DecryptException) {
            return null;
        }
    }

    /** A password is saved, but this server cannot read it - it must be entered again. */
    public function passwordUnreadable(): bool
    {
        return filled($this->getRawOriginal('password')) && $this->readablePassword() === null;
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
