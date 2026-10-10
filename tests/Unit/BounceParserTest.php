<?php

namespace Tests\Unit;

use App\Sequencer\Imap\InboundEmail;
use App\Sequencer\Services\BounceParser;
use App\Sequencer\Support\HtmlText;
use App\Sequencer\Support\MessageHeaders;
use Tests\TestCase;

class BounceParserTest extends TestCase
{
    private function mail(array $headers, string $from, string $subject, ?string $raw = null): InboundEmail
    {
        return new InboundEmail(1, 'id@x', null, [], $from, $subject, null, $headers, $raw);
    }

    public function test_recognises_bounces_by_sender_content_type_return_path_or_subject(): void
    {
        $this->assertTrue(BounceParser::looksLikeBounce([], 'mailer-daemon@x.test', 'whatever'));
        $this->assertTrue(BounceParser::looksLikeBounce([], 'postmaster@x.test', 'whatever'));
        $this->assertTrue(BounceParser::looksLikeBounce(['content-type' => 'multipart/report; report-type=delivery-status'], 'a@b.test', 'x'));
        $this->assertTrue(BounceParser::looksLikeBounce(['return-path' => '<>'], 'a@b.test', 'x'));
        $this->assertTrue(BounceParser::looksLikeBounce([], 'a@b.test', 'Delivery Status Notification (Failure)'));
        $this->assertFalse(BounceParser::looksLikeBounce([], 'person@b.test', 'Re: your note'));
    }

    public function test_hard_versus_soft_classification(): void
    {
        $parser = new BounceParser;
        $raw = fn (string $status, string $action = 'failed') => "Action: $action\nStatus: $status\nDiagnostic-Code: smtp; 550 test\nFinal-Recipient: rfc822; Who@X.test\nMessage-ID: <orig@sender.test>\n";

        $hard = $parser->parse($this->mail([], 'mailer-daemon@x.test', 'failure', $raw('5.1.1')));
        $this->assertTrue($hard->hard);
        $this->assertSame('5.1.1', $hard->status);
        $this->assertSame('who@x.test', $hard->recipient);
        $this->assertContains('orig@sender.test', $hard->messageIds);

        $this->assertFalse($parser->parse($this->mail([], 'mailer-daemon@x.test', 'f', $raw('4.4.1')))->hard, 'Temporary.');
        $this->assertFalse($parser->parse($this->mail([], 'mailer-daemon@x.test', 'f', $raw('5.2.2')))->hard, 'Mailbox full.');
        $this->assertFalse($parser->parse($this->mail([], 'mailer-daemon@x.test', 'f', $raw('5.7.1')))->hard, 'Policy block.');
        $this->assertFalse($parser->parse($this->mail([], 'mailer-daemon@x.test', 'f', $raw('5.1.1', 'delayed')))->hard);

        $noStatus = $parser->parse($this->mail([], 'mailer-daemon@x.test', 'failure notice', "550 sorry, no mailbox here by that name (#5.1.1)\n"));
        $this->assertTrue($noStatus->hard);

        $this->assertNull($parser->parse($this->mail([], 'friend@x.test', 'Re: hi', 'hello')));
    }

    public function test_auto_reply_detection(): void
    {
        $this->assertTrue(BounceParser::isAutoReply($this->mail(['auto-submitted' => 'auto-replied'], 'a@b', 'Re: x')));
        $this->assertFalse(BounceParser::isAutoReply($this->mail(['auto-submitted' => 'no'], 'a@b', 'Re: x')));
        $this->assertTrue(BounceParser::isAutoReply($this->mail(['x-autoreply' => 'yes'], 'a@b', 'Re: x')));
        $this->assertTrue(BounceParser::isAutoReply($this->mail(['precedence' => 'auto_reply'], 'a@b', 'Re: x')));
        $this->assertTrue(BounceParser::isAutoReply($this->mail([], 'a@b', 'Out of Office: back Monday')));
        $this->assertFalse(BounceParser::isAutoReply($this->mail([], 'a@b', 'Re: sounds good')));
    }

    public function test_header_helpers(): void
    {
        $h = MessageHeaders::parse("Message-ID: <a@b>\r\nReferences: <x@y>\r\n <z@w>\r\nSubject: =?UTF-8?B?SMOpbGxv?=\r\nFrom: \"Doe, Jane\" <Jane@Ex.com>\r\n\r\nbody: not a header");

        $this->assertSame(['x@y', 'z@w'], MessageHeaders::messageIds($h['references']));
        $this->assertSame('jane@ex.com', MessageHeaders::address($h['from']));
        $this->assertSame('Héllo', MessageHeaders::decode($h['subject']));
        $this->assertArrayNotHasKey('body', $h);
        $this->assertSame('hello world', MessageHeaders::normalizeSubject('Re: RE: Fwd:  Hello   World'));
    }

    public function test_plain_text_alternative_keeps_links_and_paragraphs(): void
    {
        $text = HtmlText::fromHtml('<p>Hi <b>Ann</b>,</p><p>See <a href="https://x.test">our site</a>.<br>Thanks &amp; bye</p><img src="p.gif">');

        $this->assertSame("Hi Ann,\n\nSee our site (https://x.test).\nThanks & bye", $text);
    }
}
