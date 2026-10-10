<?php

namespace App\Services;

use App\Models\MailSetting;
use App\Sequencer\Enums\Encryption;
use App\Sequencer\Mail\ConnectionResult;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Throwable;

/**
 * Resolves the mail connection details used for sending.
 *
 * Accounts saved under Settings > Mail Settings win; the .env values are only a
 * fallback for installs that have not saved anything yet. The default account
 * serves the Leads compose window; sequences send from any account via transportFor().
 */
class MailConfigService
{
    /**
     * Point Laravel's smtp mailer at an account (the default one when none is given).
     * No-op when nothing is saved, so the .env configuration keeps working.
     */
    public function applySmtp(?MailSetting $account = null): void
    {
        $smtp = $account ?? MailSetting::defaultAccount();

        if (! $smtp || ! $smtp->hasSmtp()) {
            return;
        }

        config([
            'mail.default'                    => 'smtp',
            'mail.mailers.smtp.host'          => $smtp->host,
            'mail.mailers.smtp.port'          => $smtp->port ?: 587,
            'mail.mailers.smtp.encryption'    => in_array($smtp->encryption, ['tls', 'ssl'], true) ? $smtp->encryption : null,
            'mail.mailers.smtp.username'      => $smtp->username,
            'mail.mailers.smtp.password'      => $smtp->password,
            'mail.mailers.smtp.url'           => null,
            'mail.from.address'               => $smtp->senderEmail(),
            'mail.from.name'                  => $smtp->from_name ?: config('mail.from.name'),
        ]);

        // Drop any mailer instance built earlier with the old config.
        Mail::purge('smtp');
    }

    public function fromAddress(): ?string
    {
        return MailSetting::defaultAccount()?->senderEmail() ?: env('MAIL_FROM_ADDRESS');
    }

    public function fromName(string $default): string
    {
        return MailSetting::defaultAccount()?->from_name
            ?: env('MAIL_FROM_NAME', $default);
    }

    /**
     * IMAP connection details for the Sent-folder copy: an account (default one when
     * none is given) first, .env as fallback.
     *
     * @return array{host:?string,port:int,protocol:string,username:?string,password:?string,folder:string}
     */
    public function imap(?MailSetting $account = null): array
    {
        $imap = $account ?? MailSetting::defaultAccount();

        if ($imap && $imap->hasImap()) {
            return [
                'host'     => $imap->imap_host,
                'port'     => (int) ($imap->imap_port ?: 993),
                'protocol' => $this->imapProtocol($imap->imap_encryption),
                'username' => $imap->imap_username,
                'password' => $imap->imap_password,
                'folder'   => $imap->folder ?: 'INBOX.Sent',
            ];
        }

        return [
            'host'     => env('IMAP_HOST'),
            'port'     => (int) env('IMAP_PORT', 993),
            'protocol' => env('IMAP_PROTOCOL', 'ssl'),
            'username' => env('IMAP_USERNAME'),
            'password' => env('IMAP_PASSWORD'),
            'folder'   => env('IMAP_FOLDER', 'INBOX.Sent'),
        ];
    }

    /** Map the stored IMAP encryption (ssl|tls|none, legacy notls) onto ImapService's protocol flag. */
    public function imapProtocol(?string $encryption): string
    {
        return match ($encryption) {
            'tls' => 'tls',
            'none', 'notls' => 'notls',
            default => 'ssl',
        };
    }

    /**
     * A ready-to-use SMTP transport for one account: per-account credentials, TLS
     * mode, local EHLO domain and a socket timeout. Used by the sequencer's
     * SmtpEmailProvider and by the Mail Settings "Test connection" button.
     */
    public function transportFor(MailSetting $account, ?float $timeout = null): EsmtpTransport
    {
        $mode = Encryption::tryFrom((string) $account->encryption) ?? Encryption::Tls;

        // ssl = implicit TLS (465); tls = STARTTLS (587), required; none = plain
        $transport = new EsmtpTransport((string) $account->host, (int) ($account->port ?: 587), $mode === Encryption::Ssl);

        if ($mode === Encryption::None) {
            $transport->setAutoTls(false);
        } elseif ($mode === Encryption::Tls) {
            $transport->setRequireTls(true);
        }

        if (filled($account->username)) {
            $transport->setUsername((string) $account->username);
            $transport->setPassword((string) $account->password);
        }

        $domain = substr(strrchr($account->senderEmail(), '@') ?: '@localhost', 1);
        $transport->setLocalDomain($domain ?: 'localhost');

        $stream = $transport->getStream();
        if ($stream instanceof SocketStream) {
            $stream->setTimeout($timeout ?? (float) config('sequencer.send.smtp_timeout', 30));
        }

        return $transport;
    }

    /** Connect + authenticate over SMTP without sending anything. */
    public function testSmtp(MailSetting $account): ConnectionResult
    {
        if (! $account->hasSmtp()) {
            return ConnectionResult::failure('SMTP host is not configured.');
        }

        $transport = $this->transportFor($account, 15.0);

        try {
            $transport->start();

            return ConnectionResult::success('SMTP connection and authentication succeeded.');
        } catch (Throwable $e) {
            return ConnectionResult::failure($this->scrub($e->getMessage(), $account));
        } finally {
            try {
                $transport->stop();
            } catch (Throwable) {
            }
        }
    }

    /**
     * Test SMTP and (when configured) IMAP, and remember the outcome on a saved account.
     *
     * @return array{smtp: ConnectionResult, imap: ?ConnectionResult}
     */
    public function testAccount(MailSetting $account, bool $remember = true): array
    {
        $smtp = $this->testSmtp($account);
        $imap = $account->hasImap() ? app(ImapService::class)->testAccount($account) : null;

        if ($remember && $account->exists) {
            $account->forceFill([
                'smtp_ok' => $account->hasSmtp() ? $smtp->ok : null,
                'smtp_tested_at' => now(),
                'imap_ok' => $imap?->ok,
                'imap_tested_at' => $imap ? now() : $account->imap_tested_at,
                'imap_last_error' => $imap && ! $imap->ok ? $imap->message : $account->imap_last_error,
            ])->save();
        }

        return ['smtp' => $smtp, 'imap' => $imap];
    }

    /** Never let credentials reach the UI, logs or the database. */
    public function scrub(string $message, MailSetting $account): string
    {
        foreach ([$account->password, $account->username, $account->imap_password] as $secret) {
            if (filled($secret)) {
                $message = str_replace((string) $secret, '***', $message);
            }
        }

        return mb_substr(trim(preg_replace('/\s+/', ' ', $message) ?? $message), 0, 500);
    }
}
