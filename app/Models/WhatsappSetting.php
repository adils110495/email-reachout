<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use App\Models\Concerns\ForgetsUnreadableSecrets;
use App\Models\Scopes\OwnedByUserScope;
use Illuminate\Contracts\Encryption\DecryptException;
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

    /** Whether this user's own connection can send - used to pick a sender across users. */
    public static function connectedFor(int $userId): bool
    {
        return (bool) static::withoutGlobalScope(OwnedByUserScope::class)->where('user_id', $userId)->first()?->canSend();
    }

    /**
     * Whose number a message to a record goes out from: the record owner's
     * when it is connected - otherwise the sender's own, so a super admin can
     * message a team member's leads from the company number. The message is
     * logged under that same account, which is where its webhook statuses
     * (delivered / read / replied) arrive and look for it.
     */
    public static function senderFor(int $ownerId, int $senderId): int
    {
        if ($ownerId === $senderId || self::connectedFor($ownerId) || ! self::connectedFor($senderId)) {
            return $ownerId;
        }

        return $senderId;
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
            && filled($this->sendingPhoneNumberId())
            && filled($this->sendingToken());
    }

    // ── Sending credentials ────────────────────────────────────────────────
    // A number connected through Embedded Signup (wa_accounts) wins; the
    // Phone Number ID / token typed into this form are the fallback.

    /** Memo for account(); false = looked up, none connected. Not a column. */
    private WaAccount|false|null $connectedAccount = null;

    /** The owner's number connected through Embedded Signup, if any. */
    public function account(): ?WaAccount
    {
        if ($this->connectedAccount === null) {
            $this->connectedAccount = ($this->user_id ? WaAccount::activeFor((int) $this->user_id) : null) ?? false;
        }

        return $this->connectedAccount ?: null;
    }

    public function sendingPhoneNumberId(): ?string
    {
        return $this->account()?->phone_number_id ?: ($this->phone_number_id ?: null);
    }

    public function sendingBusinessAccountId(): ?string
    {
        return $this->account()?->waba_id ?: ($this->business_account_id ?: null);
    }

    /** Null when there is none - or when it cannot be decrypted here. */
    public function sendingToken(): ?string
    {
        if ($account = $this->account()) {
            return $account->readableToken();
        }

        try {
            return $this->access_token ?: null;
        } catch (DecryptException) {
            return null;
        }
    }

    /** Every Phone Number ID whose webhooks belong to this user. */
    public function ownPhoneNumberIds(): array
    {
        return array_values(array_unique(array_filter([$this->phone_number_id, $this->account()?->phone_number_id])));
    }
}
