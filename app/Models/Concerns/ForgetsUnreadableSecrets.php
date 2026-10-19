<?php

namespace App\Models\Concerns;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;

/**
 * For models with "encrypted" casts (mail password, API tokens).
 *
 * A secret saved under another APP_KEY - a database copied from another
 * server, a regenerated key - cannot be decrypted here ("The MAC is
 * invalid."). Eloquent decrypts the old value on save to see whether it
 * changed, so even typing a new secret in would crash. Before a save, such an
 * old value is treated as empty instead, and the new one is written.
 */
trait ForgetsUnreadableSecrets
{
    public static function bootForgetsUnreadableSecrets(): void
    {
        static::saving(fn (Model $model) => $model->forgetUnreadableOriginals());
    }

    protected function forgetUnreadableOriginals(): void
    {
        foreach ($this->getCasts() as $key => $cast) {
            // Only a secret being replaced: an untouched one compares equal
            // without being decrypted, and must stay as it is.
            if (! str_starts_with((string) $cast, 'encrypted') || ! isset($this->original[$key])
                || ($this->attributes[$key] ?? null) === $this->original[$key]) {
                continue;
            }

            try {
                static::currentEncrypter()->decrypt($this->original[$key], false);
            } catch (DecryptException) {
                $this->original[$key] = null;
            }
        }
    }
}
