<?php

namespace App\Sequencer\Services;

use App\Sequencer\Imap\InboundEmail;
use App\Sequencer\Support\MessageHeaders;

/**
 * Recognises delivery-status notifications (bounces) and extracts the facts
 * the sequencer needs. Pure functions: no I/O, fully unit-testable.
 */
class BounceParser
{
    private const DAEMONS = ['mailer-daemon', 'postmaster', 'mail-daemon', 'mailerdaemon', 'mail.delivery.subsystem'];

    private const SUBJECT_HINTS = [
        'undelivered mail returned to sender', 'delivery status notification', 'mail delivery failed',
        'returned mail', 'failure notice', 'undeliverable', 'delivery failure', 'mail delivery system',
        'could not be delivered', 'message not delivered',
    ];

    /** Phrases that, with a 5xx reply, mean the mailbox itself is bad. */
    private const BAD_MAILBOX = [
        'user unknown', 'unknown user', 'no such user', 'does not exist', 'doesn\'t exist', 'mailbox unavailable',
        'mailbox not found', 'unknown recipient', 'invalid recipient', 'recipient rejected', 'address rejected',
        'no mailbox here', 'account disabled', 'user not found', 'recipient address rejected', 'unrouteable address',
    ];

    /** Cheap header-only check, used to decide whether to download the whole message. */
    public static function looksLikeBounce(array $headers, ?string $from, string $subject): bool
    {
        $local = $from ? strtolower(explode('@', $from)[0]) : '';
        if (in_array($local, self::DAEMONS, true)) {
            return true;
        }

        $type = strtolower($headers['content-type'] ?? '');
        if (str_contains($type, 'report-type=delivery-status') || str_contains($type, 'multipart/report')) {
            return true;
        }

        if (isset($headers['return-path']) && trim($headers['return-path']) === '<>') {
            return true;
        }

        $subject = strtolower($subject);
        foreach (self::SUBJECT_HINTS as $hint) {
            if (str_contains($subject, $hint)) {
                return true;
            }
        }

        return false;
    }

    /** Auto-replies (out of office, vacation) must never stop a sequence. */
    public static function isAutoReply(InboundEmail $mail): bool
    {
        $auto = strtolower($mail->header('auto-submitted') ?? '');
        if ($auto !== '' && $auto !== 'no') {
            return true;
        }

        if ($mail->header('x-autoreply') !== null || $mail->header('x-autorespond') !== null) {
            return true;
        }

        $precedence = strtolower($mail->header('precedence') ?? '');
        if (in_array($precedence, ['auto_reply', 'bulk', 'junk'], true)) {
            return true;
        }

        $subject = strtolower($mail->subject);
        foreach ((array) config('sequencer.imap.auto_reply_subjects', []) as $hint) {
            if (str_contains($subject, strtolower($hint))) {
                return true;
            }
        }

        return false;
    }

    /** Parse a bounce. Returns null when the message is not a recognisable delivery failure. */
    public function parse(InboundEmail $mail): ?BounceInfo
    {
        if (! self::looksLikeBounce($mail->headers, $mail->fromEmail, $mail->subject)) {
            return null;
        }

        $source = $mail->rawSource ?? '';

        $status = null;
        if (preg_match('/^Status:\s*([245]\.\d{1,3}\.\d{1,3})/mi', $source, $m)) {
            $status = $m[1];
        } elseif (preg_match('/\b([245]\.\d{1,3}\.\d{1,3})\b/', $source, $m)) {
            $status = $m[1];
        }

        $action = null;
        if (preg_match('/^Action:\s*(\w+)/mi', $source, $m)) {
            $action = strtolower($m[1]);
        }

        $recipient = null;
        if (preg_match('/^(?:Final|Original)-Recipient:\s*(?:rfc822;)?\s*<?([^\s<>;]+@[^\s<>;]+)>?/mi', $source, $m)) {
            $recipient = strtolower(trim($m[1]));
        }

        $smtpCode = null;
        if (preg_match('/\b(5\d{2})[ -]/', $source, $m)) {
            $smtpCode = (int) $m[1];
        }

        $reason = $this->reason($source, $status, $smtpCode);

        return new BounceInfo(
            hard: $this->isHard($status, $action, $smtpCode, $source),
            status: $status,
            recipient: $recipient,
            reason: $reason,
            messageIds: $this->messageIds($source),
        );
    }

    /** Hard = the address will never accept mail. Mailbox-full, size and policy rejections are soft. */
    private function isHard(?string $status, ?string $action, ?int $smtpCode, string $source): bool
    {
        if ($action === 'delayed') {
            return false;
        }

        if ($status !== null) {
            if ($status[0] === '4') {
                return false;
            }

            if ($status[0] === '5') {
                foreach (['5.2.2', '5.2.3', '5.3.4'] as $soft) {
                    if ($status === $soft) {
                        return false;           // mailbox full / message too big
                    }
                }

                return ! str_starts_with($status, '5.7.');   // 5.7.x = policy / spam block, not a dead mailbox
            }
        }

        if ($smtpCode !== null && in_array($smtpCode, [550, 551, 553, 554], true)) {
            $lower = strtolower($source);
            foreach (self::BAD_MAILBOX as $phrase) {
                if (str_contains($lower, $phrase)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function reason(string $source, ?string $status, ?int $smtpCode): string
    {
        if (preg_match('/^Diagnostic-Code:\s*(?:smtp;)?\s*(.+)$/mi', $source, $m)) {
            return mb_substr(trim($m[1]), 0, 300);
        }

        if ($status || $smtpCode) {
            return trim(($smtpCode ? "SMTP $smtpCode " : '').($status ? "($status)" : '')).' delivery failure';
        }

        return 'Delivery failure notification received';
    }

    /** @return list<string> every <message-id> mentioned in the notification (capped) */
    private function messageIds(string $source): array
    {
        $ids = [];
        if (preg_match_all('/^(?:Message-ID|X-Original-Message-ID)\s*:\s*<([^<>\s]+)>/mi', $source, $m)) {
            $ids = $m[1];
        }

        return array_slice(array_values(array_unique($ids)), 0, 50);
    }

    public static function normalizeSubject(string $subject): string
    {
        return MessageHeaders::normalizeSubject($subject);
    }
}
