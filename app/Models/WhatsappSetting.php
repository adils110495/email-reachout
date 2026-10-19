<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use App\Models\Concerns\ForgetsUnreadableSecrets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A user's WhatsApp Business Cloud API connection - one row per user.
 *
 * Personal even to a super admin, like Mail Settings: always read through
 * forActingUser() (mine()), so nobody ever sends from another user's number.
 */
class WhatsappSetting extends Model
{
    use BelongsToUser, ForgetsUnreadableSecrets;

    // webhook_key is generated here and never posted. verify_token is generated
    // too; the user may replace it, but only through the webhook form (forceFill).
    protected $fillable = [
        'default_country_code', 'phone_number_id', 'business_account_id',
        'access_token', 'app_secret', 'public_url', 'is_active',
    ];

    protected $hidden = ['access_token', 'app_secret', 'webhook_key', 'verify_token'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'app_secret'   => 'encrypted',
            'is_active'    => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (WhatsappSetting $setting) {
            $setting->webhook_key  ??= Str::random(40);
            $setting->verify_token ??= Str::random(32);
        });
    }

    /** The acting user's own row, or null when they have not saved one. */
    public static function forActingUser(): ?self
    {
        return static::mine()->first();
    }

    /**
     * The URL Meta must call for this user's webhooks. Built on the saved
     * public URL when there is one - a page opened on localhost would
     * otherwise hand out a localhost URL Meta can never reach.
     */
    public function callbackUrl(): string
    {
        return $this->public_url
            ? rtrim($this->public_url, '/') . route('webhooks.whatsapp', $this->webhook_key, false)
            : route('webhooks.whatsapp', $this->webhook_key);
    }

    /** True when messages can go out through the Cloud API. */
    public function canSend(): bool
    {
        return $this->is_active
            && filled($this->phone_number_id)
            && filled($this->getRawOriginal('access_token'));
    }
}
