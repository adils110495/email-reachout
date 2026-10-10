<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Laravel's "encrypted" cast, except a value that cannot be decrypted reads as null
 * instead of throwing.
 *
 * Stored mail passwords become unreadable when APP_KEY changes. Throwing there takes
 * down every page that merely asks "is IMAP configured?"; reading null instead makes
 * the account look like it has no password, so the UI asks for it to be re-entered.
 * Use MailSetting::unreadableSecrets() to tell "unreadable" apart from "never set".
 */
class SafeEncrypted implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            Log::warning('Stored secret could not be decrypted (APP_KEY changed?); treating it as empty.', [
                'model' => $model::class, 'id' => $model->getKey(), 'column' => $key,
            ]);

            return null;
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null || $value === '' ? null : Crypt::encryptString((string) $value);
    }

    /** Whether a raw stored value exists but cannot be decrypted with the current key. */
    public static function isUnreadable(?string $raw): bool
    {
        if ($raw === null || $raw === '') {
            return false;
        }

        try {
            Crypt::decryptString($raw);

            return false;
        } catch (DecryptException) {
            return true;
        }
    }
}
