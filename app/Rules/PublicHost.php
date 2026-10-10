<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects hosts that resolve to private / loopback / link-local addresses so the
 * "test connection" and sending features cannot be used to probe the internal network
 * (SSRF). Relaxed in local/testing, or with SEQUENCER_ALLOW_PRIVATE_HOSTS=true.
 */
class PublicHost implements ValidationRule
{
    public static function allowed(): bool
    {
        return (bool) config('sequencer.allow_private_hosts', app()->environment('local', 'testing'));
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        if (! preg_match('/^[A-Za-z0-9.\-]+$/', $value) && ! filter_var($value, FILTER_VALIDATE_IP)) {
            $fail('The :attribute must be a valid host name.');

            return;
        }

        if (self::allowed()) {
            return;
        }

        $ips = filter_var($value, FILTER_VALIDATE_IP) ? [$value] : (gethostbynamel($value) ?: []);

        if ($ips === []) {
            $fail('The :attribute could not be resolved.');

            return;
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $fail('The :attribute must point to a public mail server.');

                return;
            }
        }
    }
}
