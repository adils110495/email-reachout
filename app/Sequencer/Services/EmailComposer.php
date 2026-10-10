<?php

namespace App\Sequencer\Services;

use App\Models\Lead;
use App\Models\LeadEmail;
use App\Models\MailSetting;
use App\Models\Sequence;
use App\Models\SequenceStep;
use App\Models\TrackedLink;
use App\Sequencer\Mail\OutboundEmail;
use App\Sequencer\Support\HtmlText;
use App\Sequencer\Support\TrackingUrls;
use Illuminate\Support\Str;

/**
 * Turns a sequence step + lead into the exact message that is sent: variables
 * replaced, links rewritten for click tracking, unsubscribe footer, open pixel
 * and List-Unsubscribe headers.
 */
class EmailComposer
{
    public function __construct(private readonly TemplateRendererService $renderer) {}

    public function compose(LeadEmail $log, SequenceStep $step, Lead $lead, MailSetting $account, Sequence $sequence): OutboundEmail
    {
        $unsubscribeUrl = TrackingUrls::unsubscribe((string) $lead->unsubscribe_token);
        $variables = $this->renderer->variablesFor($lead, $account, ['unsubscribe_url' => $unsubscribeUrl]);

        $subject = $this->renderer->renderSubject($step->subject, $variables);
        $html = $this->renderBody($step->body, $variables);

        if ($sequence->track_clicks) {
            $html = $this->rewriteLinks($html, $log);
        }

        // Every email carries an unsubscribe link, unless the author placed their own.
        if (! str_contains($html, $unsubscribeUrl)) {
            $html .= $this->footer($unsubscribeUrl);
        }

        $text = HtmlText::fromHtml($html);

        if ($sequence->track_opens) {
            $html .= '<img src="'.e(TrackingUrls::open((string) $log->tracking_token)).'" width="1" height="1" alt="" style="display:none;border:0">';
        }

        return new OutboundEmail(
            fromEmail: $account->senderEmail(),
            fromName: (string) ($account->from_name ?: $account->senderEmail()),
            toEmail: (string) $log->to_email,
            toName: trim(($lead->first_name ?? '').' '.($lead->last_name ?? '')) ?: null,
            subject: $subject,
            html: $html,
            text: $text,
            messageId: (string) $log->message_id,
            headers: [
                'List-Unsubscribe' => '<'.$unsubscribeUrl.'>, <mailto:'.$account->senderEmail().'?subject=unsubscribe>',
                'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
            ],
        );
    }

    /** Plain-text bodies become HTML paragraphs; HTML bodies (e.g. Quill templates) are used as written. */
    public function renderBody(string $body, array $variables): string
    {
        if (HtmlText::isHtml($body)) {
            return $this->renderer->render($body, $variables, escape: true);
        }

        return nl2br(e($this->renderer->render($body, $variables, escape: false)));
    }

    /** Replace http(s) links with tracking redirects. mailto:, tel:, #anchors and our own URLs are left alone. */
    private function rewriteLinks(string $html, LeadEmail $log): string
    {
        return preg_replace_callback(
            '/(<a\b[^>]*?\bhref\s*=\s*)(["\'])(.*?)\2/is',
            function (array $m) use ($log) {
                $url = html_entity_decode(trim($m[3]), ENT_QUOTES | ENT_HTML5);

                if (! preg_match('#^https?://#i', $url) || TrackingUrls::isInternal($url)) {
                    return $m[0];
                }

                $link = TrackedLink::firstOrCreate(
                    ['lead_email_id' => $log->id, 'url_hash' => sha1($url)],
                    ['token' => Str::random(40), 'url' => $url],
                );

                return $m[1].$m[2].e(TrackingUrls::click($link->token)).$m[2];
            },
            $html
        ) ?? $html;
    }

    private function footer(string $unsubscribeUrl): string
    {
        $html = '<div style="margin-top:24px;padding-top:12px;border-top:1px solid #e5e5e5;font-size:12px;color:#888;">'
            .'If you would rather not hear from me again, <a href="'.e($unsubscribeUrl).'" style="color:#888;">unsubscribe here</a>.';

        if ($address = config('sequencer.footer_address')) {
            $html .= '<br>'.e($address);
        }

        return $html.'</div>';
    }
}
