<?php

namespace App\Services;

use App\Models\MailSetting;
use Illuminate\Support\Facades\Mail;

/**
 * Resolves the mail connection details used for sending.
 *
 * Settings saved under Settings > Mail Settings win; the .env values are only
 * a fallback for installs that have not saved anything yet.
 */
class MailConfigService
{
    /**
     * Point Laravel's smtp mailer at the saved SMTP setting.
     * No-op when nothing is saved, so the .env configuration keeps working.
     */
    public function applySmtp(): void
    {
        $smtp = MailSetting::active(MailSetting::TYPE_SMTP);

        if (! $smtp) {
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
            return [
                'host'     => $imap->host,
                'port'     => (int) ($imap->port ?: 993),
                'protocol' => $imap->encryption ?: 'ssl',
                'username' => $imap->username,
                'password' => $imap->password,
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
