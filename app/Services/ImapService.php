<?php

namespace App\Services;

use App\Models\MailSetting;
use App\Sequencer\Exceptions\ImapException;
use App\Sequencer\Imap\InboundEmail;
use App\Sequencer\Imap\InboxBatch;
use App\Sequencer\Mail\ConnectionResult;
use App\Sequencer\Services\BounceParser;
use App\Sequencer\Support\MessageHeaders;
use Illuminate\Support\Facades\Log;
use IMAP\Connection;
use Throwable;

/**
 * Everything IMAP: copying sent mail to the Sent folder, testing credentials, and
 * reading new inbox messages for reply / bounce detection.
 *
 * Uses PHP's imap extension (bundled in the Docker image; PECL on PHP 8.4+).
 * Inbox reading is read-only: nothing is marked seen, moved or deleted.
 */
class ImapService
{
    private const MAX_RAW_BYTES = 262144;   // 256 KB of a bounce notification is plenty

    /**
     * Copy a sent email to the account's IMAP Sent folder (the default account when none is given).
     *
     * @param  array  $attachments  [['path' => '/abs/path', 'name' => 'original.pdf'], ...]
     */
    public function copyToSentFolder(
        string $to,
        string $subject,
        string $htmlBody,
        string $fromName,
        string $fromEmail,
        array  $attachments = [],
        ?MailSetting $account = null,
        ?string $messageId = null,
    ): void {
        if (! extension_loaded('imap')) {
            Log::error('ImapService: PHP imap extension is not loaded. Rebuild Docker image.');
            return;
        }

        // Saved Mail Settings first, .env as fallback.
        ['host' => $host, 'port' => $port, 'protocol' => $protocol,
         'username' => $username, 'password' => $password, 'folder' => $folder] = app(MailConfigService::class)->imap($account);

        if (! $host) {
            Log::warning('ImapService: no IMAP host configured, skipping Sent-folder copy.');
            return;
        }

        $mailbox = $this->mailboxString($host, $port, $protocol, $folder);

        Log::debug('ImapService: Connecting to mailbox', ['mailbox' => $mailbox, 'username' => $username]);

        $mbox = @imap_open($mailbox, (string) $username, (string) $password, 0, 1);

        if (! $mbox) {
            Log::error('ImapService: IMAP connection failed', [
                'mailbox'    => $mailbox,
                'last_error' => imap_last_error(),
                'all_errors' => imap_errors(),
            ]);
            return;
        }

        $rawMessage = $this->buildRawMessage($fromName, $fromEmail, $to, $subject, $htmlBody, $attachments, $messageId);
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
            $error = imap_last_error() ?: 'Could not connect to the IMAP server.';
            imap_errors();

            return $error;
        }

        imap_close($mbox);
        imap_errors();

        return null;
    }

    /** Log in to an account's polled inbox (imap_folder) without reading anything. */
    public function testAccount(MailSetting $account): ConnectionResult
    {
        if (! $account->hasImap()) {
            return ConnectionResult::failure('IMAP host, username and password are not configured.');
        }

        $error = $this->testConnection(
            (string) $account->imap_host,
            (int) ($account->imap_port ?: 993),
            app(MailConfigService::class)->imapProtocol($account->imap_encryption),
            (string) $account->imap_username,
            (string) $account->imap_password,
            $account->imap_folder ?: 'INBOX',
        );

        return $error === null
            ? ConnectionResult::success('IMAP login succeeded and the folder is readable.')
            : ConnectionResult::failure(app(MailConfigService::class)->scrub($error, $account));
    }

    /**
     * Messages that arrived in the account's inbox after $afterUid (oldest first).
     *
     * On the first poll ($afterUid null, or UIDVALIDITY changed) only the last
     * $lookbackDays are read, never the whole mailbox.
     *
     * @throws ImapException
     */
    public function fetchNew(MailSetting $account, ?int $afterUid, ?int $knownUidValidity, int $lookbackDays, int $limit): InboxBatch
    {
        $conn = $this->openInbox($account);

        try {
            $mailbox = $this->accountMailbox($account);
            $status = @imap_status($conn, $mailbox, SA_UIDVALIDITY | SA_UIDNEXT);
            $validity = (int) ($status->uidvalidity ?? 0);
            $uidNext = (int) ($status->uidnext ?? 0);

            $firstPoll = $afterUid === null || ($knownUidValidity !== null && $knownUidValidity !== $validity);

            $uids = $firstPoll ? $this->uidsSince($conn, $lookbackDays) : $this->uidsAfter($conn, $afterUid);
            sort($uids);
            $truncated = count($uids) > $limit;

            $messages = [];
            foreach (array_slice($uids, 0, $limit) as $uid) {
                if ($message = $this->readMessage($conn, $uid)) {
                    $messages[] = $message;
                }
            }

            return new InboxBatch($validity, $messages, $uidNext, $truncated);
        } catch (ImapException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new ImapException(app(MailConfigService::class)->scrub($e->getMessage(), $account), 0, $e);
        } finally {
            @imap_close($conn);
            imap_errors();
            imap_alerts();
        }
    }

    // ── internals ──────────────────────────────────────────────────────────

    private function openInbox(MailSetting $account): Connection
    {
        if (! extension_loaded('imap')) {
            throw new ImapException('The PHP imap extension is not installed.');
        }

        if (! $account->hasImap()) {
            throw new ImapException('IMAP is not configured for this account.');
        }

        $timeout = (int) config('sequencer.imap.timeout', 20);
        foreach ([IMAP_OPENTIMEOUT, IMAP_READTIMEOUT, IMAP_WRITETIMEOUT, IMAP_CLOSETIMEOUT] as $type) {
            imap_timeout($type, $timeout);
        }

        $conn = @imap_open($this->accountMailbox($account), (string) $account->imap_username, (string) $account->imap_password, OP_READONLY, 1, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']);

        if (! $conn) {
            $error = imap_last_error() ?: 'Unable to connect to the IMAP server.';
            imap_errors();
            imap_alerts();

            throw new ImapException(app(MailConfigService::class)->scrub($error, $account));
        }

        return $conn;
    }

    private function accountMailbox(MailSetting $account): string
    {
        return $this->mailboxString(
            (string) $account->imap_host,
            (int) ($account->imap_port ?: 993),
            app(MailConfigService::class)->imapProtocol($account->imap_encryption),
            $account->imap_folder ?: 'INBOX',
        );
    }

    /** @return list<int> */
    private function uidsSince(Connection $conn, int $days): array
    {
        $found = @imap_search($conn, 'SINCE "'.now()->subDays(max(1, $days))->format('d-M-Y').'"', SE_UID);

        return $found ? array_map('intval', $found) : [];
    }

    /** @return list<int> */
    private function uidsAfter(Connection $conn, int $afterUid): array
    {
        // "N:*" always returns at least the newest message even when its UID < N, hence the filter.
        $overview = @imap_fetch_overview($conn, ($afterUid + 1).':*', FT_UID) ?: [];

        $uids = [];
        foreach ($overview as $item) {
            if ((int) $item->uid > $afterUid) {
                $uids[] = (int) $item->uid;
            }
        }

        return $uids;
    }

    private function readMessage(Connection $conn, int $uid): ?InboundEmail
    {
        $rawHeader = @imap_fetchheader($conn, $uid, FT_UID);
        if (! is_string($rawHeader) || $rawHeader === '') {
            return null;
        }

        $headers = MessageHeaders::parse($rawHeader);
        $from = MessageHeaders::address($headers['from'] ?? null);
        $subject = MessageHeaders::decode($headers['subject'] ?? '');

        // Only bounce candidates are downloaded in full; replies need headers alone.
        $raw = null;
        if (BounceParser::looksLikeBounce($headers, $from, $subject)) {
            $body = @imap_fetchbody($conn, $uid, '', FT_UID | FT_PEEK);
            $raw = is_string($body) ? $rawHeader."\r\n".substr($body, 0, self::MAX_RAW_BYTES) : $rawHeader;
        }

        return new InboundEmail(
            uid: $uid,
            messageId: MessageHeaders::messageIds($headers['message-id'] ?? null)[0] ?? null,
            inReplyTo: MessageHeaders::messageIds($headers['in-reply-to'] ?? null)[0] ?? null,
            references: MessageHeaders::messageIds($headers['references'] ?? null),
            fromEmail: $from,
            subject: $subject,
            date: MessageHeaders::date($headers['date'] ?? null),
            headers: $headers,
            rawSource: $raw,
        );
    }

    private function mailboxString(string $host, int $port, string $protocol, string $folder): string
    {
        $flags = $protocol === 'notls' ? '/notls' : '/' . $protocol;

        // Certificates are validated unless explicitly disabled (self-signed dev servers).
        $validate = config('sequencer.imap.validate_certificates', true) ? '' : '/novalidate-cert';

        return '{' . $host . ':' . $port . '/imap' . $flags . $validate . '}' . $folder;
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
        ?string $messageId = null,
    ): string {
        $date       = date('r');
        $fromHeader = $fromName ? "\"{$fromName}\" <{$fromEmail}>" : $fromEmail;
        $boundary   = '==Boundary_' . md5(uniqid('', true));
        $idHeader   = $messageId ? ["Message-ID: <{$messageId}>"] : [];

        if (empty($attachments)) {
            // Simple HTML-only message
            return implode("\r\n", [
                "Date: {$date}",
                "From: {$fromHeader}",
                "To: {$to}",
                "Subject: {$subject}",
                ...$idHeader,
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
            ...$idHeader,
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
