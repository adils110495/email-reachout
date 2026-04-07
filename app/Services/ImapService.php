<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class ImapService
{
    /**
     * Copy a sent email to the configured IMAP Sent folder.
     */
    /**
     * @param array $attachments  [['path' => '/abs/path', 'name' => 'original.pdf'], ...]
     */
    public function copyToSentFolder(
        string $to,
        string $subject,
        string $htmlBody,
        string $fromName,
        string $fromEmail,
        array  $attachments = [],
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

        $rawMessage = $this->buildRawMessage($fromName, $fromEmail, $to, $subject, $htmlBody, $attachments);
        $appended   = imap_append($mbox, $mailbox, $rawMessage, '\\Seen');

        if ($appended) {
            Log::info('ImapService: Email copied to Sent folder', [
                'to'          => $to,
                'subject'     => $subject,
                'folder'      => $folder,
                'attachments' => count($attachments),
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
     * Supports HTML body + multiple file attachments.
     */
    private function buildRawMessage(
        string $fromName,
        string $fromEmail,
        string $to,
        string $subject,
        string $htmlBody,
        array  $attachments = [],
    ): string {
        $date       = date('r');
        $fromHeader = $fromName ? "\"{$fromName}\" <{$fromEmail}>" : $fromEmail;
        $boundary   = '==Boundary_' . md5(uniqid('', true));

        if (empty($attachments)) {
            // Simple HTML-only message
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

        // Mixed message with attachments
        $lines = [
            "Date: {$date}",
            "From: {$fromHeader}",
            "To: {$to}",
            "Subject: {$subject}",
            "MIME-Version: 1.0",
            "Content-Type: multipart/mixed; boundary=\"{$boundary}\"",
            "",
            "--{$boundary}",
            "Content-Type: text/html; charset=UTF-8",
            "Content-Transfer-Encoding: quoted-printable",
            "",
            quoted_printable_encode($htmlBody),
        ];

        foreach ($attachments as $att) {
            $path = $att['path'];
            if (! file_exists($path)) continue;

            $filename = $att['name']; // original filename from user
            $mime     = mime_content_type($path) ?: 'application/octet-stream';
            $encoded  = base64_encode(file_get_contents($path));

            $lines[] = "--{$boundary}";
            $lines[] = "Content-Type: {$mime}; name=\"{$filename}\"";
            $lines[] = "Content-Transfer-Encoding: base64";
            $lines[] = "Content-Disposition: attachment; filename=\"{$filename}\"";
            $lines[] = "";
            // Split into 76-char lines per RFC 2045
            foreach (str_split($encoded, 76) as $chunk) {
                $lines[] = $chunk;
            }
        }

        $lines[] = "--{$boundary}--";

        return implode("\r\n", $lines);
    }
}
