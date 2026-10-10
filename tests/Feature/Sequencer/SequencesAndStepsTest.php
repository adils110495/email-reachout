<?php

namespace Tests\Feature\Sequencer;

use App\Models\EmailTemplate;
use App\Models\Sequence;
use App\Models\SequenceStep;
use App\Sequencer\Enums\EnrollmentStatus;
use App\Sequencer\Enums\SendOutcome;
use App\Sequencer\Enums\SequenceStatus;
use App\Sequencer\Services\EnrollmentService;
use App\Sequencer\Services\SequenceEmailProcessor;
use App\Sequencer\Services\StepService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesSequencerData;
use Tests\TestCase;

class SequencesAndStepsTest extends TestCase
{
    use CreatesSequencerData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeTransports();
        $this->actingAs($this->makeUser());
    }

    private function sequencePayload(array $over = []): array
    {
        return $over + [
            'name' => 'Cold outreach', 'description' => 'Q4', 'timezone' => 'Europe/London',
            'sending_start_time' => '09:00', 'sending_end_time' => '17:00', 'sending_days' => [1, 2, 3, 4, 5],
            'daily_limit' => 100, 'track_opens' => '1', 'track_clicks' => '1',
        ];
    }

    // ── sequences ──────────────────────────────────────────────────────────

    public function test_create_validate_and_update_a_sequence(): void
    {
        $account = $this->makeAccount();

        $this->post('/outreach/sequences', $this->sequencePayload(['mail_setting_id' => $account->id]))->assertRedirect();
        $sequence = Sequence::firstOrFail();
        $this->assertSame(SequenceStatus::Draft, $sequence->status);
        $this->assertSame('Europe/London', $sequence->timezone);
        $this->assertSame([1, 2, 3, 4, 5], $sequence->sending_days);
        $this->assertSame($account->id, $sequence->mail_setting_id, 'Sends from a Mail Settings account.');
        $this->assertSame(auth()->id(), $sequence->created_by);

        foreach ([
            ['sending_end_time' => '08:00'], ['timezone' => 'Nowhere/Land'], ['sending_days' => []],
            ['daily_limit' => 0], ['name' => ''], ['mail_setting_id' => 99999],
        ] as $bad) {
            $this->post('/outreach/sequences', $this->sequencePayload($bad))->assertSessionHasErrors(array_key_first($bad));
        }

        $this->put("/outreach/sequences/{$sequence->id}", $this->sequencePayload(['name' => 'Renamed', 'daily_limit' => 25]))->assertRedirect();
        $this->assertSame(25, $sequence->refresh()->daily_limit);
        $this->get("/outreach/sequences/{$sequence->id}")->assertOk()->assertSee('Renamed');
        $this->get('/outreach/sequences?status=draft')->assertOk()->assertSee('Renamed');
        $this->get('/outreach/sequences?status=active')->assertOk()->assertDontSee('Renamed');
        $this->get("/outreach/sequences/{$sequence->id}/edit")->assertOk();
        $this->get("/outreach/sequences/{$sequence->id}/analytics")->assertOk();
        $this->get('/outreach/sequences/create')->assertOk();
    }

    public function test_activation_needs_a_step_and_pause_resume_toggle_state(): void
    {
        $sequence = $this->makeSequence([], active: false);

        $this->post("/outreach/sequences/{$sequence->id}/activate")->assertSessionHas('error');
        $this->assertSame(SequenceStatus::Draft, $sequence->refresh()->status);

        $this->post("/outreach/sequences/{$sequence->id}/steps", ['subject' => 'S', 'body' => 'B'])->assertRedirect();
        $this->post("/outreach/sequences/{$sequence->id}/activate")->assertSessionHas('success');
        $this->assertSame(SequenceStatus::Active, $sequence->refresh()->status);
        $this->assertNotNull($sequence->activated_at);

        $this->post("/outreach/sequences/{$sequence->id}/activate")->assertSessionHas('success');   // double click is harmless

        $this->post("/outreach/sequences/{$sequence->id}/pause");
        $this->assertSame(SequenceStatus::Paused, $sequence->refresh()->status);
        $this->post("/outreach/sequences/{$sequence->id}/resume");
        $this->assertSame(SequenceStatus::Active, $sequence->refresh()->status);

        $this->post("/outreach/sequences/{$sequence->id}/archive");
        $this->assertSame(SequenceStatus::Archived, $sequence->refresh()->status);
        $this->post("/outreach/sequences/{$sequence->id}/activate")->assertSessionHas('error');
    }

    public function test_activating_a_draft_starts_leads_enrolled_while_it_was_a_draft(): void
    {
        $this->makeAccount();
        $sequence = $this->makeSequence([['days' => 0]], active: false);
        $enrollment = $this->enroll($sequence, $this->makeLead());

        $this->assertSame(EnrollmentStatus::Pending, $enrollment->status);
        $this->assertNull($enrollment->next_action_at);
        $this->assertSame(SendOutcome::Skipped, app(SequenceEmailProcessor::class)->process($enrollment->id), 'Nothing is sent from a draft.');

        $this->post("/outreach/sequences/{$sequence->id}/activate");

        $enrollment->refresh();
        $this->assertSame(EnrollmentStatus::Active, $enrollment->status);
        $this->assertNotNull($enrollment->next_action_at);
    }

    public function test_duplicating_a_sequence_copies_steps_but_not_enrollments(): void
    {
        $this->makeAccount();
        $sequence = $this->makeSequence([['subject' => 'One'], ['subject' => 'Two', 'days' => 3]]);
        $this->enroll($sequence, $this->makeLead());

        $this->post("/outreach/sequences/{$sequence->id}/duplicate")->assertRedirect();

        $copy = Sequence::where('id', '!=', $sequence->id)->firstOrFail();
        $this->assertSame(SequenceStatus::Draft, $copy->status);
        $this->assertSame(['One', 'Two'], $copy->steps->pluck('subject')->all());
        $this->assertSame(3, $copy->steps[1]->delay_days);
        $this->assertSame(0, $copy->enrollments()->count());
    }

    public function test_deleting_a_sequence_keeps_its_sent_emails_in_email_activity(): void
    {
        $this->makeAccount();
        $sequence = $this->makeSequence();
        $enrollment = $this->enroll($sequence, $this->makeLead());
        app(SequenceEmailProcessor::class)->process($enrollment->id);

        $this->delete("/outreach/sequences/{$sequence->id}")->assertRedirect('/outreach/sequences');

        $this->assertDatabaseMissing('sequences', ['id' => $sequence->id]);
        $this->assertDatabaseMissing('sequence_enrollments', ['id' => $enrollment->id]);
        $this->assertDatabaseHas('lead_emails', ['lead_id' => $enrollment->lead_id, 'status' => 'sent', 'sequence_id' => null]);
    }

    // ── steps ──────────────────────────────────────────────────────────────

    public function test_add_edit_delete_steps_and_renumbering(): void
    {
        $sequence = $this->makeSequence([], active: false);

        foreach ([['Step A', 0], ['Step B', 2], ['Step C', 3]] as [$subject, $days]) {
            $this->post("/outreach/sequences/{$sequence->id}/steps", ['subject' => $subject, 'body' => 'Body', 'delay_days' => $days])->assertRedirect();
        }
        $steps = $sequence->steps()->get();
        $this->assertSame([1, 2, 3], $steps->pluck('step_number')->all());
        $this->assertSame(2 * 1440, $steps[1]->delayInMinutes());
        $this->assertSame('2 days', $steps[1]->delayLabel());

        $this->get("/outreach/sequences/{$sequence->id}/steps/create")->assertOk();
        $this->get("/outreach/steps/{$steps[1]->id}/edit")->assertOk();

        $this->put("/outreach/steps/{$steps[1]->id}", ['subject' => 'Step B2', 'body' => 'X', 'delay_days' => 1, 'delay_hours' => 12, 'delay_minutes' => 30, 'status' => 'active'])->assertRedirect();
        $this->assertSame(1440 + 720 + 30, $steps[1]->refresh()->delayInMinutes());
        $this->assertSame('1 day 12 hours 30 minutes', $steps[1]->delayLabel());

        $this->delete("/outreach/steps/{$steps[0]->id}");
        $this->assertSame(['Step B2', 'Step C'], $sequence->steps()->pluck('subject')->all());
        $this->assertSame([1, 2], $sequence->steps()->pluck('step_number')->all());

        $this->post("/outreach/sequences/{$sequence->id}/steps", ['subject' => '', 'body' => ''])->assertSessionHasErrors(['subject', 'body']);
        $this->post("/outreach/sequences/{$sequence->id}/steps", ['subject' => 'S', 'body' => 'B', 'delay_hours' => 99])->assertSessionHasErrors('delay_hours');
    }

    public function test_a_step_can_be_built_from_an_existing_email_template(): void
    {
        // The Email Templates module (Settings > Email Templates) is the template library.
        $template = EmailTemplate::create(['name' => 'Intro', 'subject' => 'Hi {{first_name}}', 'body' => '<p>From the template</p>', 'status' => 'active']);
        $inactive = EmailTemplate::create(['name' => 'Retired', 'subject' => 's', 'body' => 'b', 'status' => 'inactive']);
        $sequence = $this->makeSequence([], active: false);

        $this->get("/outreach/sequences/{$sequence->id}/steps/create")->assertOk()->assertSee('Intro')->assertDontSee('Retired');

        $this->post("/outreach/sequences/{$sequence->id}/steps", ['template_id' => $template->id, 'subject' => '', 'body' => ''])->assertSessionHasNoErrors();

        $step = $sequence->steps()->firstOrFail();
        $this->assertSame('Hi {{first_name}}', $step->subject);
        $this->assertSame('<p>From the template</p>', $step->body);

        $this->post("/outreach/sequences/{$sequence->id}/steps", ['template_id' => $inactive->id])->assertSessionHasErrors('template_id');
        $this->assertFalse(Schema::hasTable('message_templates'), 'There is no second templates table.');
    }

    public function test_reorder_duplicate_and_move(): void
    {
        $sequence = $this->makeSequence([['subject' => 'A'], ['subject' => 'B'], ['subject' => 'C']], active: false);
        [$a, , $c] = $sequence->steps->all();

        $this->post("/outreach/steps/{$c->id}/move", ['direction' => 'up']);
        $this->assertSame(['A', 'C', 'B'], $sequence->steps()->pluck('subject')->all());
        $this->post("/outreach/steps/{$a->id}/move", ['direction' => 'up']);   // already first: no-op
        $this->assertSame(['A', 'C', 'B'], $sequence->steps()->pluck('subject')->all());

        $this->post("/outreach/steps/{$a->id}/duplicate");
        $this->assertSame(['A', 'A', 'C', 'B'], $sequence->steps()->pluck('subject')->all());
        $this->assertSame([1, 2, 3, 4], $sequence->steps()->pluck('step_number')->all());

        $this->expectException(ValidationException::class);
        app(StepService::class)->reorder($sequence, array_slice($sequence->steps()->pluck('id')->all(), 1));   // missing a step
    }

    public function test_reordering_is_refused_while_leads_are_part_way_through(): void
    {
        $this->makeAccount();
        $sequence = $this->makeSequence([['subject' => 'A'], ['subject' => 'B', 'days' => 1]]);
        app(SequenceEmailProcessor::class)->process($this->enroll($sequence, $this->makeLead())->id);   // A is out

        $this->post("/outreach/steps/{$sequence->steps[1]->id}/move", ['direction' => 'up'])->assertSessionHas('error');
        $this->assertSame(['A', 'B'], $sequence->steps()->pluck('subject')->all());
    }

    public function test_deleting_a_step_keeps_in_flight_leads_on_the_right_step(): void
    {
        $this->makeAccount();
        $sequence = $this->makeSequence([['subject' => 'A'], ['subject' => 'B', 'days' => 1], ['subject' => 'C', 'days' => 1]]);
        $enrollment = $this->enroll($sequence, $this->makeLead());
        app(SequenceEmailProcessor::class)->process($enrollment->id);        // A sent, current_step = 1
        $this->travel(2)->days();
        app(SequenceEmailProcessor::class)->process($enrollment->id);        // B sent, current_step = 2

        // Remove A (already sent): the lead has now completed 1 step of the new 2-step layout (B, C).
        app(StepService::class)->delete($sequence->steps()->first());
        $this->assertSame(1, $enrollment->refresh()->current_step);
        $this->assertSame('C', app(EnrollmentService::class)->nextStepAfter($sequence->refresh(), 1)->subject);
    }

    public function test_preview_renders_variables_in_a_sandboxed_frame(): void
    {
        $this->makeAccount(['from_name' => 'Pat Sender']);
        $sequence = $this->makeSequence([['subject' => 'Hi {{first_name}} at {{company}}', 'body' => '<p>From {{sender_name}} {{oops}}</p>']], active: false);
        $step = $sequence->steps->first();

        $this->get("/outreach/steps/{$step->id}/preview")->assertOk()
            ->assertSee('Hi Alex at Acme Inc')->assertSee('Pat Sender')->assertSee('{{oops}}', false)->assertSee('sandbox', false);

        $lead = $this->makeLead(['first_name' => 'Real', 'company_name' => 'Realco']);
        $this->get("/outreach/steps/{$step->id}/preview?lead={$lead->id}")->assertSee('Hi Real at Realco');
    }

    public function test_guests_cannot_reach_sequences(): void
    {
        $sequence = $this->makeSequence();
        auth()->logout();

        $this->get('/outreach/sequences')->assertRedirect('/login');
        $this->post("/outreach/sequences/{$sequence->id}/pause")->assertRedirect('/login');
        $this->delete("/outreach/steps/{$sequence->steps->first()->id}")->assertRedirect('/login');
        $this->assertSame(1, SequenceStep::count());
    }
}
