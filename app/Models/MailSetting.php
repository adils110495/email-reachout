<?php

namespace App\Models;

use App\Casts\SafeEncrypted;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

/**
 * A sending account (Settings > Mail Settings).
 *
 * Each row holds one mailbox: SMTP for sending (host, port, encryption, username,
 * password, from_*), IMAP for the Sent-folder copy (`folder`) and for reply/bounce
 * polling (`imap_*`, `imap_folder`), plus sequencer limits and health.
 *
 * The default account (is_default) is what the Leads compose window and every other
 * non-sequence send uses; sequences may pick any active account.
 */
class MailSetting extends Model
{
    protected $fillable = [
        'name', 'provider', 'host', 'port', 'encryption', 'username', 'password',
        'from_address', 'from_name', 'folder', 'is_active', 'is_default',
        'imap_host', 'imap_port', 'imap_encryption', 'imap_username', 'imap_password', 'imap_folder',
        'provider_config', 'daily_limit', 'rate_limit_per_minute',
    ];

    /** Secrets never leave the server: not in JSON, API resources or arrays. */
    protected $hidden = ['password', 'imap_password', 'provider_config'];

    protected function casts(): array
    {
        return [
            // Unreadable ciphertext (APP_KEY changed) reads as null instead of crashing the page.
            'password' => SafeEncrypted::class,
            'imap_password' => SafeEncrypted::class,
            'provider_config' => 'encrypted:array',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'port' => 'integer',
            'imap_port' => 'integer',
            'daily_limit' => 'integer',
            'rate_limit_per_minute' => 'integer',
            'smtp_ok' => 'boolean',
            'imap_ok' => 'boolean',
            'smtp_tested_at' => 'datetime',
            'imap_tested_at' => 'datetime',
            'imap_last_checked_at' => 'datetime',
            'imap_last_uid' => 'integer',
            'imap_uid_validity' => 'integer',
        ];
    }

    /**
     * The account used for ordinary sends: the active default, else the first active
     * one. Null when nothing usable is saved (callers then fall back to .env).
     */
    public static function defaultAccount(): ?self
    {
        if (! Schema::hasTable('mail_settings')) {
            return null;
        }

        return static::active()->orderByDesc('is_default')->orderBy('id')->first();
    }

    /** Make this the only default account. */
    public function makeDefault(): void
    {
        static::whereKeyNot($this->getKey())->where('is_default', true)->update(['is_default' => false]);
        $this->forceFill(['is_default' => true])->save();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(SequenceEnrollment::class);
    }

    public function leadEmails(): HasMany
    {
        return $this->hasMany(LeadEmail::class);
    }

    public function hasSmtp(): bool
    {
        return filled($this->host);
    }

    public function hasImap(): bool
    {
        return filled($this->imap_host) && filled($this->imap_username) && filled($this->imap_password);
    }

    /**
     * Saved passwords that exist but can no longer be decrypted (APP_KEY changed).
     *
     * @return list<string> e.g. ['SMTP', 'IMAP']
     */
    public function unreadableSecrets(): array
    {
        $labels = ['password' => 'SMTP', 'imap_password' => 'IMAP'];

        return array_values(array_filter(
            $labels,
            fn ($column) => SafeEncrypted::isUnreadable($this->getRawOriginal($column)),
            ARRAY_FILTER_USE_KEY,
        ));
    }

    /** Whether a usable (decryptable) password is saved in this column. */
    public function hasStoredSecret(string $column): bool
    {
        return filled($this->getRawOriginal($column)) && ! SafeEncrypted::isUnreadable($this->getRawOriginal($column));
    }

    /** The From address, falling back to the SMTP login. */
    public function senderEmail(): string
    {
        return (string) ($this->from_address ?: $this->username);
    }

    public function label(): string
    {
        return ($this->name ?: $this->senderEmail()).($this->senderEmail() && $this->name !== $this->senderEmail() ? ' <'.$this->senderEmail().'>' : '');
    }
}
