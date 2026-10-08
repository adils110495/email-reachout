<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Verifies a single email address without a paid third-party API.
 *
 * The checks run cheapest-first and short-circuit as soon as the address is
 * provably undeliverable, so a malformed address never costs a DNS lookup:
 *
 *   1. syntax      RFC-ish validation + length limits
 *   2. domain      the domain resolves at all (MX, then A/AAAA fallback)
 *   3. mx          the domain publishes mail exchangers
 *   4. disposable  throwaway-inbox providers
 *   5. role        info@ / support@ / admin@ - real, but not a person
 *   6. free        gmail/yahoo/... - deliverable, weaker as a B2B lead
 *   7. smtp        optional RCPT TO probe + catch-all detection
 *
 * The SMTP probe is OFF by default: most hosting providers (and Docker on
 * consumer ISPs) block outbound port 25, which would make every address look
 * "unknown". Turn it on with VERIFY_SMTP_PROBE=true where port 25 is open.
 *
 * DNS answers are cached per domain, so verifying 500 addresses at one company
 * costs one lookup rather than 500.
 */
class EmailVerifierService
{
    /** Throwaway-inbox providers. An address here is real but worthless as a lead. */
    private const DISPOSABLE_DOMAINS = [
        '10minutemail.com', '20minutemail.com', 'anonbox.net', 'dispostable.com',
        'discard.email', 'emailondeck.com', 'fakeinbox.com', 'getairmail.com',
        'getnada.com', 'guerrillamail.com', 'guerrillamail.net', 'guerrillamail.org',
        'inboxbear.com', 'jetable.org', 'mail-temporaire.fr', 'mailcatch.com',
        'maildrop.cc', 'mailinator.com', 'mailnesia.com', 'mailsac.com',
        'mintemail.com', 'moakt.com', 'mohmal.com', 'mytemp.email',
        'nowmymail.com', 'sharklasers.com', 'spam4.me', 'spamgourmet.com',
        'tempinbox.com', 'tempmail.net', 'temp-mail.org', 'tempmailaddress.com',
        'throwawaymail.com', 'trashmail.com', 'trashmail.de', 'yopmail.com',
        'yopmail.fr', 'yopmail.net', 'mailtemp.net', 'burnermail.io',
        'trbvm.com', 'tmpmail.org', 'byom.de', 'einrot.com', 'fakemailgenerator.com',
    ];

    /** Consumer mailbox providers - deliverable, but a weaker B2B signal. */
    private const FREE_DOMAINS = [
        'gmail.com', 'googlemail.com', 'yahoo.com', 'yahoo.co.uk', 'yahoo.in',
        'hotmail.com', 'hotmail.co.uk', 'outlook.com', 'live.com', 'msn.com',
        'aol.com', 'icloud.com', 'me.com', 'mac.com', 'gmx.com', 'gmx.de',
        'gmx.net', 'mail.com', 'mail.ru', 'yandex.com', 'yandex.ru',
        'zoho.com', 'protonmail.com', 'proton.me', 'tutanota.com',
        'rediffmail.com', 'inbox.com', 'fastmail.com', 'hushmail.com',
    ];

    /** Shared/departmental mailboxes - deliverable, but nobody owns them. */
    private const ROLE_PREFIXES = [
        'admin', 'administrator', 'billing', 'career', 'careers', 'compliance',
        'contact', 'enquiries', 'enquiry', 'feedback', 'finance', 'help', 'hello',
        'hiring', 'hr', 'info', 'inquiries', 'inquiry', 'job', 'jobs', 'legal',
        'mail', 'marketing', 'media', 'newsletter', 'office', 'orders',
        'postmaster', 'press', 'privacy', 'recruiting', 'recruitment', 'sales',
        'security', 'service', 'support', 'talent', 'team', 'webmaster', 'work',
        'noreply', 'no-reply', 'donotreply', 'do-not-reply', 'bounce', 'abuse',
    ];

    /**
     * Verify one address.
     *
     * @return array{
     *     email:string, domain:string, status:string, score:int,
     *     reason:string, checks:array<string,mixed>
     * }
     */
    public function verify(string $email): array
    {
        $email  = strtolower(trim($email));
        $domain = (string) substr((string) strrchr($email, '@'), 1);

        $checks = [
            'syntax'     => false,
            'domain'     => false,
            'mx'         => false,
            'smtp'       => null,   // null = not probed
            'catch_all'  => null,   // null = not probed
            'disposable' => false,
            'role'       => false,
            'free'       => false,
            'mx_hosts'   => [],
        ];

        // 1. Syntax
        if (! $this->hasValidSyntax($email)) {
            return $this->result($email, $domain, 'invalid', 0, 'Malformed email address.', $checks);
        }
        $checks['syntax'] = true;

        // 2/3. DNS: mail exchangers, falling back to an A/AAAA record
        $dns = $this->resolveDomain($domain);
        $checks['domain']   = $dns['resolves'];
        $checks['mx']       = $dns['has_mx'];
        $checks['mx_hosts'] = $dns['hosts'];

        if (! $dns['resolves']) {
            return $this->result($email, $domain, 'invalid', 0, "Domain \"{$domain}\" does not exist.", $checks);
        }

        if (! $dns['has_mx'] && empty($dns['hosts'])) {
            return $this->result($email, $domain, 'invalid', 10, "Domain \"{$domain}\" accepts no mail (no MX record).", $checks);
        }

        // 4/5/6. Reputation of the domain and the local part
        $localPart = strstr($email, '@', true) ?: '';

        $checks['disposable'] = $this->isDisposable($domain);
        $checks['free']       = in_array($domain, self::FREE_DOMAINS, true);
        $checks['role']       = $this->isRoleAddress($localPart);

        if ($checks['disposable']) {
            return $this->result($email, $domain, 'risky', 20, 'Disposable / throwaway inbox provider.', $checks);
        }

        // 7. Optional SMTP conversation
        if ($this->smtpEnabled() && ! empty($dns['hosts'])) {
            $smtp = $this->probeSmtp($domain, $email, $dns['hosts']);

            $checks['smtp']      = $smtp['deliverable'];
            $checks['catch_all'] = $smtp['catch_all'];

            if ($smtp['deliverable'] === false) {
                return $this->result($email, $domain, 'invalid', 5, $smtp['message'] ?: 'Mail server rejected this mailbox.', $checks);
            }

            if ($smtp['catch_all'] === true) {
                return $this->result($email, $domain, 'risky', 55, 'Domain accepts all addresses (catch-all) - delivery cannot be confirmed.', $checks);
            }

            if ($smtp['deliverable'] === null) {
                // Port 25 blocked, greylisting, or the server simply would not talk.
                return $this->result($email, $domain, 'unknown', 45, $smtp['message'] ?: 'Mail server did not answer the probe.', $checks);
            }
        }

        // Verdict
        if ($checks['role']) {
            return $this->result(
                $email, $domain, 'risky', 60,
                'Role-based address (shared mailbox, not an individual).',
                $checks,
            );
        }

        $probed = $checks['smtp'] === true;
        $score  = $probed ? 95 : 80;
        if ($checks['free']) {
            $score -= 10;
        }

        $reason = $probed
            ? 'Mailbox accepted by the mail server.'
            : 'Valid syntax and the domain accepts mail. Mailbox not probed (SMTP probe disabled).';

        return $this->result($email, $domain, 'valid', $score, $reason, $checks);
    }

    /**
     * Verify a list of addresses in one pass. Duplicates are collapsed, and the
     * per-domain DNS cache means one lookup per distinct domain.
     *
     * @param  string[]  $emails
     * @return array<string, array<string,mixed>>  keyed by email
     */
    public function verifyMany(array $emails): array
    {
        $results = [];

        foreach (array_unique(array_map('strtolower', array_map('trim', $emails))) as $email) {
            if ($email === '') {
                continue;
            }
            $results[$email] = $this->verify($email);
        }

        return $results;
    }

    /** Split a pasted blob (commas, semicolons, whitespace or newlines) into addresses. */
    public function parseList(string $blob): array
    {
        $parts = preg_split('/[\s,;]+/', $blob, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_map(
            static fn ($p) => strtolower(trim($p)),
            $parts,
        )));
    }

    // Individual checks

    private function hasValidSyntax(string $email): bool
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // RFC 5321: 64 octets local part, 254 total.
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return $local !== ''
            && $domain !== ''
            && strlen($local) <= 64
            && strlen($email) <= 254
            && str_contains($domain, '.')
            && ! str_contains($email, '..');
    }

    /**
     * Look up the domain's mail exchangers, falling back to an A/AAAA record
     * (RFC 5321 5.1 - a host with an address record but no MX still accepts
     * mail at that address). Cached per domain.
     *
     * @return array{resolves:bool, has_mx:bool, hosts:string[]}
     */
    private function resolveDomain(string $domain): array
    {
        $ttl = (int) config('services.email_verifier.cache_ttl', 86400);

        return Cache::remember('verify:dns:'.$domain, $ttl, function () use ($domain) {
            $hosts   = [];
            $weights = [];
            $hasMx   = false;

            try {
                if (@getmxrr($domain, $hosts, $weights) && ! empty($hosts)) {
                    $hasMx = true;
                    // Lowest preference number wins - try that server first.
                    array_multisort($weights, SORT_ASC, $hosts);
                }
            } catch (\Throwable $e) {
                Log::debug('EmailVerifierService: MX lookup failed', ['domain' => $domain, 'error' => $e->getMessage()]);
            }

            $resolves = $hasMx;

            if (! $hasMx) {
                try {
                    if (@checkdnsrr($domain, 'A') || @checkdnsrr($domain, 'AAAA')) {
                        $resolves = true;
                        $hosts    = [$domain];
                    }
                } catch (\Throwable $e) {
                    Log::debug('EmailVerifierService: A lookup failed', ['domain' => $domain, 'error' => $e->getMessage()]);
                }
            }

            return [
                'resolves' => $resolves,
                'has_mx'   => $hasMx,
                'hosts'    => array_values(array_slice($hosts, 0, 3)),
            ];
        });
    }

    private function isDisposable(string $domain): bool
    {
        if (in_array($domain, self::DISPOSABLE_DOMAINS, true)) {
            return true;
        }

        // Catch subdomains of a known provider (e.g. foo.mailinator.com).
        foreach (self::DISPOSABLE_DOMAINS as $disposable) {
            if (str_ends_with($domain, '.'.$disposable)) {
                return true;
            }
        }

        return false;
    }

    private function isRoleAddress(string $localPart): bool
    {
        // Compare on the bare word so "sales-uk" and "info.team" still match,
        // while "salesforce" (a plausible surname-like handle) does not.
        $bare = preg_split('/[.\-_+]/', $localPart)[0] ?? $localPart;

        return in_array($localPart, self::ROLE_PREFIXES, true)
            || in_array($bare, self::ROLE_PREFIXES, true);
    }

    private function smtpEnabled(): bool
    {
        return (bool) config('services.email_verifier.smtp', false);
    }

    /**
     * Hold an SMTP conversation with the domain's mail server and ask whether it
     * would accept mail for this address (RCPT TO), then repeat with a random
     * address to detect a catch-all.
     *
     * @param  string[]  $hosts
     * @return array{deliverable:bool|null, catch_all:bool|null, message:string}
     */
    private function probeSmtp(string $domain, string $email, array $hosts): array
    {
        $timeout = (int) config('services.email_verifier.smtp_timeout', 8);
        $from    = (string) config('services.email_verifier.smtp_from', 'verify@'.$domain);
        $helo    = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';

        foreach ($hosts as $host) {
            $socket = @fsockopen($host, 25, $errNo, $errStr, $timeout);

            if (! $socket) {
                Log::debug('EmailVerifierService: SMTP connect failed', [
                    'host' => $host, 'error' => $errStr ?: $errNo,
                ]);
                continue;
            }

            stream_set_timeout($socket, $timeout);

            try {
                if ($this->smtpCode($socket) !== 220) {
                    continue;
                }

                $this->smtpCommand($socket, "EHLO {$helo}");
                $this->smtpCommand($socket, "MAIL FROM:<{$from}>");

                $rcptCode = $this->smtpCommand($socket, "RCPT TO:<{$email}>");

                // 550/551/553/554 - the server positively refuses this mailbox.
                if (in_array($rcptCode, [550, 551, 553, 554], true)) {
                    return ['deliverable' => false, 'catch_all' => null, 'message' => 'Mail server rejected this mailbox.'];
                }

                if ($rcptCode !== 250 && $rcptCode !== 251) {
                    // 450/451/452 greylisting, 421 throttling, anything unexpected.
                    return ['deliverable' => null, 'catch_all' => null, 'message' => "Mail server replied {$rcptCode} - result inconclusive."];
                }

                // Accepted. Now ask for an address that cannot exist: if that is
                // accepted too, the domain accepts everything and the first
                // "yes" told us nothing.
                $random   = 'no-such-user-'.bin2hex(random_bytes(6)).'@'.$domain;
                $probe    = $this->smtpCommand($socket, "RCPT TO:<{$random}>");
                $catchAll = ($probe === 250 || $probe === 251);

                return ['deliverable' => true, 'catch_all' => $catchAll, 'message' => 'Mailbox accepted by the mail server.'];

            } catch (\Throwable $e) {
                Log::debug('EmailVerifierService: SMTP probe error', ['host' => $host, 'error' => $e->getMessage()]);
            } finally {
                @fwrite($socket, "QUIT\r\n");
                @fclose($socket);
            }
        }

        return ['deliverable' => null, 'catch_all' => null, 'message' => 'Could not reach any mail server for this domain (port 25 may be blocked).'];
    }

    /** @param resource $socket */
    private function smtpCommand($socket, string $command): int
    {
        @fwrite($socket, $command."\r\n");

        return $this->smtpCode($socket);
    }

    /**
     * Read a (possibly multi-line) SMTP reply and return its status code.
     * A multi-line reply has a "-" after the code on every line but the last.
     *
     * @param  resource  $socket
     */
    private function smtpCode($socket): int
    {
        $code = 0;

        while (($line = @fgets($socket, 515)) !== false) {
            $code = (int) substr($line, 0, 3);

            if (! isset($line[3]) || $line[3] !== '-') {
                break;
            }

            $meta = stream_get_meta_data($socket);
            if (! empty($meta['timed_out'])) {
                break;
            }
        }

        return $code;
    }

    /** @param array<string,mixed> $checks */
    private function result(string $email, string $domain, string $status, int $score, string $reason, array $checks): array
    {
        return [
            'email'  => $email,
            'domain' => $domain,
            'status' => $status,
            'score'  => max(0, min(100, $score)),
            'reason' => $reason,
            'checks' => $checks,
        ];
    }
}
