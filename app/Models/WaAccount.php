<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use App\Models\Concerns\ForgetsUnreadableSecrets;
use App\Models\Scopes\OwnedByUserScope;
use App\Services\WhatsappEmbeddedSignupService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;

/**
 * A WhatsApp number connected through Meta's Embedded Signup - usually a
 * WhatsApp Business app number in "coexistence": it keeps working on the
 * phone and sends through the Cloud API from here too.
 *
 * WhatsappSetting reads its sending credentials from the owner's connected
 * account first, and from its own fields when there is none.
 */
class WaAccount extends Model
{
    use BelongsToUser, ForgetsUnreadableSecrets;

    public const STATUS_CONNECTED    = 'connected';
    public const STATUS_DISCONNECTED = 'disconnected';

    public const HISTORY_REQUESTED = 'requested';
    public const HISTORY_IMPORTING = 'importing';
    public const HISTORY_COMPLETE  = 'complete';
    public const HISTORY_DECLINED  = 'declined';

    /** Meta asks for the app-data sync within 24 hours of onboarding. */
    public const SYNC_WINDOW_HOURS = 24;

    protected $fillable = [
        'waba_id', 'phone_number_id', 'display_phone_number', 'verified_name', 'business_id',
        'access_token', 'token_type', 'token_expires_at', 'two_step_pin', 'is_coexistence', 'onboarding_event',
        'history_sync_requested_at', 'contacts_sync_requested_at', 'onboarded_at', 'history_status', 'status', 'last_error',
    ];

    protected $hidden = ['access_token', 'two_step_pin'];

    protected function casts(): array
    {
        return [
            'access_token'               => 'encrypted',
            'two_step_pin'               => 'encrypted',
            'is_coexistence'             => 'boolean',
            'token_expires_at'           => 'datetime',
            'history_sync_requested_at'  => 'datetime',
            'contacts_sync_requested_at' => 'datetime',
            'onboarded_at'               => 'datetime',
        ];
    }

    /**
     * The account a user's messages go out from: their newest connected one.
     * Read across the owner scope - a webhook or a super admin's send asks
     * about another user.
     */
    public static function activeFor(int $userId): ?self
    {
        return static::withoutGlobalScope(OwnedByUserScope::class)
            ->where('user_id', $userId)
            ->where('status', self::STATUS_CONNECTED)
            ->whereNotNull('phone_number_id')
            ->latest('id')
            ->first();
    }

    /** The token, or null when it cannot be decrypted here (saved under another APP_KEY). */
    public function readableToken(): ?string
    {
        try {
            return $this->access_token ?: null;
        } catch (DecryptException) {
            return null;
        }
    }

    /**
     * May ask the phone app for its contacts and history: a coexistence number,
     * or one whose signup never said how it was connected (a successful sync
     * proves it is coexistence). Not a number registered fresh (FINISH).
     */
    public function canRequestSync(): bool
    {
        return $this->is_coexistence || $this->onboarding_event !== WhatsappEmbeddedSignupService::EVENT_FINISH;
    }

    /** Still inside the 24 hours Meta gives to request the history / contacts sync. */
    public function inSyncWindow(): bool
    {
        return $this->canRequestSync() && ($this->onboarded_at ?? $this->created_at)?->gt(now()->subHours(self::SYNC_WINDOW_HOURS));
    }

    public function tokenExpiresSoon(): bool
    {
        return $this->token_expires_at !== null && $this->token_expires_at->lt(now()->addDays(7));
    }
}
