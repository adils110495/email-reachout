<?php

namespace Tests\Feature\Sequencer;

use App\Models\LeadEmail;
use App\Models\MailSetting;
use App\Sequencer\Enums\ContactStatus;
use App\Sequencer\Enums\EnrollmentStatus;
use App\Sequencer\Mail\SmtpEmailProvider;
use App\Sequencer\Services\SequenceEmailProcessor;
use App\Services\ImapService;
use App\Services\MailConfigService;
use App\Services\ReplyCheckerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Email;
use Tests\Concerns\CreatesSequencerData;
use Tests\TestCase;

/**
 * End-to-end against a real SMTP + IMAP server (GreenMail), exercising the
 * production SmtpEmailProvider, ImapService and ReplyCheckerService - no fakes.
 *
 * Runs only when SEQUENCER_TEST_MAIL_HOST is set, e.g.
 *   docker run -d --name greenmail --network <app network> \
 *     -e GREENMAIL_OPTS="-Dgreenmail.setup.test.smtp -Dgreenmail.setup.test.imap -Dgreenmail.hostname=0.0.0.0 -Dgreenmail.auth.disabled" \
 *     greenmail/standalone:2.0.1
 *   SEQUENCER_TEST_MAIL_HOST=greenmail php artisan test --filter=RealMailServer
 */
class RealMailServerIntegrationTest extends TestCase
{
    use CreatesSequencerData, RefreshDatabase;

    private string $host;

    protected function setUp(): void
    {
        parent::setUp();

        $this->host = (string) getenv('SEQUENCER_TEST_MAIL_HOST');
        if ($this->host === '' || ! @fsockopen($this->host, 3025, $errno, $errstr, 2)) {
            $this->markTestSkipped('No test mail server (set SEQUENCER_TEST_MAIL_HOST).');
        }
    }

    private function mailbox(string $address): MailSetting
    {
        return $this->makeAccount([
            'from_address' => $address, 'from_name' => 'Sam Sender',
            'host' => $this->host, 'port' => 3025, 'encryption' => 'none', 'username' => null, 'password' => null,
            'imap_host' => $this->host, 'imap_port' => 3143, 'imap_encryption' => 'none',
            'imap_username' => $address, 'imap_password' => 'anything-'.uniqid(), 'folder' => null,
        ]);
    }

    private function sendRaw(Email $email): void
    {
        $transport = new EsmtpTransport($this->host, 3025, false);
        $transport->setAutoTls(false);
        $transport->send($email);
        $transport->stop();
    }

    public function test_send_reply_detection_and_bounce_detection_against_a_real_server(): void
    {
        $tag = substr(md5(uniqid()), 0, 8);
        $sender = "sam.$tag@sender.test";
        $prospect = "prospect.$tag@client.test";
        $account = $this->mailbox($sender);
        $imap = app(ImapService::class);

        // Connection tests use the real code paths.
        $this->assertTrue((new SmtpEmailProvider($account, app(MailConfigService::class)))->testConnection()->ok);
        $this->assertTrue($imap->testAccount($account)->ok);

        $sequence = $this->makeSequence([['days' => 0, 'subject' => 'Partnership idea', 'body' => '<p>Hi {{first_name}}, see <a href="https://acme.test">this</a></p>'], ['days' => 2]]);
        $lead = $this->makeLead(['email' => $prospect, 'first_name' => 'Pia']);
        $enrollment = $this->enroll($sequence, $lead, $account);

        // 1. Real SMTP delivery.
        $this->assertSame('sent', app(SequenceEmailProcessor::class)->process($enrollment->id)->value);
        $log = LeadEmail::firstOrFail();

        // 2. It arrived intact: read the prospect's mailbox over real IMAP.
        $batch = $imap->fetchNew($this->mailbox($prospect), null, null, 2, 50);
        $received = collect($batch->messages)->first(fn ($m) => $m->messageId === $log->message_id);
        $this->assertNotNull($received, 'Our Message-ID must survive delivery.');
        $this->assertSame('Partnership idea', $received->subject);
        $this->assertStringContainsString('/unsubscribe/'.$lead->unsubscribe_token, (string) $received->header('list-unsubscribe'));

        // 3. The prospect replies (threaded via In-Reply-To) -> reply detection over IMAP.
        $reply = (new Email)->from($prospect)->to($sender)->subject('Re: Partnership idea')->text('Sounds good, call me.');
        $reply->getHeaders()->addIdHeader('In-Reply-To', $log->message_id);
        $this->sendRaw($reply);

        $checker = app(ReplyCheckerService::class);
        $result = $checker->pollAccount($account->refresh());
        $this->assertSame(1, $result['replies'], json_encode($result));
        $this->assertSame(EnrollmentStatus::Replied, $enrollment->refresh()->status);
        $this->assertNotNull($log->refresh()->replied_at);
        $this->assertSame('replied', $lead->refresh()->status, 'The Leads page shows it as replied.');

        // A second poll finds nothing new.
        $this->assertSame(0, $checker->pollAccount($account->refresh())['fetched']);

        // 4. A delivery-status notification for a second lead -> hard bounce.
        $bounced = $this->makeLead(['email' => "gone.$tag@client.test"]);
        $second = $this->enroll($sequence, $bounced, $account);
        app(SequenceEmailProcessor::class)->process($second->id);
        $secondLog = LeadEmail::where('enrollment_id', $second->id)->firstOrFail();

        $dsn = "Reporting-MTA: dns; mx.client.test\r\n\r\nFinal-Recipient: rfc822; gone.$tag@client.test\r\nAction: failed\r\nStatus: 5.1.1\r\nDiagnostic-Code: smtp; 550 5.1.1 user unknown\r\n";
        $this->sendRaw((new Email)->from('MAILER-DAEMON@client.test')->to($sender)->subject('Undelivered Mail Returned to Sender')
            ->text("This is the mail system.\r\n\r\n".$dsn."\r\nMessage-ID: <{$secondLog->message_id}>\r\n"));

        $result = $checker->pollAccount($account->refresh());
        $this->assertSame(1, $result['bounces'], json_encode($result));
        $this->assertSame(ContactStatus::Bounced, $bounced->refresh()->contact_status);
        $this->assertSame(EnrollmentStatus::Bounced, $second->refresh()->status);
        $this->assertSame('bounced', $secondLog->refresh()->status);
    }

    public function test_bad_smtp_host_fails_cleanly(): void
    {
        $account = $this->mailbox('x@sender.test');
        $account->port = 1;   // nothing listens there

        $result = (new SmtpEmailProvider($account, app(MailConfigService::class)))->testConnection();

        $this->assertFalse($result->ok);
        $this->assertNotSame('', $result->message);
    }
}
