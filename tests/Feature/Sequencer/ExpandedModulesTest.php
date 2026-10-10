<?php

namespace Tests\Feature\Sequencer;

use App\Jobs\ProcessBulkJob;
use App\Models\Bulk;
use App\Models\Category;
use App\Models\EmailTemplate;
use App\Models\Lead;
use App\Models\LeadEmail;
use App\Models\MailSetting;
use App\Sequencer\Enums\ContactStatus;
use App\Sequencer\Services\SequenceEmailProcessor;
use App\Services\MailConfigService;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\CreatesSequencerData;
use Tests\Support\FakeEmailProvider;
use Tests\TestCase;

/**
 * The existing modules the sequencer builds on: Leads (contacts), Categories (lists),
 * Mail Settings (sending accounts), Email Templates, Email Activity, Dashboard.
 */
class ExpandedModulesTest extends TestCase
{
    use CreatesSequencerData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeTransports();
        $this->actingAs($this->makeUser());
    }

    // ── Leads = contacts ───────────────────────────────────────────────────

    public function test_leads_carry_contact_fields_and_get_an_unsubscribe_token(): void
    {
        $category = $this->makeCategory('Prospects');
        $this->post('/leads', [
            'company_name' => 'Initech', 'website' => 'https://initech.test', 'emails' => ['dana@initech.test'], 'category_id' => $category->id,
            'first_name' => 'Dana', 'last_name' => 'Lee', 'job_title' => 'CEO', 'phone' => '+1 555', 'country' => 'US',
        ])->assertRedirect();

        $lead = Lead::firstOrFail();
        $this->assertSame('Dana', $lead->first_name);
        $this->assertSame('CEO', $lead->job_title);
        $this->assertSame(ContactStatus::Active, $lead->contact_status);
        $this->assertSame(48, strlen($lead->unsubscribe_token));
        $this->assertSame('Dana Lee', $lead->displayName());

        // The Leads page lists rows once a category is chosen.
        $this->get('/leads?category='.$category->id)->assertOk()->assertSee('Dana Lee')->assertSee('Initech');
        $this->getJson('/leads/'.$lead->id.'/edit')->assertJsonPath('first_name', 'Dana')->assertJsonPath('contact_status', 'active');
    }

    public function test_editing_a_lead_can_change_its_email_status_but_never_undo_an_unsubscribe(): void
    {
        $lead = $this->makeLead();
        $base = ['company_name' => 'Acme', 'website' => 'https://acme.test', 'emails' => [$lead->email], 'status' => 'new'];

        $this->put('/leads/'.$lead->id, $base + ['contact_status' => 'archived', 'first_name' => 'Zed'])->assertRedirect();
        $this->assertSame(ContactStatus::Archived, $lead->refresh()->contact_status);
        $this->assertSame('Zed', $lead->first_name);

        $lead->forceFill(['contact_status' => ContactStatus::Unsubscribed])->save();
        $this->put('/leads/'.$lead->id, $base + ['contact_status' => 'active']);
        $this->assertSame(ContactStatus::Unsubscribed, $lead->refresh()->contact_status);

        // A mass-assignment attempt cannot flip it either.
        $lead->update(['contact_status' => 'active', 'unsubscribe_token' => 'x']);
        $this->assertSame(ContactStatus::Unsubscribed, $lead->refresh()->contact_status);
        $this->assertNotSame('x', $lead->unsubscribe_token);
    }

    public function test_the_leads_compose_window_cannot_email_unsubscribed_leads_and_renders_template_variables(): void
    {
        Mail::fake();
        $this->makeAccount();
        $lead = $this->makeLead(['first_name' => 'Ana', 'company_name' => 'Globex']);

        $lead->forceFill(['contact_status' => ContactStatus::Unsubscribed])->save();
        $this->post('/send-email/'.$lead->id, ['subject' => 'Hi', 'body' => 'x'])->assertSessionHas('error');
        $this->assertSame(0, LeadEmail::count());

        $lead->forceFill(['contact_status' => ContactStatus::Active])->save();
        $this->post('/send-email/'.$lead->id, ['subject' => 'Hi {{first_name}}', 'body' => '<p>Hello {{company}}</p>']);

        $email = LeadEmail::firstOrFail();
        $this->assertSame('Hi Ana', $email->subject, 'Email Templates use the same {{variables}} as sequences.');
        $this->assertSame('<p>Hello Globex</p>', $email->body);
        $this->assertSame($lead->email, $email->to_email);
    }

    public function test_bulk_enroll_and_add_to_category_from_the_leads_page(): void
    {
        $this->makeAccount();
        $sequence = $this->makeSequence();
        $category = $this->makeCategory('SaaS');
        $leads = collect(range(1, 3))->map(fn () => $this->makeLead());
        $ids = $leads->pluck('id')->all();

        $this->post('/leads/bulk-category', ['ids' => $ids, 'category_id' => $category->id])->assertSessionHas('success');
        $this->assertSame(3, $category->leads()->count());
        $this->post('/leads/bulk-category', ['ids' => [$ids[0]], 'category_id' => $category->id, 'mode' => 'remove']);
        $this->assertSame(2, $category->leads()->count());

        Queue::fake();
        $this->post('/leads/bulk-enroll', ['ids' => $ids, 'sequence_id' => $sequence->id])->assertRedirect();
        $bulk = Bulk::firstOrFail();
        $this->assertSame(Bulk::TYPE_ENROLL, $bulk->type);
        $this->assertSame(3, $bulk->total_records);
        $this->assertSame(['sequence_id' => $sequence->id, 'mail_setting_id' => null], $bulk->options);
    }

    public function test_bulk_sequence_runs_show_the_lead_on_every_row_and_refresh_when_finished(): void
    {
        $this->makeAccount();
        $sequence = $this->makeSequence();
        $ann = $this->makeLead(['first_name' => 'Ann', 'last_name' => 'Lee', 'email' => 'ann@inbox.test']);
        $twin = $this->makeLead(['first_name' => 'Twin', 'last_name' => 'Row', 'email' => 'ann@inbox.test']);
        $bob = $this->makeLead(['first_name' => 'Bob', 'last_name' => 'Ray', 'email' => 'bob@inbox.test']);

        Queue::fake();
        $this->post('/leads/bulk-enroll', ['ids' => [$ann->id, $twin->id, $bob->id], 'sequence_id' => $sequence->id]);
        $bulk = Bulk::firstOrFail();
        $ajax = ['X-Requested-With' => 'XMLHttpRequest'];

        // Still queued: rows already name the lead and its address, and searching by email works.
        $this->assertSame('bob@inbox.test', $bulk->items()->orderByDesc('id')->value('extra'));
        $this->get("/bulks/{$bulk->id}")->assertOk()
            ->assertSee('Ann Lee')->assertSee('bob@inbox.test')
            ->assertSee('id="bulkCancelForm"', false)->assertSee('ajax-filters:reload');
        $this->get("/bulks/{$bulk->id}?q=bob@inbox", $ajax)->assertOk()
            ->assertSee('Bob Ray')->assertDontSee('Ann Lee')->assertDontSee('Confidence');

        app()->call([new ProcessBulkJob($bulk->id), 'handle']);

        // The second lead with the same address is skipped, with the reason on its row.
        $this->assertSame(2, $sequence->enrollments()->count());
        $this->get("/bulks/{$bulk->id}", $ajax)->assertOk()
            ->assertSee('Done')->assertSee('duplicate email in sequence')->assertDontSee('Queued');
        $this->get("/bulks/{$bulk->id}")->assertOk()->assertDontSee('id="bulkCancelForm"', false);
    }

    // ── Categories = lists ─────────────────────────────────────────────────

    public function test_a_lead_can_be_in_several_categories_and_the_primary_category_counts(): void
    {
        $agencies = $this->makeCategory('Agencies');
        $publishers = $this->makeCategory('Publishers');
        $lead = $this->makeLead(['category_id' => $agencies->id]);   // primary category, as the Leads page sets it

        $this->assertSame([$agencies->id], $lead->categories()->pluck('categories.id')->all(), 'Mirrored into the pivot.');
        $publishers->addLeads([$lead->id]);
        $this->assertSame(2, $lead->categories()->count());

        // The Leads category filter finds it through either membership.
        $this->get('/leads?category='.$publishers->id)->assertOk()->assertSee($lead->email);
        $this->get('/leads?category='.$agencies->id)->assertOk()->assertSee($lead->email);

        $this->post('/settings/categories', ['name' => 'SaaS Companies', 'description' => 'B2B software', 'status' => 'active'])->assertRedirect();
        $this->assertSame('B2B software', Category::where('name', 'SaaS Companies')->value('description'));
        $this->get('/settings/categories')->assertOk()->assertSee('B2B software');

        $publishers->delete();
        $this->assertDatabaseHas('leads', ['id' => $lead->id]);
    }

    // ── Mail Settings = sending accounts ───────────────────────────────────

    private function accountPayload(array $over = []): array
    {
        return $over + [
            'name' => 'Sales', 'provider' => 'smtp', 'from_name' => 'Sam', 'from_address' => 'sam@sender.test',
            'host' => 'smtp.sender.test', 'port' => 587, 'username' => 'sam', 'password' => 'S3cret-smtp', 'encryption' => 'tls',
            'imap_host' => 'imap.sender.test', 'imap_port' => 993, 'imap_username' => 'sam', 'imap_password' => 'S3cret-imap',
            'imap_encryption' => 'ssl', 'folder' => 'INBOX.Sent', 'imap_folder' => 'INBOX', 'rate_limit_per_minute' => 10, 'is_active' => '1',
        ];
    }

    public function test_mail_settings_hold_several_accounts_with_encrypted_passwords(): void
    {
        $this->post('/settings/mail', $this->accountPayload())->assertRedirect('/settings/mail');
        $this->post('/settings/mail', $this->accountPayload(['name' => 'Support', 'from_address' => 'help@sender.test']))->assertRedirect();

        $this->assertSame(2, MailSetting::count());
        $first = MailSetting::where('name', 'Sales')->firstOrFail();
        $this->assertTrue($first->is_default, 'The first account becomes the default.');
        $this->assertFalse(MailSetting::where('name', 'Support')->value('is_default'));

        $raw = DB::table('mail_settings')->where('id', $first->id)->first();
        $this->assertStringNotContainsString('S3cret', $raw->password.$raw->imap_password);
        $this->assertSame('S3cret-smtp', $first->password);
        $this->assertArrayNotHasKey('password', $first->toArray());

        foreach (['/settings/mail', '/settings/mail/create', '/settings/mail/'.$first->id.'/edit'] as $url) {
            $this->get($url)->assertOk()->assertDontSee('S3cret');
        }

        // Blank password on edit keeps the stored one.
        $this->put('/settings/mail/'.$first->id, $this->accountPayload(['name' => 'Sales EU', 'password' => '', 'imap_password' => '']))->assertRedirect();
        $first->refresh();
        $this->assertSame('Sales EU', $first->name);
        $this->assertSame('S3cret-smtp', $first->password);
        $this->assertSame('S3cret-imap', $first->imap_password);

        $support = MailSetting::where('name', 'Support')->firstOrFail();
        $this->post('/settings/mail/'.$support->id.'/default')->assertSessionHas('success');
        $this->assertSame($support->id, MailSetting::defaultAccount()->id);
        $this->assertSame(1, MailSetting::where('is_default', true)->count());
    }

    public function test_the_default_account_drives_the_leads_compose_window_and_sent_folder_copy(): void
    {
        $this->makeAccount(['name' => 'Old default', 'from_address' => 'old@sender.test']);
        $new = $this->makeAccount(['name' => 'New', 'from_address' => 'new@sender.test', 'imap_host' => 'imap.new.test', 'folder' => 'Sent Items']);
        $new->makeDefault();

        $config = app(MailConfigService::class);
        $config->applySmtp();
        $this->assertSame('new@sender.test', config('mail.from.address'));
        $this->assertSame('smtp.sender.test', config('mail.mailers.smtp.host'));
        $this->assertSame('imap.new.test', $config->imap()['host']);
        $this->assertSame('Sent Items', $config->imap()['folder']);
    }

    public function test_passwords_saved_under_an_old_app_key_do_not_break_mail_settings(): void
    {
        $account = $this->makeAccount();
        $foreign = (new Encrypter(random_bytes(32), 'AES-256-CBC'))->encryptString('old-secret');
        DB::table('mail_settings')->where('id', $account->id)->update(['password' => $foreign, 'imap_password' => $foreign]);
        $account->refresh();

        $this->assertNull($account->imap_password);
        $this->assertFalse($account->hasImap());
        $this->assertSame(['SMTP', 'IMAP'], $account->unreadableSecrets());

        $this->get('/settings/mail')->assertOk()->assertSee("password can't be read", false);
        $this->get('/settings/mail/'.$account->id.'/edit')->assertOk()->assertSee('Enter it again')->assertDontSee('leave blank to keep current');

        // Re-entering fixes it; leaving the IMAP password blank is not accepted while the stored one is unreadable.
        $this->put('/settings/mail/'.$account->id, $this->accountPayload(['password' => '', 'imap_password' => '']))->assertSessionHasErrors('imap_password');
        $this->put('/settings/mail/'.$account->id, $this->accountPayload(['password' => 'new-smtp', 'imap_password' => 'new-imap']))->assertSessionHasNoErrors();
        $account->refresh();
        $this->assertSame('new-imap', $account->imap_password);
        $this->assertSame([], $account->unreadableSecrets());
    }

    public function test_mail_settings_validation_and_ssrf_guard(): void
    {
        $this->post('/settings/mail', $this->accountPayload(['from_address' => 'nope', 'port' => 99999, 'encryption' => 'weird']))
            ->assertSessionHasErrors(['from_address', 'port', 'encryption']);
        $this->post('/settings/mail', $this->accountPayload(['imap_password' => '']))->assertSessionHasErrors('imap_password');

        config(['sequencer.allow_private_hosts' => false]);
        foreach (['127.0.0.1', '169.254.169.254', '10.0.0.5', 'bad host!'] as $host) {
            $this->post('/settings/mail', $this->accountPayload(['host' => $host]))->assertSessionHasErrors('host');
        }
    }

    public function test_test_connection_for_saved_and_unsaved_accounts(): void
    {
        $account = $this->makeAccount();

        FakeEmailProvider::$connection = null;
        $this->inbox->failWith = 'Login failed';
        $this->post('/settings/mail/'.$account->id.'/check')->assertSessionHas('error');
        $this->assertFalse($account->refresh()->imap_ok);
        $this->assertSame('Login failed', $account->imap_last_error);

        $this->inbox->failWith = null;
        // Unsaved form values, reusing the stored passwords: JSON for the "Test Connection" button.
        $this->postJson('/settings/mail/test', $this->accountPayload(['account_id' => $account->id, 'password' => '', 'imap_password' => '', 'host' => 'smtp.invalid.test']))
            ->assertOk()->assertJsonStructure(['smtp' => ['ok', 'message'], 'imap' => ['ok', 'message']])->assertJsonPath('imap.ok', true);
        $this->assertSame(1, MailSetting::count(), 'Testing never saves.');
    }

    public function test_accounts_used_by_running_sequences_cannot_be_deleted(): void
    {
        $busy = $this->makeAccount();
        $idle = $this->makeAccount();
        $this->enroll($this->makeSequence(), $this->makeLead(), $busy);

        $this->delete('/settings/mail/'.$busy->id)->assertSessionHas('error');
        $this->assertDatabaseHas('mail_settings', ['id' => $busy->id]);

        $this->delete('/settings/mail/'.$idle->id)->assertRedirect('/settings/mail');
        $this->assertDatabaseMissing('mail_settings', ['id' => $idle->id]);
    }

    // ── Email Templates, Email Activity, Dashboard ─────────────────────────

    public function test_the_email_template_form_lists_the_variables(): void
    {
        $this->get('/settings/templates/create')->assertOk()->assertSee('{{first_name}}', false)->assertSee('{{sender_email}}', false);
    }

    public function test_sequence_emails_appear_on_email_activity_and_the_dashboard(): void
    {
        $this->makeAccount();
        $sequence = $this->makeSequence([['subject' => 'Seq hello']], ['name' => 'Q4 Agencies']);
        $enrollment = $this->enroll($sequence, $this->makeLead());
        app(SequenceEmailProcessor::class)->process($enrollment->id);
        $email = LeadEmail::firstOrFail();
        $this->get('/track/open/'.$email->tracking_token);

        $this->get('/email-activity')->assertOk()->assertSee('Seq hello')->assertSee('Q4 Agencies');
        $this->get('/email-activity?sequence='.$sequence->id)->assertOk()->assertSee('Seq hello');
        $this->get('/email-activity?activity=opened')->assertOk()->assertSee('Seq hello');

        $this->get('/')->assertOk()->assertSee('Emails Scheduled')->assertSee('Unsubscribes')->assertSee('engagementChart');
        $this->getJson('/dashboard/stats')->assertOk()->assertJsonPath('outreach.opened', 1)->assertJsonPath('outreach.open_rate', 100);
    }

    public function test_check_replies_answers_ajax_with_json_and_the_page_has_no_full_reload_form(): void
    {
        // No IMAP account yet: a readable error, not a redirect.
        $this->postJson('/email-activity/check-replies')->assertStatus(422)
            ->assertJsonPath('ok', false)->assertJsonStructure(['message']);

        $this->makeAccount();
        $this->postJson('/email-activity/check-replies')->assertOk()
            ->assertJsonPath('ok', true)->assertJsonPath('replies', 0)->assertJsonPath('message', 'No new replies.')
            ->assertJsonStructure(['counts' => ['total', 'not_opened', 'opened', 'replied']]);

        // Plain (non-AJAX) post still works as a fallback.
        $this->post('/email-activity/check-replies')->assertRedirect()->assertSessionHas('success', 'No new replies.');

        $this->get('/email-activity')->assertOk()->assertSee('id="checkRepliesForm"', false)->assertSee('ajax-filters:reload');
    }

    public function test_html_in_lead_data_is_escaped_on_every_page(): void
    {
        $category = $this->makeCategory('XSS');
        $this->makeLead(['first_name' => '<script>alert(1)</script>', 'company_name' => '"><img src=x onerror=alert(2)>', 'category_id' => $category->id]);

        $this->get('/leads?category='.$category->id)->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('<img src=x', false);
    }

    public function test_csv_exports_neutralise_spreadsheet_formulas(): void
    {
        Lead::create(['company_name' => '=HYPERLINK("http://evil")', 'website' => 'https://x.test']);

        $csv = $this->get('/export')->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }

    public function test_migrated_legacy_template_rows_stay_usable(): void
    {
        $template = EmailTemplate::create(['name' => 'Legacy', 'subject' => 'S', 'body' => 'B', 'status' => 'active']);

        $this->get('/settings/templates')->assertOk()->assertSee('Legacy');
        $this->assertSame('active', $template->refresh()->status);
    }
}
