<?php

namespace Tests\Support;

use App\Models\MailSetting;
use App\Sequencer\Exceptions\ImapException;
use App\Sequencer\Imap\InboundEmail;
use App\Sequencer\Imap\InboxBatch;
use App\Sequencer\Mail\ConnectionResult;
use App\Services\ImapService;
use Carbon\CarbonImmutable;

/** A scripted mailbox standing in for ImapService: tests push messages in; fetchNew returns those after the cursor. */
class FakeImapService extends ImapService
{
    /** @var list<InboundEmail> */
    public array $messages = [];

    public int $uidValidity = 1000;

    public ?string $failWith = null;

    public int $fetches = 0;

    /** @var list<array{to: string, subject: string, account: ?int}> */
    public array $sentCopies = [];

    private int $nextUid = 1;

    /**
     * Add a message to the mailbox.
     *
     * @param  array{message_id?: ?string, in_reply_to?: ?string, references?: list<string>, from?: string, subject?: string, headers?: array, raw?: ?string, date?: ?CarbonImmutable}  $o
     */
    public function add(array $o = []): InboundEmail
    {
        $mail = new InboundEmail(
            uid: $this->nextUid++,
            messageId: array_key_exists('message_id', $o) ? $o['message_id'] : 'in-'.$this->nextUid.'@test.local',
            inReplyTo: $o['in_reply_to'] ?? null,
            references: $o['references'] ?? [],
            fromEmail: $o['from'] ?? 'someone@example.com',
            subject: $o['subject'] ?? 'Re: hello',
            date: $o['date'] ?? CarbonImmutable::now(),
            headers: $o['headers'] ?? [],
            rawSource: $o['raw'] ?? null,
        );

        $this->messages[] = $mail;

        return $mail;
    }

    public function fetchNew(MailSetting $account, ?int $afterUid, ?int $knownUidValidity, int $lookbackDays, int $limit): InboxBatch
    {
        $this->fetches++;

        if ($this->failWith) {
            throw new ImapException($this->failWith);
        }

        $first = $afterUid === null || ($knownUidValidity !== null && $knownUidValidity !== $this->uidValidity);

        $pool = array_values(array_filter($this->messages, fn (InboundEmail $m) => $first || $m->uid > $afterUid));

        return new InboxBatch($this->uidValidity, array_slice($pool, 0, $limit), $this->nextUid, count($pool) > $limit);
    }

    public function testAccount(MailSetting $account): ConnectionResult
    {
        return $this->failWith ? ConnectionResult::failure($this->failWith) : ConnectionResult::success('Fake IMAP ok.');
    }

    public function copyToSentFolder(string $to, string $subject, string $htmlBody, string $fromName, string $fromEmail, array $attachments = [], ?MailSetting $account = null, ?string $messageId = null): void
    {
        $this->sentCopies[] = ['to' => $to, 'subject' => $subject, 'account' => $account?->id];
    }
}
