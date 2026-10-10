<?php

namespace Tests\Feature\Sequencer;

use App\Models\ActivityEvent;
use App\Models\LeadEmail;
use App\Models\SequenceEnrollment;
use App\Models\TrackedLink;
use App\Sequencer\Enums\ContactStatus;
use App\Sequencer\Enums\EmailLogStatus;
use App\Sequencer\Enums\EnrollmentStatus;
use App\Sequencer\Enums\SendOutcome;
use App\Sequencer\Services\SequenceEmailProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesSequencerData;
use Tests\Support\FakeEmailProvider;
use Tests\TestCase;

class TrackingAndUnsubscribeTest extends TestCase
{
    use CreatesSequencerData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeTransports();
        $this->makeAccount();
    }

    /** @return array{0: LeadEmail, 1: SequenceEnrollment} */
    private function sentEmail(string $body = '<p><a href="https://acme.test/pricing?x=1&amp;y=2">Pricing</a> <a href="mailto:me@x.test">Mail</a> <a href="tel:+123">Call</a> <a href="#top">Top</a> <a href="{{unsubscribe_url}}">Opt out</a></p>'): array
    {
        $sequence = $this->makeSequence([['body' => $body, 'days' => 0], ['body' => $body, 'days' => 1]]);
        $enrollment = $this->enroll($sequence, $this->makeLead());
        app(SequenceEmailProcessor::class)->process($enrollment->id);

        return [LeadEmail::firstOrFail(), $enrollment->refresh()];
    }

    public function test_links_are_rewritten_except_mailto_tel_anchors_and_unsubscribe(): void
    {
        [$email] = $this->sentEmail();
        $html = FakeEmailProvider::$sent[0]->html;

        $this->assertSame(1, TrackedLink::count(), 'Only the http(s) link is tracked.');
        $link = TrackedLink::first();
        $this->assertSame('https://acme.test/pricing?x=1&y=2', $link->url);
        $this->assertSame($email->id, $link->lead_email_id);
        $this->assertStringContainsString('/track/click/'.$link->token, $html);
        $this->assertStringContainsString('href="mailto:me@x.test"', $html);
        $this->assertStringContainsString('href="tel:+123"', $html);
        $this->assertStringContainsString('href="#top"', $html);
        $this->assertSame(1, substr_count($html, '/unsubscribe/'), 'An author-placed unsubscribe link means no extra footer.');
        $this->assertGreaterThanOrEqual(40, strlen($email->tracking_token));
    }

    public function test_open_pixel_records_the_first_open_and_counts_repeats(): void
    {
        [$email] = $this->sentEmail();

        $response = $this->get('/track/open/'.$email->tracking_token);
        $response->assertOk()->assertHeader('Content-Type', 'image/gif');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        $email->refresh();
        $this->assertSame('sent', $email->status, 'Delivery status is untouched by engagement.');
        $this->assertSame(EmailLogStatus::Opened, $email->engagement());
        $this->assertSame(LeadEmail::ACTIVITY_OPENED, $email->activity, 'Shown as opened on the Email Activity page.');
        $first = $email->first_opened_at;
        $this->assertNotNull($first);

        $this->travel(1)->hours();
        $this->get('/track/open/'.$email->tracking_token)->assertOk();
        $email->refresh();
        $this->assertEquals($first, $email->first_opened_at, 'First open time is kept.');
        $this->assertTrue($email->last_opened_at->gt($first));
        $this->assertSame(2, $email->open_count);
        $this->assertSame(1, ActivityEvent::where('type', 'email_opened')->count(), 'Only the first open goes on the timeline.');
    }

    public function test_the_original_pixel_url_still_works_for_emails_sent_from_the_leads_page(): void
    {
        $lead = $this->makeLead();
        $legacy = LeadEmail::create([
            'lead_id' => $lead->id, 'subject' => 'Direct', 'body' => 'x', 'status' => 'sent', 'sent_at' => now(),
            'tracking_token' => Str::random(40), 'message_id' => 'legacy@sender.test',
        ]);

        $this->get('/t/o/'.$legacy->tracking_token.'.gif')->assertOk()->assertHeader('Content-Type', 'image/gif');

        $legacy->refresh();
        $this->assertSame(1, $legacy->open_count);
        $this->assertNotNull($legacy->first_opened_at);
        $this->assertNotNull($legacy->last_opened_at);
    }

    public function test_unknown_or_malformed_open_tokens_get_the_same_pixel_and_change_nothing(): void
    {
        [$email] = $this->sentEmail();

        foreach (['doesnotexist0000000000000000000000000000', '1', $email->id, "x'or'1'='1"] as $token) {
            $this->get('/track/open/'.urlencode((string) $token))->assertOk()->assertHeader('Content-Type', 'image/gif');
        }

        $this->assertSame(0, $email->refresh()->open_count);
    }

    public function test_click_redirects_to_the_stored_url_and_records_engagement(): void
    {
        [$email] = $this->sentEmail();
        $link = TrackedLink::first();

        $this->get('/track/click/'.$link->token)->assertRedirect('https://acme.test/pricing?x=1&y=2')->assertHeader('Referrer-Policy', 'no-referrer');
        $this->get('/track/click/'.$link->token);

        $email->refresh();
        $link->refresh();
        $this->assertSame(EmailLogStatus::Clicked, $email->engagement());
        $this->assertNotNull($email->clicked_at);
        $this->assertNotNull($email->first_opened_at, 'A click implies an open.');
        $this->assertSame(2, $email->click_count);
        $this->assertSame(2, $link->click_count);
        $this->assertNotNull($link->first_clicked_at);
        $this->assertSame(2, ActivityEvent::where('type', 'link_clicked')->count());
    }

    public function test_unknown_click_tokens_404_and_there_is_no_open_redirect(): void
    {
        $this->sentEmail();

        $this->get('/track/click/nonexistenttoken000000000000000000000000')->assertNotFound();
        $this->get('/track/click/'.urlencode('https://evil.test'))->assertNotFound();
        $this->get('/track/click/1')->assertNotFound();
    }

    public function test_unsubscribe_get_only_confirms_and_post_unsubscribes_and_stops_all_sequences(): void
    {
        [, $enrollment] = $this->sentEmail();
        $lead = $enrollment->lead;
        $other = $this->enroll($this->makeSequence([['days' => 3]]), $lead);

        $this->get('/unsubscribe/'.$lead->unsubscribe_token)->assertOk()->assertSee('Yes, unsubscribe me');
        $this->assertSame(ContactStatus::Active, $lead->refresh()->contact_status, 'Link scanners must not unsubscribe anyone.');

        $this->post('/unsubscribe/'.$lead->unsubscribe_token)->assertOk()->assertSee('You are unsubscribed');

        $lead->refresh();
        $this->assertSame(ContactStatus::Unsubscribed, $lead->contact_status);
        $this->assertNotNull($lead->unsubscribed_at);
        foreach ([$enrollment, $other] as $e) {
            $e->refresh();
            $this->assertSame(EnrollmentStatus::Unsubscribed, $e->status);
            $this->assertSame('unsubscribed', $e->stop_reason);
        }

        // No future email may be sent, even by an already-queued job.
        $this->travel(5)->days();
        $this->assertSame(SendOutcome::Skipped, app(SequenceEmailProcessor::class)->process($enrollment->id));
        $this->assertCount(1, FakeEmailProvider::$sent);

        $this->post('/unsubscribe/'.$lead->unsubscribe_token)->assertOk();   // repeating is harmless
        $this->assertSame(ContactStatus::Unsubscribed, $lead->refresh()->contact_status);
    }

    public function test_one_click_list_unsubscribe_post_without_csrf_token(): void
    {
        [, $enrollment] = $this->sentEmail();

        $this->post('/unsubscribe/'.$enrollment->lead->unsubscribe_token, ['List-Unsubscribe' => 'One-Click'])->assertOk()->assertSee('Unsubscribed');
        $this->assertSame(ContactStatus::Unsubscribed, $enrollment->lead->refresh()->contact_status);
    }

    public function test_bad_unsubscribe_tokens_404_and_the_page_masks_the_address(): void
    {
        [, $enrollment] = $this->sentEmail();

        $this->get('/unsubscribe/short')->assertNotFound();
        $this->get('/unsubscribe/'.str_repeat('a', 48))->assertNotFound();
        $this->post('/unsubscribe/'.str_repeat('b', 48))->assertNotFound();

        $this->get('/unsubscribe/'.$enrollment->lead->unsubscribe_token)->assertDontSee($enrollment->lead->email);
    }
}
