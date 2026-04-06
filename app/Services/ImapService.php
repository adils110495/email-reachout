<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class ImapService
{
    /**
     * Copy a sent email to the configured IMAP Sent folder.
     */
    public function copyToSentFolder(
        string $to,
        string $subject,
        string $htmlBody,
        string $fromName,
        string $fromEmail,
    ): void {
        if (! extension_loaded('imap')) {
            Log::error('ImapService: PHP imap extension is not loaded. Rebuild Docker image.');
            return;
        }

        $host     = env('IMAP_HOST');
        $username = env('IMAP_USERNAME');
        $password = env('IMAP_PASSWORD');
        $folder   = env('IMAP_FOLDER', 'INBOX.Sent');
        $port     = (int) env('IMAP_PORT', 993);
        $protocol = env('IMAP_PROTOCOL', 'ssl');

        $mailbox = '{' . $host . ':' . $port . '/imap/' . $protocol . '/novalidate-cert}' . $folder;

        Log::debug('ImapService: Connecting to mailbox', ['mailbox' => $mailbox, 'username' => $username]);

        $mbox = imap_open($mailbox, $username, $password, 0, 1);

        if (! $mbox) {
            Log::error('ImapService: IMAP connection failed', [
                'mailbox'    => $mailbox,
                'last_error' => imap_last_error(),
                'all_errors' => imap_errors(),
            ]);
            return;
        }

        $rawMessage = $this->buildRawMessage($fromName, $fromEmail, $to, $subject, $htmlBody);
        $appended   = imap_append($mbox, $mailbox, $rawMessage, '\\Seen');

        if ($appended) {
            Log::info('ImapService: Email copied to Sent folder', [
                'to'      => $to,
                'subject' => $subject,
                'folder'  => $folder,
            ]);
        } else {
            Log::error('ImapService: imap_append failed', [
                'last_error' => imap_last_error(),
                'all_errors' => imap_errors(),
            ]);
        }

        imap_close($mbox);
    }

    /**
     * Build a minimal RFC 2822 raw message string suitable for imap_append().
     */
    private function buildRawMessage(
        string $fromName,
        string $fromEmail,
        string $to,
        string $subject,
        string $htmlBody,
    ): string {
        $date       = date('r');
        $boundary   = '==Boundary_' . md5(uniqid('', true));
        $fromHeader = $fromName ? "\"{$fromName}\" <{$fromEmail}>" : $fromEmail;

        return implode("\r\n", [
            "Date: {$date}",
            "From: {$fromHeader}",
            "To: {$to}",
            "Subject: {$subject}",
            "MIME-Version: 1.0",
            "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
            "",
            "--{$boundary}",
            "Content-Type: text/html; charset=UTF-8",
            "Content-Transfer-Encoding: quoted-printable",
            "",
            quoted_printable_encode($htmlBody),
            "--{$boundary}--",
        ]);
    }
}
