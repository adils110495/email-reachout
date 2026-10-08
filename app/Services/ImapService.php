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

        // Saved Mail Settings first, .env as fallback.
        ['host' => $host, 'port' => $port, 'protocol' => $protocol,
         'username' => $username, 'password' => $password, 'folder' => $folder] = app(MailConfigService::class)->imap();

        if (! $host) {
            Log::warning('ImapService: no IMAP host configured, skipping Sent-folder copy.');
            return;
        }

        $mailbox = $this->mailboxString($host, $port, $protocol, $folder);

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
     * Open and close a connection to verify the credentials.
     *
     * @return string|null  null on success, otherwise the error message
     */
    public function testConnection(string $host, int $port, string $protocol, string $username, string $password, string $folder): ?string
    {
        if (! extension_loaded('imap')) {
            return 'PHP imap extension is not loaded.';
        }

        $mbox = @imap_open($this->mailboxString($host, $port, $protocol, $folder), $username, $password, OP_HALFOPEN, 1);

        if (! $mbox) {
            return imap_last_error() ?: 'Could not connect to the IMAP server.';
        }

        imap_close($mbox);
        imap_errors();

        return null;
    }

    /**
     * Header summary of INBOX messages received since $since (newest 500 max).
     *
     * @return array<int, array{from:string,in_reply_to:string,references:string,date:\Illuminate\Support\Carbon}>
     * @throws \RuntimeException when the mailbox cannot be reached
     */
    public function recentInboxMessages(\DateTimeInterface $since): array
    {
        if (! extension_loaded('imap')) {
            throw new \RuntimeException('PHP imap extension is not loaded.');
        }

        ['host' => $host, 'port' => $port, 'protocol' => $protocol, 'username' => $username, 'password' => $password] = app(MailConfigService::class)->imap();

        if (! $host) {
            throw new \RuntimeException('No IMAP settings configured (Settings > Mail Settings).');
        }

        $mbox = @imap_open($this->mailboxString($host, $port, $protocol, 'INBOX'), (string) $username, (string) $password, OP_READONLY, 1);

        if (! $mbox) {
            throw new \RuntimeException(imap_last_error() ?: 'Could not connect to the IMAP server.');
        }

        $messages = [];
        $numbers  = imap_search($mbox, 'SINCE "' . $since->format('d-M-Y') . '"') ?: [];

        foreach (array_slice($numbers, -500) as $number) {
            $header = @imap_headerinfo($mbox, $number);
            if (! $header || empty($header->from[0])) {
                continue;
            }

            $messages[] = [
                'from'        => strtolower($header->from[0]->mailbox . '@' . ($header->from[0]->host ?? '')),
                'in_reply_to' => (string) ($header->in_reply_to ?? ''),
                'references'  => (string) ($header->references ?? ''),
                'date'        => \Illuminate\Support\Carbon::parse($header->date ?? 'now'),
            ];
        }

        imap_close($mbox);
        imap_errors();

        return $messages;
    }

    private function mailboxString(string $host, int $port, string $protocol, string $folder): string
    {
        $flags = $protocol === 'notls' ? '/notls' : '/' . $protocol;

        return '{' . $host . ':' . $port . '/imap' . $flags . '/novalidate-cert}' . $folder;
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
