<?php

namespace Tests\Unit;

use App\Models\Lead;
use App\Models\MailSetting;
use App\Sequencer\Services\TemplateRendererService;
use Tests\TestCase;

class TemplateRendererTest extends TestCase
{
    private TemplateRendererService $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new TemplateRendererService;
    }

    public function test_replaces_every_supported_variable(): void
    {
        $vars = [
            'first_name' => 'Alex', 'last_name' => 'Morgan', 'email' => 'a@b.test', 'company' => 'Acme',
            'website' => 'https://acme.test', 'country' => 'US', 'job_title' => 'CTO',
            'sender_name' => 'Sam', 'sender_email' => 'sam@x.test',
        ];

        $template = implode('|', array_map(fn ($v) => '{{'.$v.'}}', array_keys($vars)));

        $this->assertSame(implode('|', $vars), $this->renderer->render($template, $vars));
    }

    public function test_whitespace_inside_braces_is_allowed(): void
    {
        $this->assertSame('Hi Alex', $this->renderer->render('Hi {{  first_name }}', ['first_name' => 'Alex']));
    }

    public function test_missing_values_render_empty_and_fallbacks_apply(): void
    {
        $this->assertSame('Hi ,', $this->renderer->render('Hi {{first_name}},', ['first_name' => null]));
        $this->assertSame('Hi there,', $this->renderer->render('Hi {{first_name|there}},', ['first_name' => '']));
        $this->assertSame('Hi Alex,', $this->renderer->render('Hi {{first_name|there}},', ['first_name' => 'Alex']));
        $this->assertSame('x', $this->renderer->render('{{nope}}x', []));
    }

    public function test_html_mode_escapes_values_so_data_cannot_inject_markup(): void
    {
        $out = $this->renderer->render('<p>{{company}}</p>', ['company' => '<script>alert(1)</script> & Co'], escape: true);

        $this->assertSame('<p>&lt;script&gt;alert(1)&lt;/script&gt; &amp; Co</p>', $out);
    }

    public function test_subjects_are_single_line_so_values_cannot_inject_headers(): void
    {
        $subject = $this->renderer->renderSubject('Hello {{first_name}}', ['first_name' => "Alex\r\nBcc: evil@x.test"]);

        $this->assertStringNotContainsString("\n", $subject);
        $this->assertStringNotContainsString("\r", $subject);
        $this->assertSame('Hello Alex Bcc: evil@x.test', $subject);
    }

    public function test_variables_come_from_the_lead_and_the_mail_setting(): void
    {
        $lead = new Lead(['email' => 'a@b.test', 'company_name' => 'Acme', 'first_name' => 'Ann', 'custom_fields' => ['plan' => 'Pro']]);
        $account = new MailSetting(['from_name' => 'Sam', 'from_address' => 's@x.test']);

        $vars = $this->renderer->variablesFor($lead, $account);

        $this->assertSame('Acme', $vars['company'], '{{company}} is the lead\'s company_name.');
        $this->assertSame('s@x.test', $vars['sender_email']);
        $this->assertSame('Pro plan for Ann at Acme from Sam', $this->renderer->render('{{custom.plan}} plan for {{first_name}} at {{company}} from {{sender_name}}', $vars));
    }

    public function test_unknown_variables_are_reported(): void
    {
        $unknown = $this->renderer->unknownVariables('Hi {{firstname}} {{first_name}} {{custom.x}} {{unsubscribe_url}}');

        $this->assertSame(['firstname'], $unknown);
    }
}
