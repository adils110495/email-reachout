<?php

namespace Tests\Feature\Sequencer;

use App\Models\Bulk;
use App\Models\Lead;
use App\Models\LeadEmail;
use App\Models\SequenceEnrollment;
use App\Sequencer\Enums\ContactStatus;
use App\Sequencer\Services\SequenceEmailProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\CreatesSequencerData;
use Tests\TestCase;

/** /api/v1: contacts = leads, lists = categories, email-logs = lead emails, email-accounts = mail settings. */
class ApiTest extends TestCase
{
    use CreatesSequencerData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeTransports();
    }

    private function token($user): string
    {
        return $this->postJson('/api/v1/auth/login', ['login' => $user->username, 'password' => 'secret-password'])
            ->assertOk()->json('token');
    }

    private function api(string $token): static
    {
        return $this->withHeaders(['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json']);
    }

    public function test_login_me_logout(): void
    {
        $user = $this->makeUser();

        $this->postJson('/api/v1/auth/login', ['login' => $user->username, 'password' => 'wrong'])->assertStatus(422);
        $res = $this->postJson('/api/v1/auth/login', ['login' => $user->email, 'password' => 'secret-password'])->assertOk();
        $res->assertJsonStructure(['token', 'token_type', 'expires_at', 'user' => ['id', 'name', 'timezone']]);
        $token = $res->json('token');

        $this->api($token)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('user.id', $user->id);
        $this->api($token)->postJson('/api/v1/auth/logout')->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => 'Bearer '.$token])->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->getJson('/api/v1/contacts')->assertUnauthorized()->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_login_is_rate_limited(): void
    {
        $user = $this->makeUser();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['login' => $user->username, 'password' => 'bad']);
        }
        $this->postJson('/api/v1/auth/login', ['login' => $user->username, 'password' => 'bad'])->assertStatus(429);
    }

    public function test_contacts_are_leads_crud_filter_and_partial_update(): void
    {
        $t = $this->token($this->makeUser());

        $id = $this->api($t)->postJson('/api/v1/contacts', ['email' => 'API@x.test', 'first_name' => 'Api', 'custom_fields' => ['plan' => 'pro']])
            ->assertCreated()->assertJsonPath('data.email', 'api@x.test')->assertJsonPath('data.custom_fields.plan', 'pro')
            ->assertJsonPath('data.outreach_status', 'new')->json('data.id');
        $this->assertSame('Api', Lead::find($id)->first_name, 'Stored as a lead, visible on the Leads page.');

        $this->api($t)->postJson('/api/v1/contacts', ['email' => 'api@x.test'])->assertStatus(422)->assertJsonValidationErrors('email');

        $this->api($t)->patchJson("/api/v1/contacts/$id", ['company' => 'Patched'])->assertOk()
            ->assertJsonPath('data.company', 'Patched')->assertJsonPath('data.first_name', 'Api');
        $this->api($t)->getJson("/api/v1/contacts/$id")->assertOk()->assertJsonPath('data.status', 'active');
        $this->api($t)->getJson('/api/v1/contacts?q=api&per_page=500')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.per_page', 100);

        $this->api($t)->deleteJson("/api/v1/contacts/$id")->assertNoContent();
        $this->assertDatabaseMissing('leads', ['id' => $id]);
    }

    public function test_lists_are_categories_with_membership(): void
    {
        $t = $this->token($this->makeUser());
        $lead = $this->makeLead();

        $listId = $this->api($t)->postJson('/api/v1/lists', ['name' => 'SaaS'])->assertCreated()->json('data.id');
        $this->assertDatabaseHas('categories', ['id' => $listId, 'name' => 'SaaS']);
        $this->api($t)->postJson("/api/v1/lists/$listId/contacts", ['contact_ids' => [$lead->id]])->assertOk()->assertJsonPath('added', 1);
        $this->api($t)->getJson("/api/v1/lists/$listId")->assertJsonPath('data.contacts_count', 1);
        $this->api($t)->getJson("/api/v1/lists/$listId/contacts")->assertJsonCount(1, 'data');
        $this->api($t)->getJson("/api/v1/contacts?list_id=$listId")->assertJsonCount(1, 'data');
        $this->api($t)->deleteJson("/api/v1/lists/$listId/contacts", ['contact_ids' => [$lead->id]])->assertJsonPath('removed', 1);
        $this->api($t)->putJson("/api/v1/lists/$listId", ['name' => 'SaaS companies'])->assertJsonPath('data.name', 'SaaS companies');
        $this->api($t)->deleteJson("/api/v1/lists/$listId")->assertNoContent();
        $this->assertDatabaseHas('leads', ['id' => $lead->id]);
    }

    public function test_sequence_lifecycle_steps_enrollment_pause_resume_and_logs(): void
    {
        $user = $this->makeUser();
        $account = $this->makeAccount();
        $t = $this->token($user);

        $seqId = $this->api($t)->postJson('/api/v1/sequences', [
            'name' => 'API seq', 'timezone' => 'UTC', 'sending_start_time' => '00:00', 'sending_end_time' => '23:59',
            'sending_days' => [1, 2, 3, 4, 5, 6, 7], 'daily_limit' => 50, 'mail_setting_id' => $account->id,
        ])->assertCreated()->assertJsonPath('data.status', 'draft')->json('data.id');

        $this->api($t)->postJson("/api/v1/sequences/$seqId/activate")->assertStatus(422);   // no steps yet

        $s1 = $this->api($t)->postJson("/api/v1/sequences/$seqId/steps", ['subject' => 'Hi {{first_name}}', 'body' => 'Hello'])->assertCreated()->json('data.id');
        $s2 = $this->api($t)->postJson("/api/v1/sequences/$seqId/steps", ['subject' => 'Bump', 'body' => 'Again', 'delay_days' => 2])->assertCreated()->assertJsonPath('data.step_number', 2)->json('data.id');
        $this->api($t)->patchJson("/api/v1/steps/$s2", ['delay_days' => 3])->assertOk()->assertJsonPath('data.delay_days', 3)->assertJsonPath('data.subject', 'Bump');
        $this->api($t)->postJson("/api/v1/sequences/$seqId/steps/reorder", ['order' => [$s2, $s1]])->assertOk()->assertJsonPath('data.0.id', $s2);
        $this->api($t)->postJson("/api/v1/sequences/$seqId/steps/reorder", ['order' => [$s1, $s2]])->assertOk();
        $this->api($t)->getJson("/api/v1/sequences/$seqId/steps")->assertJsonCount(2, 'data');

        $this->api($t)->postJson("/api/v1/sequences/$seqId/activate")->assertOk()->assertJsonPath('data.status', 'active');

        $c1 = $this->makeLead();
        $c2 = $this->makeLead();
        $c2->forceFill(['contact_status' => ContactStatus::Unsubscribed])->save();
        $this->api($t)->postJson("/api/v1/sequences/$seqId/enrollments", ['contact_ids' => [$c1->id, $c2->id]])->assertCreated()
            ->assertJsonFragment(['contact_id' => $c1->id, 'outcome' => 'enrolled'])
            ->assertJsonFragment(['contact_id' => $c2->id, 'outcome' => 'skipped', 'reason' => 'lead_unsubscribed']);

        $enrollmentId = SequenceEnrollment::firstOrFail()->id;
        $this->api($t)->postJson("/api/v1/enrollments/$enrollmentId/pause")->assertJsonPath('data.status', 'paused');
        $this->api($t)->postJson("/api/v1/enrollments/$enrollmentId/resume")->assertJsonPath('data.status', 'active');
        $this->api($t)->getJson("/api/v1/sequences/$seqId/enrollments?status=active")->assertJsonCount(1, 'data');

        app(SequenceEmailProcessor::class)->process($enrollmentId);
        $logId = LeadEmail::firstOrFail()->id;
        $this->api($t)->getJson('/api/v1/email-logs')->assertOk()->assertJsonPath('data.0.status', 'sent')
            ->assertJsonPath('data.0.delivery_status', 'sent')
            ->assertJsonMissingPath('data.0.body')->assertJsonMissingPath('data.0.tracking_token');
        $this->api($t)->getJson("/api/v1/email-logs/$logId")->assertOk()->assertJsonPath('data.subject', 'Hi Alex');
        $this->api($t)->getJson("/api/v1/sequences/$seqId/analytics")->assertOk()->assertJsonPath('emails.sent', 1)->assertJsonPath('rates.open', 0);

        $this->api($t)->postJson("/api/v1/sequences/$seqId/pause")->assertJsonPath('data.status', 'paused');
        $this->api($t)->postJson("/api/v1/sequences/$seqId/resume")->assertJsonPath('data.status', 'active');
        $this->api($t)->deleteJson("/api/v1/enrollments/$enrollmentId")->assertJsonPath('data.status', 'removed');

        $this->api($t)->getJson('/api/v1/email-accounts')->assertOk()->assertJsonMissingPath('data.0.password')
            ->assertJsonPath('data.0.from_email', $account->from_address)->assertJsonPath('data.0.is_default', true);
        $body = $this->api($t)->getJson('/api/v1/email-accounts/'.$account->id)->getContent();
        $this->assertStringNotContainsString('smtp-secret', $body);
        $this->assertStringNotContainsString('imap-secret', $body);
    }

    public function test_large_api_enrollments_are_queued_as_a_bulk(): void
    {
        $user = $this->makeUser();
        $this->makeAccount();
        $sequence = $this->makeSequence();
        $ids = collect(range(1, 60))->map(fn () => $this->makeLead()->id)->all();

        Queue::fake();
        $res = $this->api($this->token($user))->postJson("/api/v1/sequences/{$sequence->id}/enrollments", ['contact_ids' => $ids])
            ->assertStatus(202)->assertJsonPath('total', 60);
        $this->assertSame(0, SequenceEnrollment::count());
        $this->assertSame(Bulk::TYPE_ENROLL, Bulk::find($res->json('bulk_id'))->type, 'Visible on the Bulks page.');

        $category = $this->makeCategory();
        $category->addLeads(array_slice($ids, 0, 5));
        $this->api($this->token($user))->postJson("/api/v1/sequences/{$sequence->id}/enrollments", ['list_id' => $category->id])
            ->assertStatus(202)->assertJsonPath('total', 5);
    }

    public function test_data_is_shared_by_the_team_but_needs_a_token(): void
    {
        $sequence = $this->makeSequence();
        $lead = $this->makeLead();
        $enrollment = $this->enroll($sequence, $lead);
        app(SequenceEmailProcessor::class)->process($enrollment->id);
        $log = LeadEmail::firstOrFail();

        $teammate = $this->token($this->makeUser());
        $this->api($teammate)->getJson("/api/v1/contacts/{$lead->id}")->assertOk();
        $this->api($teammate)->getJson("/api/v1/sequences/{$sequence->id}")->assertOk();
        $this->api($teammate)->getJson("/api/v1/email-logs/{$log->id}")->assertOk();

        $this->app['auth']->forgetGuards();
        foreach (["/api/v1/contacts/{$lead->id}", "/api/v1/sequences/{$sequence->id}", "/api/v1/email-logs/{$log->id}", '/api/v1/email-accounts'] as $url) {
            $this->withHeaders(['Authorization' => 'Bearer not-a-token'])->getJson($url)->assertUnauthorized();
        }
        $this->getJson('/api/v1/contacts/999999')->assertUnauthorized();
    }

    public function test_mass_assignment_cannot_set_protected_columns(): void
    {
        $t = $this->token($this->makeUser());

        $id = $this->api($t)->postJson('/api/v1/contacts', [
            'email' => 'm@x.test', 'contact_status' => 'bounced', 'status' => 'replied', 'unsubscribe_token' => 'chosen-token-0000000000000000000000000',
        ])->assertCreated()->json('data.id');

        $lead = Lead::find($id);
        $this->assertSame(ContactStatus::Active, $lead->contact_status);
        $this->assertSame('new', $lead->status);
        $this->assertNotSame('chosen-token-0000000000000000000000000', $lead->unsubscribe_token);
    }
}
