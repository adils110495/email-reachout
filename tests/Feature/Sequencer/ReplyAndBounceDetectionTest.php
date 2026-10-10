<?php

namespace Tests\Feature\Sequencer;

use App\Models\ActivityEvent;
use App\Models\InboundMessage;
use App\Models\Lead;
use App\Models\LeadEmail;
use App\Models\MailSetting;
use App\Models\SequenceEnrollment;
use App\Sequencer\Enums\ContactStatus;
use App\Sequencer\Enums\EmailLogStatus;
use App\Sequencer\Enums\EnrollmentStatus;
use App\Sequencer\Enums\SendOutcome;
use App\Sequencer\Jobs\CheckIncomingRepliesJob;
use App\Sequencer\Services\SequenceEmailProcessor;
use App\Services\ReplyCheckerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesSequencerData;
use Tests\Support\FakeEmailProvider;
use Tests\TestCase;

/** ReplyCheckerService: replies and bounces for sequence emails and for direct Leads sends. */
class ReplyAndBounceDetectionTest extends TestCase
{
    use CreatesSequencerData, RefreshDatabase;

    private MailSetting $account;

    private SequenceEnrollment $enrollment;

    private LeadEmail $email;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeTransports();

        $this->account = $this->makeAccount();
        $sequence = $this->makeSequence([['days' => 0, 'subject' => 'Quick question'], ['days' => 2], ['days' => 2]]);
        $this->enrollment = $this->enroll($sequence, $this->makeLead(['email' => 'prospect@client.test']), $this->account);
        app(SequenceEmailProcessor::class)->process($this->enrollment->id);
        $this->email = LeadEmail::firstOrFail();
    }

    private function poll(): array
    {
        return app(ReplyCheckerService::class)->pollAccount($this->account->refresh());
    }

    public function test_a_reply_matched_by_in_reply_to_marks_the_email_and_stops_the_sequence(): void
    {
        $this->inbox->add(['from' => 'prospect@client.test', 'subject' => 'Re: Quick question', 'in_reply_to' => $this->email->message_id]);

        $this->assertSame(1, $this->poll()['replies']);

        $this->email->refresh();
        $this->assertNotNull($this->email->replied_at);
        $this->assertSame(EmailLogStatus::Replied, $this->email->engagement());
        $this->assertSame(LeadEmail::ACTIVITY_REPLIED, $this->email->activity);
        $this->assertSame(Lead::STATUS_REPLIED, $this->enrollment->lead->refresh()->status, 'The lead shows as replied on the Leads page.');

        $e = $this->enrollment->refresh();
        $this->assertSame(EnrollmentStatus::Replied, $e->status);
        $this->assertSame('replied', $e->stop_reason);
        $this->assertNotNull($e->stopped_at);

        $types = ActivityEvent::where('enrollment_id', $e->id)->pluck('type')->map->value->all();
        $this->assertContains('reply_received', $types);
        $this->assertContains('sequence_stopped', $types);

        // The follow-ups are never sent.
        $this->travel(10)->days();
        $this->assertSame(SendOutcome::Skipped, app(SequenceEmailProcessor::class)->process($e->id));
        $this->assertCount(1, FakeEmailProvider::$sent);
    }

    public function test_a_reply_matched_only_by_references_from_a_colleague(): void
    {
        $this->inbox->add(['from' => 'colleague@client.test', 'subject' => 'Fwd: hello', 'references' => ['older@elsewhere.test', $this->email->message_id]]);

        $this->assertSame(1, $this->poll()['replies']);
        $this->assertSame(EnrollmentStatus::Replied, $this->enrollment->refresh()->status);
    }

    public function test_a_reply_without_threading_headers_is_matched_by_any_of_the_leads_addresses(): void
    {
        // The lead has a second address; the reply comes from it, with no In-Reply-To.
        $this->enrollment->lead->update(['emails' => ['prospect@client.test', 'boss@client.test']]);
        $this->inbox->add(['from' => 'Boss@Client.test', 'subject' => 'About your note', 'message_id' => null]);

        $this->assertSame(1, $this->poll()['replies']);
        $this->assertSame(EnrollmentStatus::Replied, $this->enrollment->refresh()->status);
    }

    public function test_mail_from_strangers_or_from_before_we_wrote_is_not_a_reply(): void
    {
        $this->inbox->add(['from' => 'stranger@nowhere.test', 'subject' => 'Re: Quick question']);
        $this->inbox->add(['from' => 'prospect@client.test', 'subject' => 'Old thread', 'date' => now()->subDays(3)->toImmutable()]);

        $result = $this->poll();

        $this->assertSame(0, $result['replies']);
        $this->assertSame(2, $result['ignored']);
        $this->assertSame(EnrollmentStatus::Active, $this->enrollment->refresh()->status);
        $this->assertSame(2, InboundMessage::where('classification', 'unmatched')->count());
    }

    public function test_out_of_office_auto_replies_do_not_stop_the_sequence(): void
    {
        $this->inbox->add(['from' => 'prospect@client.test', 'subject' => 'Automatic reply: Quick question', 'in_reply_to' => $this->email->message_id, 'headers' => ['auto-submitted' => 'auto-replied']]);

        $this->poll();

        $this->assertSame(EnrollmentStatus::Active, $this->enrollment->refresh()->status);
        $this->assertSame('auto_reply', InboundMessage::first()->classification);
        $this->assertNull($this->email->refresh()->replied_at);
    }

    public function test_replies_to_emails_sent_from_the_leads_page_are_detected_too(): void
    {
        $lead = $this->makeLead(['email' => 'direct@client.test']);
        $direct = LeadEmail::create([
            'lead_id' => $lead->id, 'subject' => 'Hello', 'body' => 'x', 'status' => 'sent', 'sent_at' => now()->subHour(),
            'tracking_token' => Str::random(40), 'message_id' => 'direct-token@sender.test',   // no mail_setting_id, like older rows
        ]);
        $this->inbox->add(['from' => 'direct@client.test', 'in_reply_to' => 'direct-token@sender.test']);

        $this->assertSame(1, app(ReplyCheckerService::class)->check(), 'check() is what the Email Activity button calls.');

        $this->assertNotNull($direct->refresh()->replied_at);
        $this->assertSame(Lead::STATUS_REPLIED, $lead->refresh()->status);
    }

    public function test_messages_are_never_processed_twice(): void
    {
        $this->inbox->add(['from' => 'prospect@client.test', 'in_reply_to' => $this->email->message_id, 'message_id' => 'reply-1@client.test']);

        $this->poll();
        // Cursor reset / UIDVALIDITY change / overlapping poll: the same message is seen again.
        $this->account->forceFill(['imap_last_uid' => null])->save();
        $second = $this->poll();

        $this->assertSame(0, $second['replies']);
        $this->assertSame(1, InboundMessage::count());
        $this->assertSame(1, ActivityEvent::where('type', 'reply_received')->count());

        // The same Message-ID arriving under a different UID (copied to another folder) is also a duplicate.
        $this->inbox->add(['from' => 'prospect@client.test', 'in_reply_to' => $this->email->message_id, 'message_id' => 'reply-1@client.test']);
        $this->assertSame('duplicate', app(ReplyCheckerService::class)->processMessage($this->account, end($this->inbox->messages), 1000));
    }

    public function test_the_cursor_advances_so_old_mail_is_not_refetched(): void
    {
        $this->inbox->add(['from' => 'a@x.test']);
        $this->inbox->add(['from' => 'b@x.test']);
        $this->poll();
        $this->assertSame(2, (int) $this->account->refresh()->imap_last_uid);
        $this->assertSame(1000, (int) $this->account->imap_uid_validity);
        $this->assertTrue($this->account->imap_ok);

        $this->inbox->add(['from' => 'c@x.test']);
        $this->assertSame(1, $this->poll()['fetched'], 'Only the new message.');
    }

    public function test_an_imap_failure_is_recorded_and_retried_next_poll(): void
    {
        $this->inbox->failWith = 'Connection timed out';

        $this->assertSame('Connection timed out', $this->poll()['error']);
        $this->assertFalse($this->account->refresh()->imap_ok);
        $this->assertSame('Connection timed out', $this->account->imap_last_error);

        $this->inbox->failWith = null;
        $this->inbox->add(['from' => 'prospect@client.test', 'in_reply_to' => $this->email->message_id]);
        $this->assertSame(1, $this->poll()['replies']);
        $this->assertTrue($this->account->refresh()->imap_ok);
    }

    // ── bounces via IMAP ───────────────────────────────────────────────────

    private function dsn(string $status, string $diagnostic, ?string $messageId = null): string
    {
        $messageId ??= $this->email->message_id;

        return "From: MAILER-DAEMON@mx.client.test\r\nSubject: Undelivered Mail Returned to Sender\r\nContent-Type: multipart/report; report-type=delivery-status; boundary=\"b\"\r\n\r\n"
            ."--b\r\nContent-Type: text/plain\r\n\r\nDelivery failed.\r\n"
            ."--b\r\nContent-Type: message/delivery-status\r\n\r\nReporting-MTA: dns; mx.client.test\r\n\r\nFinal-Recipient: rfc822; prospect@client.test\r\nAction: failed\r\nStatus: $status\r\nDiagnostic-Code: smtp; $diagnostic\r\n"
            ."--b\r\nContent-Type: text/rfc822-headers\r\n\r\nFrom: sam@sender.test\r\nTo: prospect@client.test\r\nMessage-ID: <$messageId>\r\nSubject: Quick question\r\n--b--\r\n";
    }

    private function addBounce(string $raw): void
    {
        $this->inbox->add([
            'from' => 'mailer-daemon@mx.client.test',
            'subject' => 'Undelivered Mail Returned to Sender',
            'headers' => ['content-type' => 'multipart/report; report-type=delivery-status; boundary="b"'],
            'raw' => $raw,
        ]);
    }

    public function test_a_hard_bounce_marks_the_lead_bounced_and_stops_every_sequence(): void
    {
        $lead = $this->enrollment->lead;
        $other = $this->enroll($this->makeSequence([['days' => 5]]), $lead, $this->account);
        $this->addBounce($this->dsn('5.1.1', '550 5.1.1 <prospect@client.test>: User unknown'));

        $this->assertSame(1, $this->poll()['bounces']);

        $this->email->refresh();
        $this->assertSame('bounced', $this->email->status);
        $this->assertNotNull($this->email->bounced_at);
        $this->assertStringContainsString('User unknown', $this->email->error_message);

        $this->assertSame(ContactStatus::Bounced, $lead->refresh()->contact_status);
        foreach ([$this->enrollment, $other] as $e) {
            $e->refresh();
            $this->assertSame(EnrollmentStatus::Bounced, $e->status);
            $this->assertSame('email_bounced', $e->stop_reason);
        }

        $this->travel(10)->days();
        $this->assertSame(SendOutcome::Skipped, app(SequenceEmailProcessor::class)->process($this->enrollment->id));
        $this->assertCount(1, FakeEmailProvider::$sent, 'No future sequence email.');
    }

    public function test_a_soft_bounce_changes_no_state(): void
    {
        $this->addBounce($this->dsn('4.2.2', '452 4.2.2 Mailbox full'));

        $this->poll();

        $this->assertSame(EnrollmentStatus::Active, $this->enrollment->refresh()->status);
        $this->assertSame(ContactStatus::Active, $this->enrollment->lead->refresh()->contact_status);
        $this->assertSame('soft_bounce', InboundMessage::first()->classification);
        $this->assertStringStartsWith('Soft bounce', (string) $this->email->refresh()->error_message);
    }

    public function test_a_bounce_without_the_original_message_id_is_matched_by_recipient(): void
    {
        $this->addBounce($this->dsn('5.1.1', '550 no such user', 'unrelated@elsewhere.test'));

        $this->poll();

        $this->assertSame(ContactStatus::Bounced, $this->enrollment->lead->refresh()->contact_status);
    }

    // ── scheduling the poll ────────────────────────────────────────────────

    public function test_the_existing_check_replies_command_queues_one_poll_per_imap_account(): void
    {
        $this->makeAccount(['imap_host' => null, 'imap_username' => null, 'imap_password' => null]);   // no IMAP: skipped
        $this->makeAccount(['is_active' => false]);                                                    // inactive: skipped

        Queue::fake();
        $this->artisan('emails:check-replies')->assertSuccessful();

        Queue::assertPushed(CheckIncomingRepliesJob::class, 1);
        Queue::assertPushed(CheckIncomingRepliesJob::class, fn ($job) => $job->mailSettingId === $this->account->id && $job->queue === 'default');
    }

    public function test_the_job_and_the_sync_option_run_detection(): void
    {
        $this->inbox->add(['from' => 'prospect@client.test', 'in_reply_to' => $this->email->message_id]);

        (new CheckIncomingRepliesJob($this->account->id))->handle(app(ReplyCheckerService::class));
        $this->assertSame(EnrollmentStatus::Replied, $this->enrollment->refresh()->status);

        $this->artisan('emails:check-replies', ['--sync' => true])->assertSuccessful();
    }

    public function test_check_reports_when_no_imap_is_configured(): void
    {
        MailSetting::query()->update(['imap_host' => null]);

        $this->expectException(\RuntimeException::class);
        app(ReplyCheckerService::class)->check();
    }
}
