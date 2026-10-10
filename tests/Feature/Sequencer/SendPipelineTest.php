<?php

namespace Tests\Feature\Sequencer;

use App\Models\DailySendCounter;
use App\Models\Lead;
use App\Models\LeadEmail;
use App\Models\SequenceEnrollment;
use App\Sequencer\Enums\ContactStatus;
use App\Sequencer\Enums\EnrollmentStatus;
use App\Sequencer\Enums\SendOutcome;
use App\Sequencer\Enums\SequenceStatus;
use App\Sequencer\Exceptions\PermanentSendException;
use App\Sequencer\Exceptions\RecipientRejectedException;
use App\Sequencer\Exceptions\TransientSendException;
use App\Sequencer\Services\EnrollmentService;
use App\Sequencer\Services\SequenceEmailProcessor;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSequencerData;
use Tests\Support\FakeEmailProvider;
use Tests\TestCase;

/** The send pipeline, working on leads, lead_emails and mail_settings. */
class SendPipelineTest extends TestCase
{
    use CreatesSequencerData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeTransports();
        $this->makeAccount();
        config(['sequencer.tracking.public_url' => 'https://track.example.com']);
    }

    private function process(SequenceEnrollment $e): SendOutcome
    {
        return app(SequenceEmailProcessor::class)->process($e->id);
    }

    /** A lead_emails row as another worker / an earlier attempt would have left it. */
    private function existingAttempt(SequenceEnrollment $enrollment, string $status, $claimedAt): LeadEmail
    {
        return LeadEmail::create([
            'lead_id' => $enrollment->lead_id, 'sequence_id' => $enrollment->sequence_id,
            'sequence_step_id' => $enrollment->sequence->steps->first()->id, 'enrollment_id' => $enrollment->id,
            'mail_setting_id' => $enrollment->mail_setting_id, 'message_id' => 'a@sender.test', 'tracking_token' => str_repeat('b', 40),
            'from_email' => 'sam@sender.test', 'to_email' => 'x@y.test', 'subject' => 's', 'body' => '',
            'status' => $status, 'attempts' => 1, 'claimed_at' => $claimedAt,
        ]);
    }

    public function test_first_step_is_sent_immediately_and_the_next_one_scheduled(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-03-03 10:00:00', 'UTC'));   // a Tuesday
        $sequence = $this->makeSequence([['days' => 0], ['days' => 2]]);
        $lead = $this->makeLead(['first_name' => 'Dana', 'email' => 'dana@prospect.test']);
        $enrollment = $this->enroll($sequence, $lead);

        $this->assertTrue($enrollment->next_action_at->lte(now()));
        $this->assertSame(SendOutcome::Sent, $this->process($enrollment));

        $this->assertCount(1, FakeEmailProvider::$sent);
        $mail = FakeEmailProvider::$sent[0];
        $this->assertSame('dana@prospect.test', $mail->toEmail);
        $this->assertSame('Hello Dana', $mail->subject);
        $this->assertStringContainsString('Hi Dana', $mail->html);
        $this->assertStringContainsString('Sam Sender', $mail->html);

        // Recorded in the same table as emails sent from the Leads page.
        $email = LeadEmail::firstOrFail();
        $this->assertSame('sent', $email->status);
        $this->assertSame($lead->id, $email->lead_id);
        $this->assertSame($sequence->id, $email->sequence_id);
        $this->assertNotNull($email->sent_at);
        $this->assertSame($mail->messageId, $email->message_id);
        $this->assertSame('dana@prospect.test', $email->to_email);
        $this->assertStringContainsString('Hi Dana', $email->body);

        $this->assertSame(Lead::STATUS_SENT, $lead->refresh()->status, 'The lead\'s outreach status follows.');
        $this->assertCount(1, $this->inbox->sentCopies, 'Copied to the Sent folder like a Leads send.');

        $enrollment->refresh();
        $this->assertSame(1, $enrollment->current_step);
        $this->assertSame(EnrollmentStatus::Active, $enrollment->status);
        $this->assertEquals(now()->addDays(2)->toDateTimeString(), $enrollment->next_action_at->toDateTimeString());
        $this->assertNull($enrollment->dispatch_lease_until);

        // Nothing is due yet: running the pipeline again sends nothing.
        $this->assertSame(SendOutcome::Skipped, $this->process($enrollment));
        $this->assertCount(1, FakeEmailProvider::$sent);
    }

    public function test_delayed_step_goes_out_when_due_and_the_enrollment_completes(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-03-03 10:00:00', 'UTC'));
        $sequence = $this->makeSequence([['days' => 0], ['days' => 2, 'subject' => 'Following up']]);
        $enrollment = $this->enroll($sequence, $this->makeLead());

        $this->process($enrollment);
        $this->travel(2)->days();

        $this->assertSame(SendOutcome::Completed, $this->process($enrollment->refresh()));

        $this->assertCount(2, FakeEmailProvider::$sent);
        $this->assertSame('Following up', FakeEmailProvider::$sent[1]->subject);

        $enrollment->refresh();
        $this->assertSame(EnrollmentStatus::Completed, $enrollment->status);
        $this->assertNotNull($enrollment->completed_at);
        $this->assertNull($enrollment->next_action_at, 'No further job may be scheduled.');
        $this->assertSame(2, $enrollment->current_step);

        $this->assertSame(SendOutcome::Skipped, $this->process($enrollment));
        $this->assertCount(2, FakeEmailProvider::$sent);
    }

    public function test_every_email_carries_unsubscribe_tracking_and_threading_headers(): void
    {
        $sequence = $this->makeSequence([['body' => '<p>See <a href="https://acme.test/pricing">pricing</a> or <a href="mailto:a@b.test">mail</a></p>']]);
        $lead = $this->makeLead();
        $this->process($this->enroll($sequence, $lead));

        $mail = FakeEmailProvider::$sent[0];
        $email = LeadEmail::firstOrFail();

        $this->assertStringContainsString('https://track.example.com/unsubscribe/'.$lead->unsubscribe_token, $mail->html);
        $this->assertStringContainsString('https://track.example.com/track/open/'.$email->tracking_token, $mail->html);
        $this->assertStringNotContainsString('https://acme.test/pricing', $mail->html);   // rewritten
        $this->assertStringContainsString('/track/click/', $mail->html);
        $this->assertStringContainsString('mailto:a@b.test', $mail->html);                // left alone
        $this->assertStringContainsString('unsubscribe', strtolower($mail->text));
        $this->assertStringContainsString('/unsubscribe/'.$lead->unsubscribe_token, $mail->headers['List-Unsubscribe']);
        $this->assertSame('List-Unsubscribe=One-Click', $mail->headers['List-Unsubscribe-Post']);
        $this->assertMatchesRegularExpression('/^\d{14}\.[0-9a-f]{24}@sender\.test$/', $mail->messageId);
    }

    public function test_tracking_can_be_switched_off_per_sequence(): void
    {
        $sequence = $this->makeSequence([['body' => '<a href="https://acme.test">x</a>']], ['track_opens' => false, 'track_clicks' => false]);
        $this->process($this->enroll($sequence, $this->makeLead()));

        $html = FakeEmailProvider::$sent[0]->html;
        $this->assertStringNotContainsString('/track/open/', $html);
        $this->assertStringNotContainsString('/track/click/', $html);
        $this->assertStringContainsString('href="https://acme.test"', $html);
        $this->assertStringContainsString('/unsubscribe/', $html, 'Unsubscribe is mandatory regardless of tracking.');
    }

    // ── sending window ─────────────────────────────────────────────────────

    public function test_enrolling_outside_the_window_schedules_the_next_allowed_time(): void
    {
        // Saturday noon, window is Mon-Fri 09:00-17:00 New York.
        $this->travelTo(CarbonImmutable::parse('2026-03-07 12:00:00', 'America/New_York')->utc());
        $sequence = $this->makeSequence([['days' => 0]], [
            'timezone' => 'America/New_York', 'sending_start_time' => '09:00', 'sending_end_time' => '17:00', 'sending_days' => [1, 2, 3, 4, 5],
        ]);
        $enrollment = $this->enroll($sequence, $this->makeLead());

        $this->assertSame('2026-03-09 09:00:00', $enrollment->next_action_at->setTimezone('America/New_York')->toDateTimeString());
        $this->assertSame(SendOutcome::Skipped, $this->process($enrollment), 'Not due on Saturday.');
        $this->assertCount(0, FakeEmailProvider::$sent);

        $this->travelTo(CarbonImmutable::parse('2026-03-09 09:00:00', 'America/New_York')->utc());   // DST began on the 8th
        $this->assertSame(SendOutcome::Completed, $this->process($enrollment->refresh()));
        $this->assertCount(1, FakeEmailProvider::$sent);
    }

    public function test_a_job_that_runs_outside_the_window_defers_instead_of_sending(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-03-06 16:00:00', 'UTC'));   // Friday 16:00
        $sequence = $this->makeSequence([['days' => 0]], [
            'sending_start_time' => '09:00', 'sending_end_time' => '17:00', 'sending_days' => [1, 2, 3, 4, 5],
        ]);
        $enrollment = $this->enroll($sequence, $this->makeLead());

        // The queue was backed up: the job only runs at 17:30, after the window closed.
        $this->travelTo(CarbonImmutable::parse('2026-03-06 17:30:00', 'UTC'));

        $this->assertSame(SendOutcome::Deferred, $this->process($enrollment));
        $this->assertCount(0, FakeEmailProvider::$sent);
        $this->assertSame('2026-03-09 09:00:00', $enrollment->refresh()->next_action_at->utc()->toDateTimeString());   // Monday
        $this->assertSame(0, LeadEmail::count());
    }

    // ── limits ─────────────────────────────────────────────────────────────

    public function test_the_sequence_daily_limit_is_enforced_and_overflow_moves_to_the_next_day(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-03-03 10:00:00', 'UTC'));
        $sequence = $this->makeSequence([['days' => 0]], ['daily_limit' => 2]);

        $enrollments = collect(range(1, 4))->map(fn () => $this->enroll($sequence, $this->makeLead()));
        $outcomes = $enrollments->map(fn ($e) => $this->process($e->refresh()));

        $this->assertSame(2, $outcomes->filter(fn ($o) => $o === SendOutcome::Completed)->count());
        $this->assertSame(2, $outcomes->filter(fn ($o) => $o === SendOutcome::Deferred)->count());
        $this->assertCount(2, FakeEmailProvider::$sent);
        $this->assertSame(2, DailySendCounter::where('scope', 'sequence')->where('scope_id', $sequence->id)->value('count'));

        $deferred = $enrollments->map->refresh()->filter(fn ($e) => $e->status === EnrollmentStatus::Active);
        foreach ($deferred as $e) {
            $this->assertSame('2026-03-04 00:00:00', $e->next_action_at->utc()->toDateTimeString(), 'Rescheduled for the start of tomorrow.');
        }

        $this->travelTo(CarbonImmutable::parse('2026-03-04 00:00:01', 'UTC'));
        foreach ($deferred as $e) {
            $this->assertSame(SendOutcome::Completed, $this->process($e->refresh()));
        }
        $this->assertCount(4, FakeEmailProvider::$sent);
    }

    public function test_the_account_daily_limit_is_enforced_across_sequences(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-03-03 10:00:00', 'UTC'));
        $account = $this->makeAccount(['daily_limit' => 3]);
        $a = $this->makeSequence([['days' => 0]]);
        $b = $this->makeSequence([['days' => 0]]);

        $sent = 0;
        foreach ([$a, $a, $b, $b] as $sequence) {
            $sent += $this->process($this->enroll($sequence, $this->makeLead(), $account)) === SendOutcome::Completed ? 1 : 0;
        }

        $this->assertSame(3, $sent);
        $this->assertCount(3, FakeEmailProvider::$sent);
        // The sequence slot taken for the blocked 4th send was handed back.
        $this->assertSame(3, (int) DailySendCounter::where('scope', 'sequence')->sum('count'));
    }

    public function test_the_per_minute_rate_limit_defers_the_excess(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-03-03 10:00:10', 'UTC'));
        $account = $this->makeAccount(['rate_limit_per_minute' => 2]);
        $sequence = $this->makeSequence([['days' => 0]]);

        $outcomes = collect(range(1, 3))->map(fn () => $this->process($this->enroll($sequence, $this->makeLead(), $account)));

        $this->assertSame([SendOutcome::Completed, SendOutcome::Completed, SendOutcome::Deferred], $outcomes->all());
        $this->assertCount(2, FakeEmailProvider::$sent);

        $deferred = SequenceEnrollment::where('status', 'active')->firstOrFail();
        $this->assertSame('2026-03-03 10:01:00', $deferred->next_action_at->utc()->toDateTimeString(), 'Waits for the next minute.');

        $this->travelTo(CarbonImmutable::parse('2026-03-03 10:01:00', 'UTC'));
        $this->assertSame(SendOutcome::Completed, $this->process($deferred));
    }

    // ── duplicate protection ───────────────────────────────────────────────

    public function test_a_step_that_was_already_sent_is_never_sent_again(): void
    {
        $sequence = $this->makeSequence([['days' => 0], ['days' => 1]]);
        $enrollment = $this->enroll($sequence, $this->makeLead());

        $this->process($enrollment);
        $this->assertCount(1, FakeEmailProvider::$sent);

        // Simulate a crash / restored backup / stale queue message: the enrollment forgot step 1 went out.
        $enrollment->refresh()->forceFill(['current_step' => 0, 'next_action_at' => now()->subMinute()])->save();

        $this->assertSame(SendOutcome::Recovered, $this->process($enrollment));
        $this->assertCount(1, FakeEmailProvider::$sent, 'The duplicate must not be sent.');
        $this->assertSame(1, LeadEmail::count());
        $this->assertSame(1, $enrollment->refresh()->current_step, 'Progress was repaired.');
    }

    public function test_the_database_itself_refuses_a_second_attempt_for_the_same_step(): void
    {
        $enrollment = $this->enroll($this->makeSequence(), $this->makeLead());
        $this->process($enrollment);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->existingAttempt($enrollment->load('sequence.steps'), 'sending', now());
    }

    public function test_legacy_emails_without_an_enrollment_are_not_affected_by_the_unique_key(): void
    {
        $lead = $this->makeLead();

        // Two direct sends from the Leads page: enrollment_id and sequence_step_id are NULL for both.
        foreach (['one', 'two'] as $subject) {
            LeadEmail::create(['lead_id' => $lead->id, 'subject' => $subject, 'body' => 'x', 'status' => 'sent', 'sent_at' => now()]);
        }

        $this->assertSame(2, LeadEmail::count());
    }

    public function test_a_send_in_progress_on_another_worker_is_not_duplicated(): void
    {
        $enrollment = $this->enroll($this->makeSequence(), $this->makeLead())->load('sequence.steps');
        $this->existingAttempt($enrollment, 'sending', now());

        $this->assertSame(SendOutcome::Skipped, $this->process($enrollment));
        $this->assertCount(0, FakeEmailProvider::$sent);
        $this->assertSame(1, LeadEmail::count());
        $this->assertSame(EnrollmentStatus::Active, $enrollment->refresh()->status, 'Left alone for the other worker.');
    }

    public function test_a_send_stuck_in_sending_is_not_retried_because_delivery_is_unknown(): void
    {
        $enrollment = $this->enroll($this->makeSequence([['days' => 0], ['days' => 1]]), $this->makeLead())->load('sequence.steps');
        $this->existingAttempt($enrollment, 'sending', now()->subMinutes(30));

        $this->assertSame(SendOutcome::Failed, $this->process($enrollment));
        $this->assertCount(0, FakeEmailProvider::$sent, 'Never risk a duplicate.');

        $enrollment->refresh();
        $this->assertSame(EnrollmentStatus::Failed, $enrollment->status);
        $this->assertSame('delivery_uncertain', $enrollment->stop_reason);
        $this->assertSame('failed', LeadEmail::first()->status);
    }

    // ── failure handling ───────────────────────────────────────────────────

    public function test_a_transient_smtp_failure_is_retried_with_backoff_then_succeeds(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-03-03 10:00:00', 'UTC'));
        $enrollment = $this->enroll($this->makeSequence(), $this->makeLead());

        FakeEmailProvider::failWith(new TransientSendException('421 try again later'), times: 1);
        $this->assertSame(SendOutcome::Retry, $this->process($enrollment));

        $email = LeadEmail::firstOrFail();
        $this->assertSame('queued', $email->status, 'Waiting to be retried.');
        $this->assertSame(1, $email->attempts);
        $this->assertStringContainsString('421', (string) $email->error_message);
        $enrollment->refresh();
        $this->assertSame(EnrollmentStatus::Active, $enrollment->status);
        $this->assertSame('2026-03-03 10:00:30', $enrollment->next_action_at->utc()->toDateTimeString(), 'First backoff is 30 seconds.');
        $this->assertSame(0, (int) DailySendCounter::where('scope', 'sequence')->sum('count'), 'The quota slot was returned.');

        $this->travel(31)->seconds();
        $this->assertSame(SendOutcome::Completed, $this->process($enrollment->refresh()));

        $this->assertCount(1, FakeEmailProvider::$sent);
        $this->assertSame(1, LeadEmail::count(), 'The same row is reused, never a second one.');
        $email->refresh();
        $this->assertSame('sent', $email->status);
        $this->assertSame(2, $email->attempts);
        $this->assertNull($email->error_message);
    }

    public function test_retries_stop_after_the_configured_number_of_attempts(): void
    {
        config(['sequencer.send.tries' => 3]);
        $this->travelTo(CarbonImmutable::parse('2026-03-03 10:00:00', 'UTC'));
        $enrollment = $this->enroll($this->makeSequence(), $this->makeLead());
        FakeEmailProvider::failWith(new TransientSendException('connection timed out'));

        $outcomes = [];
        for ($i = 0; $i < 3; $i++) {
            $this->travel(1)->hours();
            $outcomes[] = $this->process($enrollment->refresh());
        }

        $this->assertSame([SendOutcome::Retry, SendOutcome::Retry, SendOutcome::Failed], $outcomes);
        $enrollment->refresh();
        $this->assertSame(EnrollmentStatus::Failed, $enrollment->status);
        $this->assertSame('send_failed', $enrollment->stop_reason);
        $this->assertSame('failed', LeadEmail::first()->status);
        $this->assertSame(Lead::STATUS_FAILED, $enrollment->lead->status);

        $this->travel(1)->hours();
        $this->assertSame(SendOutcome::Skipped, $this->process($enrollment), 'No indefinite retrying.');
    }

    public function test_a_permanent_failure_is_not_retried(): void
    {
        $enrollment = $this->enroll($this->makeSequence(), $this->makeLead());
        FakeEmailProvider::failWith(new PermanentSendException('554 message rejected as spam'));

        $this->assertSame(SendOutcome::Failed, $this->process($enrollment));
        $this->assertSame(EnrollmentStatus::Failed, $enrollment->refresh()->status);
        $this->assertSame(1, LeadEmail::first()->attempts);
        $this->assertCount(0, FakeEmailProvider::$sent);
    }

    public function test_an_address_rejected_by_smtp_is_a_hard_bounce_that_stops_every_sequence(): void
    {
        $lead = $this->makeLead();
        $first = $this->enroll($this->makeSequence([['days' => 0], ['days' => 1]]), $lead);
        $second = $this->enroll($this->makeSequence([['days' => 3]]), $lead);
        FakeEmailProvider::failWith(new RecipientRejectedException('550 5.1.1 user unknown'));

        $this->assertSame(SendOutcome::Bounced, $this->process($first));

        $this->assertSame(ContactStatus::Bounced, $lead->refresh()->contact_status);
        $this->assertNotNull($lead->bounced_at);
        $this->assertSame('bounced', LeadEmail::first()->status);

        $first->refresh();
        $this->assertSame(EnrollmentStatus::Bounced, $first->status);
        $this->assertSame('email_bounced', $first->stop_reason);
        $this->assertSame(EnrollmentStatus::Bounced, $second->refresh()->status, 'Other sequences stop as well.');

        FakeEmailProvider::reset();
        $this->travel(5)->days();
        $this->assertSame(SendOutcome::Skipped, $this->process($first));
        $this->assertCount(0, FakeEmailProvider::$sent);
    }

    // ── never trust stale queue data ───────────────────────────────────────

    public function test_stop_conditions_are_rechecked_immediately_before_sending(): void
    {
        $sequence = $this->makeSequence();
        $cases = ['unsubscribed' => 'unsubscribed', 'bounced' => 'email_bounced', 'archived' => 'contact_inactive', 'invalid' => 'contact_inactive'];

        $enrollments = [];
        foreach (array_keys($cases) as $status) {
            $lead = $this->makeLead();
            $enrollments[$status] = $this->enroll($sequence, $lead);
            // State changes behind the queue's back (the job is already "in flight").
            Lead::whereKey($lead->id)->update(['contact_status' => $status]);
        }

        // A lead whose address was removed can no longer be emailed either.
        $noEmail = $this->makeLead();
        $enrollments['no_email'] = $this->enroll($sequence, $noEmail);
        Lead::whereKey($noEmail->id)->update(['email' => null, 'emails' => null]);

        foreach ($enrollments as $enrollment) {
            $this->assertSame(SendOutcome::Stopped, $this->process($enrollment));
        }

        $this->assertCount(0, FakeEmailProvider::$sent);
        foreach ($cases as $status => $reason) {
            $this->assertSame($reason, $enrollments[$status]->refresh()->stop_reason);
        }
        $this->assertSame('contact_inactive', $enrollments['no_email']->refresh()->stop_reason);
    }

    public function test_deleting_a_lead_removes_its_enrollments_so_queued_jobs_do_nothing(): void
    {
        $lead = $this->makeLead();
        $enrollment = $this->enroll($this->makeSequence(), $lead);

        $lead->delete();   // as the Leads page does

        $this->assertDatabaseMissing('sequence_enrollments', ['id' => $enrollment->id]);
        $this->assertSame(SendOutcome::Skipped, $this->process($enrollment));
        $this->assertCount(0, FakeEmailProvider::$sent);
    }

    public function test_nothing_is_sent_while_the_sequence_or_enrollment_is_paused(): void
    {
        $sequence = $this->makeSequence([['days' => 0], ['days' => 1], ['days' => 1]]);
        $enrollment = $this->enroll($sequence, $this->makeLead());

        $this->process($enrollment);                       // step 1 out
        $this->assertCount(1, FakeEmailProvider::$sent);

        // Sequence paused: step 2 comes due but must not go out.
        $sequence->forceFill(['status' => SequenceStatus::Paused])->save();
        $this->travel(2)->days();
        $this->assertSame(SendOutcome::Skipped, $this->process($enrollment->refresh()));
        $this->assertCount(1, FakeEmailProvider::$sent);

        // Resumed: continues from step 2 - does not restart from step 1.
        $sequence->forceFill(['status' => SequenceStatus::Active])->save();
        $this->assertSame(SendOutcome::Sent, $this->process($enrollment->refresh()));
        $this->assertCount(2, FakeEmailProvider::$sent);
        $this->assertSame(2, $enrollment->refresh()->current_step);

        // Enrollment paused individually.
        app(EnrollmentService::class)->pause($enrollment);
        $this->travel(2)->days();
        $this->assertSame(SendOutcome::Skipped, $this->process($enrollment->refresh()));
        $this->assertCount(2, FakeEmailProvider::$sent);

        app(EnrollmentService::class)->resume($enrollment);
        $this->assertSame(SendOutcome::Completed, $this->process($enrollment->refresh()));
        $this->assertCount(3, FakeEmailProvider::$sent);
        $this->assertSame(EnrollmentStatus::Completed, $enrollment->refresh()->status);
    }

    public function test_an_inactive_mail_account_defers_and_a_missing_one_fails(): void
    {
        $account = $this->makeAccount();
        $sequence = $this->makeSequence();
        $waiting = $this->enroll($sequence, $this->makeLead(), $account);

        $account->forceFill(['is_active' => false])->save();
        $this->assertSame(SendOutcome::Deferred, $this->process($waiting));
        $this->assertCount(0, FakeEmailProvider::$sent);
        $this->assertSame(EnrollmentStatus::Active, $waiting->refresh()->status);

        $orphan = $this->enroll($sequence, $this->makeLead());
        $orphan->forceFill(['mail_setting_id' => null])->save();
        $this->assertSame(SendOutcome::Failed, $this->process($orphan));
        $this->assertSame(EnrollmentStatus::Failed, $orphan->refresh()->status);
    }

    public function test_an_enrollment_without_remaining_steps_completes_without_sending(): void
    {
        $enrollment = $this->enroll($this->makeSequence([['days' => 0]]), $this->makeLead());
        $enrollment->forceFill(['current_step' => 1])->save();

        $this->assertSame(SendOutcome::Completed, $this->process($enrollment));
        $this->assertCount(0, FakeEmailProvider::$sent);
        $this->assertSame(EnrollmentStatus::Completed, $enrollment->refresh()->status);
    }
}
