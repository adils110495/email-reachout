<?php

namespace App\Services;

use App\Models\MailSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Resolves the mail connection details used for sending.
 *
 * Settings saved under Settings > Mail Settings win; the .env values are only
 * a fallback for installs that have not saved anything yet.
 */
class MailConfigService
{
    /** Laravel's own "The MAC is invalid." says nothing about what to do. */
    public const UNREADABLE_PASSWORD = 'The saved SMTP password cannot be read on this server - it was saved with a different APP_KEY '
        . '(for example, the database came from another server). Enter the password again under Settings > Mail Settings.';

    /**
     * Why the saved SMTP setting cannot send, or null when it can (or when
     * nothing is saved and .env is used). Checked before a bulk send is queued.
     */
    public function smtpProblem(): ?string
    {
        return MailSetting::active(MailSetting::TYPE_SMTP)?->passwordUnreadable() ? self::UNREADABLE_PASSWORD : null;
    }

    /**
     * Point Laravel's smtp mailer at the saved SMTP setting.
     * No-op when nothing is saved, so the .env configuration keeps working.
     *
     * @throws RuntimeException when the saved password cannot be decrypted
     */
    public function applySmtp(): void
    {
        $smtp = MailSetting::active(MailSetting::TYPE_SMTP);

        if (! $smtp) {
            return;
        }

        if ($smtp->passwordUnreadable()) {
            throw new RuntimeException(self::UNREADABLE_PASSWORD);
        }

        config([
            'mail.default'                    => 'smtp',
            'mail.mailers.smtp.host'          => $smtp->host,
            'mail.mailers.smtp.port'          => $smtp->port ?: 587,
            'mail.mailers.smtp.encryption'    => in_array($smtp->encryption, ['tls', 'ssl'], true) ? $smtp->encryption : null,
            'mail.mailers.smtp.username'      => $smtp->username,
            'mail.mailers.smtp.password'      => $smtp->readablePassword(),
            'mail.mailers.smtp.url'           => null,
            'mail.from.address'               => $smtp->from_address ?: $smtp->username,
            'mail.from.name'                  => $smtp->from_name ?: config('mail.from.name'),
        ]);

        // Drop any mailer instance built earlier with the old config.
        Mail::purge('smtp');
    }

    public function fromAddress(): ?string
    {
        return MailSetting::active(MailSetting::TYPE_SMTP)?->from_address
            ?: MailSetting::active(MailSetting::TYPE_SMTP)?->username
            ?: env('MAIL_FROM_ADDRESS');
    }

    public function fromName(string $default): string
    {
        return MailSetting::active(MailSetting::TYPE_SMTP)?->from_name
            ?: env('MAIL_FROM_NAME', $default);
    }

    /**
     * IMAP connection details: saved setting first, .env as fallback.
     *
     * @return array{host:?string,port:int,protocol:string,username:?string,password:?string,folder:string}
     */
    public function imap(): array
    {
        $imap = MailSetting::active(MailSetting::TYPE_IMAP);

        if ($imap) {
            // An unreadable password makes the login fail and get logged, instead
            // of throwing - the Sent-folder copy runs after the email is already out.
            if ($imap->passwordUnreadable()) {
                Log::warning('MailConfigService: the saved IMAP password cannot be decrypted (different APP_KEY) - enter it again under Settings > Mail Settings', ['setting_id' => $imap->id]);
            }

            return [
                'host'     => $imap->host,
                'port'     => (int) ($imap->port ?: 993),
                'protocol' => $imap->encryption ?: 'ssl',
                'username' => $imap->username,
                'password' => $imap->readablePassword(),
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
}
