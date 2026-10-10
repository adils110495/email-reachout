<?php

namespace Tests\Feature\Sequencer;

use App\Jobs\ProcessBulkJob;
use App\Models\Bulk;
use App\Models\Lead;
use App\Models\LeadEmail;
use App\Models\MailSetting;
use App\Models\SequenceEnrollment;
use App\Sequencer\Enums\ContactStatus;
use App\Sequencer\Enums\EnrollmentStatus;
use App\Sequencer\Enums\SequenceStatus;
use App\Sequencer\Jobs\SendSequenceEmailJob;
use App\Sequencer\Services\EnrollmentService;
use App\Sequencer\Services\SequenceEmailProcessor;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\CreatesSequencerData;
use Tests\Support\FakeEmailProvider;
use Tests\TestCase;

class EnrollmentAndSchedulerTest extends TestCase
{
    use CreatesSequencerData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeTransports();
        $this->makeAccount();
        $this->actingAs($this->makeUser());
    }

    // ── enrolling ──────────────────────────────────────────────────────────

    public function test_enrolling_sets_the_first_due_time_from_the_first_steps_delay(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-03-03 10:00:00', 'UTC'));

        $e = $this->enroll($this->makeSequence([['hours' => 3]]), $this->makeLead());

        $this->assertSame(EnrollmentStatus::Active, $e->status);
        $this->assertSame('2026-03-03 13:00:00', $e->next_action_at->utc()->toDateTimeString());
        $this->assertSame(0, $e->current_step);
        $this->assertNotNull($e->started_at);
    }

    public function test_the_same_lead_cannot_be_enrolled_twice(): void
    {
        $sequence = $this->makeSequence();
        $lead = $this->makeLead();
        $service = app(EnrollmentService::class);

        $first = $service->enroll($sequence, $lead);
        $again = $service->enroll($sequence, $lead);

        $this->assertSame('enrolled', $first->outcome);
        $this->assertSame('already_enrolled', $again->outcome);
        $this->assertSame($first->enrollment->id, $again->enrollment->id);
        $this->assertSame(1, SequenceEnrollment::count());

        // Even a raw duplicate insert (a racing request) is refused by the database.
        $this->expectException(UniqueConstraintViolationException::class);
        SequenceEnrollment::create(['sequence_id' => $sequence->id, 'lead_id' => $lead->id]);
    }

    public function test_one_address_is_enrolled_once_per_sequence_even_when_two_leads_hold_it(): void
    {
        $sequence = $this->makeSequence();
        $other = $this->makeSequence();
        $lead = $this->makeLead(['email' => 'same@inbox.test']);
        $twin = $this->makeLead(['email' => 'SAME@inbox.test']);   // a duplicate row in Leads
        $service = app(EnrollmentService::class);

        $enrollment = $this->enroll($sequence, $lead);
        $result = $service->enroll($sequence, $twin);

        $this->assertSame('skipped', $result->outcome);
        $this->assertSame('duplicate_email_in_sequence', $result->reason);
        $this->assertSame(1, SequenceEnrollment::where('sequence_id', $sequence->id)->count());

        // A different sequence is a different conversation.
        $this->assertTrue($service->enroll($other, $twin)->wasEnrolled());

        // Once the first lead is removed from the sequence, the address is free again.
        $service->remove($enrollment);
        $this->assertTrue($service->enroll($sequence, $twin)->wasEnrolled());
    }

    public function test_leads_that_cannot_receive_email_are_skipped(): void
    {
        $sequence = $this->makeSequence();
        $service = app(EnrollmentService::class);

        foreach (['unsubscribed', 'bounced', 'invalid', 'archived'] as $status) {
            $lead = $this->makeLead();
            $lead->forceFill(['contact_status' => $status])->save();
            $result = $service->enroll($sequence, $lead);
            $this->assertSame('skipped', $result->outcome);
            $this->assertSame("lead_$status", $result->reason);
        }

        $noEmail = Lead::create(['company_name' => 'No Mail Ltd', 'website' => 'https://nomail.test']);
        $this->assertSame('lead_has_no_email', $service->enroll($sequence, $noEmail)->reason);

        $this->assertSame(0, SequenceEnrollment::count());
    }

    public function test_enrolling_needs_steps_and_a_mail_account_and_uses_the_default_one(): void
    {
        $service = app(EnrollmentService::class);
        $lead = $this->makeLead();

        $this->assertSame('sequence_has_no_steps', $service->enroll($this->makeSequence([]), $lead)->reason);

        $default = MailSetting::defaultAccount();
        $result = $service->enroll($this->makeSequence(), $lead);
        $this->assertSame($default->id, $result->enrollment->mail_setting_id, 'Falls back to the default Mail Settings account.');

        MailSetting::query()->delete();
        $this->assertSame('no_mail_account', $service->enroll($this->makeSequence(), $this->makeLead())->reason);
    }

    public function test_pause_resume_continue_from_the_current_step_and_remove_can_be_undone(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-03-03 10:00:00', 'UTC'));
        $sequence = $this->makeSequence([['days' => 0], ['days' => 2], ['days' => 2]]);
        $service = app(EnrollmentService::class);
        $e = $this->enroll($sequence, $this->makeLead());
        app(SequenceEmailProcessor::class)->process($e->id);

        $this->assertTrue($service->pause($e->refresh()));
        $this->assertSame(EnrollmentStatus::Paused, $e->status);
        $this->assertFalse($service->pause($e), 'Already paused.');

        $this->travel(5)->days();   // step 2 is long overdue
        $this->assertTrue($service->resume($e->refresh()));
        $this->assertSame(EnrollmentStatus::Active, $e->status);
        $this->assertSame(1, $e->current_step, 'Resumes after step 1, not from the start.');
        $this->assertTrue($e->next_action_at->lte(now()));

        $this->assertTrue($service->remove($e));
        $this->assertSame(EnrollmentStatus::Removed, $e->status);
        $this->assertSame('manually_stopped', $e->stop_reason);
        $this->assertNull($e->next_action_at);

        // Re-enrolling continues where they stopped.
        $again = $service->enroll($sequence, $e->lead, $e->mailSetting);
        $this->assertSame('reactivated', $again->outcome);
        $this->assertSame(1, $again->enrollment->current_step);
        $this->assertSame(EnrollmentStatus::Active, $again->enrollment->status);
    }

    public function test_changing_a_leads_email_status_stops_its_sequences_and_unsubscribe_is_permanent(): void
    {
        $lead = $this->makeLead();
        $e = $this->enroll($this->makeSequence([['days' => 2]]), $lead);
        $service = app(EnrollmentService::class);

        $this->assertTrue($service->setContactStatus($lead, ContactStatus::Archived));
        $this->assertSame(EnrollmentStatus::Removed, $e->refresh()->status);
        $this->assertSame('contact_inactive', $e->stop_reason);

        $this->assertTrue($service->setContactStatus($lead, ContactStatus::Active));
        $this->assertTrue($service->setContactStatus($lead, ContactStatus::Unsubscribed));
        $this->assertFalse($service->setContactStatus($lead, ContactStatus::Active), 'Consent withdrawn stays withdrawn.');
        $this->assertSame(ContactStatus::Unsubscribed, $lead->refresh()->contact_status);
    }

    public function test_web_pause_resume_remove_retry_actions(): void
    {
        $sequence = $this->makeSequence([['days' => 0], ['days' => 1]]);
        $e = $this->enroll($sequence, $this->makeLead(['email' => 'findme@x.test']));

        $this->post("/outreach/enrollments/{$e->id}/pause")->assertSessionHas('success');
        $this->assertSame(EnrollmentStatus::Paused, $e->refresh()->status);
        $this->post("/outreach/enrollments/{$e->id}/resume")->assertSessionHas('success');
        $this->assertSame(EnrollmentStatus::Active, $e->refresh()->status);

        $this->post("/outreach/enrollments/{$e->id}/retry")->assertSessionHas('error');   // not failed

        $e->forceFill(['status' => EnrollmentStatus::Failed, 'stop_reason' => 'send_failed'])->save();
        $this->post("/outreach/enrollments/{$e->id}/retry")->assertSessionHas('success');
        $this->assertSame(EnrollmentStatus::Active, $e->refresh()->status);

        $this->get("/outreach/sequences/{$sequence->id}/enrollments?q=findme")->assertOk()->assertSee('findme@x.test');
        $this->get("/outreach/sequences/{$sequence->id}/enrollments?status=paused")->assertOk()->assertDontSee('findme@x.test');

        $this->post("/outreach/enrollments/{$e->id}/remove")->assertSessionHas('success');
        $this->assertSame(EnrollmentStatus::Removed, $e->refresh()->status);
    }

    // ── bulk actions run through the existing Bulks module ─────────────────

    public function test_enrolling_a_whole_category_is_a_queued_bulk_run(): void
    {
        $sequence = $this->makeSequence();
        $category = $this->makeCategory('Agencies');
        $leads = collect(range(1, 5))->map(fn () => $this->makeLead(['category_id' => $category->id]));
        $extra = $this->makeLead();                    // member through the pivot only
        $category->addLeads([$extra->id]);
        $unsubscribed = $this->makeLead(['category_id' => $category->id]);
        $unsubscribed->forceFill(['contact_status' => 'unsubscribed'])->save();

        Queue::fake();
        $this->post("/outreach/sequences/{$sequence->id}/enrollments", ['category_id' => $category->id])->assertRedirect();
        Queue::assertPushed(ProcessBulkJob::class);
        $this->assertSame(0, SequenceEnrollment::count(), 'Never done inside the request.');

        $bulk = Bulk::firstOrFail();
        $this->assertSame(Bulk::TYPE_ENROLL, $bulk->type);
        $this->assertSame(7, $bulk->total_records);
        $this->assertSame(Bulk::STATUS_PENDING, $bulk->status);

        // Now the worker: each run handles one chunk and re-queues itself until done.
        do {
            app()->call([new ProcessBulkJob($bulk->id), 'handle']);
        } while ($bulk->refresh()->isRunning());

        $bulk->refresh();
        $this->assertSame(Bulk::STATUS_COMPLETED, $bulk->status);
        $this->assertSame(6, $bulk->successful_records);
        $this->assertSame(1, $bulk->failed_records);
        $this->assertSame(6, SequenceEnrollment::count());
        $this->assertSame('lead unsubscribed', $bulk->items()->where('result_status', 'skipped')->value('message'));

        $this->get("/bulks/{$bulk->id}")->assertOk()->assertSee('Enroll in');
        $this->getJson("/bulks/{$bulk->id}/status")->assertJsonPath('progress', 100)->assertJsonPath('breakdown.done', 6)->assertJsonPath('breakdown.skipped', 1);
        $this->get('/bulks')->assertOk()->assertSee('Enroll in sequence');
        $this->get("/bulks/{$bulk->id}/export")->assertOk();
    }

    public function test_bulk_pause_resume_remove_and_unsubscribe(): void
    {
        $sequence = $this->makeSequence([['days' => 0], ['days' => 1]]);
        $enrollments = collect(range(1, 4))->map(fn () => $this->enroll($sequence, $this->makeLead()));

        $this->post("/outreach/sequences/{$sequence->id}/enrollments/bulk", ['action' => 'pause', 'all' => 1])->assertRedirect();
        $this->assertSame(4, SequenceEnrollment::where('status', 'paused')->count());

        $ids = $enrollments->take(2)->pluck('id')->all();
        $this->post("/outreach/sequences/{$sequence->id}/enrollments/bulk", ['action' => 'resume', 'ids' => $ids]);
        $this->assertSame(2, SequenceEnrollment::where('status', 'active')->count());

        $this->post("/outreach/sequences/{$sequence->id}/enrollments/bulk", ['action' => 'remove', 'ids' => $ids]);
        $this->assertSame(2, SequenceEnrollment::where('status', 'removed')->count());

        $this->post("/outreach/sequences/{$sequence->id}/enrollments/bulk", ['action' => 'pause'])->assertSessionHas('error');

        // An id from another sequence is ignored.
        $foreign = $this->enroll($this->makeSequence(), $this->makeLead());
        $this->post("/outreach/sequences/{$sequence->id}/enrollments/bulk", ['action' => 'remove', 'ids' => [$foreign->id]]);
        $this->assertSame(EnrollmentStatus::Active, $foreign->refresh()->status);

        // From the Leads page.
        $this->post('/leads/bulk-unsubscribe', ['ids' => $enrollments->pluck('lead_id')->all()])->assertRedirect();
        $this->assertSame(4, Lead::where('contact_status', 'unsubscribed')->count());
        $this->assertSame(0, SequenceEnrollment::where('sequence_id', $sequence->id)->whereIn('status', ['active', 'paused', 'pending'])->count(), 'Unsubscribing stops everything.');
    }

    // ── scheduler ──────────────────────────────────────────────────────────

    public function test_the_scheduler_dispatches_due_active_enrollments_and_never_sends(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-03-03 10:00:00', 'UTC'));
        $sequence = $this->makeSequence([['days' => 0], ['days' => 1]]);

        $due = $this->enroll($sequence, $this->makeLead());
        $this->enroll($sequence, $this->makeLead())->forceFill(['next_action_at' => now()->addHour()])->save();
        $this->enroll($sequence, $this->makeLead())->forceFill(['status' => 'paused'])->save();
        $this->enroll($sequence, $this->makeLead())->forceFill(['status' => 'replied'])->save();
        $this->enroll($this->makeSequence([['days' => 0]], active: false), $this->makeLead());

        $pausedSeq = $this->makeSequence([['days' => 0]]);
        $this->enroll($pausedSeq, $this->makeLead());
        $pausedSeq->forceFill(['status' => SequenceStatus::Paused])->save();

        Queue::fake();
        $this->artisan('sequencer:dispatch-due')->assertSuccessful();

        Queue::assertPushed(SendSequenceEmailJob::class, 1);
        Queue::assertPushed(SendSequenceEmailJob::class, fn ($job) => $job->enrollmentId === $due->id && $job->queue === 'emails');
        $this->assertCount(0, FakeEmailProvider::$sent, 'The scheduler must NEVER send mail itself.');
        $this->assertSame(0, LeadEmail::count());
        $this->assertNotNull($due->refresh()->dispatch_lease_until);
    }

    public function test_overlapping_scheduler_runs_do_not_queue_the_same_enrollment_twice(): void
    {
        $this->enroll($this->makeSequence(), $this->makeLead());

        Queue::fake();
        $this->artisan('sequencer:dispatch-due');
        $this->artisan('sequencer:dispatch-due');   // next minute's run while the job is still queued
        $this->artisan('sequencer:dispatch-due');

        Queue::assertPushed(SendSequenceEmailJob::class, 1);

        // A lost job (worker crash, Redis flush) is re-dispatched once its lease expires.
        $this->travel(6)->minutes();
        $this->artisan('sequencer:dispatch-due');
        Queue::assertPushed(SendSequenceEmailJob::class, 2);
    }

    public function test_the_scheduler_dispatches_no_more_than_the_account_rate_per_run(): void
    {
        $account = $this->makeAccount(['rate_limit_per_minute' => 3]);
        $sequence = $this->makeSequence();
        foreach (range(1, 8) as $_) {
            $this->enroll($sequence, $this->makeLead(), $account);
        }

        Queue::fake();
        $this->artisan('sequencer:dispatch-due');

        Queue::assertPushed(SendSequenceEmailJob::class, 3);
    }

    public function test_end_to_end_cron_to_queue_to_smtp(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-03-03 10:00:00', 'UTC'));   // queue connection is "sync" in tests
        $sequence = $this->makeSequence([['days' => 0, 'subject' => 'First'], ['days' => 1, 'subject' => 'Second']]);
        $e = $this->enroll($sequence, $this->makeLead());

        // The scheduler (cron -> schedule:run) registers the dispatcher every minute and reply checking...
        $events = collect(app(Schedule::class)->events());
        $dispatch = $events->first(fn ($ev) => str_contains((string) $ev->command, 'sequencer:dispatch-due'));
        $this->assertNotNull($dispatch, 'sequencer:dispatch-due must be scheduled.');
        $this->assertSame('* * * * *', $dispatch->expression);
        $this->assertNotNull($events->first(fn ($ev) => str_contains((string) $ev->command, 'emails:check-replies')));

        // ...which queues jobs that the worker (sync connection in tests) delivers.
        $this->artisan('sequencer:dispatch-due')->assertSuccessful();
        $this->assertCount(1, FakeEmailProvider::$sent);
        $this->assertSame('First', FakeEmailProvider::$sent[0]->subject);

        $this->travel(1)->minutes();
        $this->artisan('sequencer:dispatch-due');
        $this->assertCount(1, FakeEmailProvider::$sent, 'Nothing is due a minute later.');

        $this->travel(1)->days();
        $this->artisan('sequencer:dispatch-due');
        $this->assertCount(2, FakeEmailProvider::$sent);
        $this->assertSame(EnrollmentStatus::Completed, $e->refresh()->status);

        $this->travel(5)->days();
        $this->artisan('sequencer:dispatch-due');
        $this->assertCount(2, FakeEmailProvider::$sent, 'No further job is scheduled after completion.');
    }

    public function test_the_job_itself_is_unique_per_enrollment_and_safe_to_run_with_stale_data(): void
    {
        $job = new SendSequenceEmailJob(42);
        $this->assertSame('sequence-send-42', $job->uniqueId());
        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertSame('emails', $job->queue, 'Shares the existing "emails" queue.');

        // A job for an enrollment that no longer exists is a harmless no-op.
        $job->handle(app(SequenceEmailProcessor::class));
        $this->assertCount(0, FakeEmailProvider::$sent);
    }
}
