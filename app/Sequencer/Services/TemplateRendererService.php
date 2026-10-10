<?php

namespace App\Sequencer\Services;

use App\Models\Lead;
use App\Models\MailSetting;

/**
 * The single place where {{variables}} are replaced: sequence subjects and bodies,
 * Email Templates loaded in the Leads compose window, previews and the REST API.
 *
 * Syntax:  {{first_name}}   {{ first_name }}   {{first_name|there}} (fallback when empty)
 *          {{custom.plan}}  reads leads.custom_fields['plan']
 */
class TemplateRendererService
{
    /** Variables every template may use. */
    public const VARIABLES = [
        'first_name', 'last_name', 'email', 'company', 'website',
        'country', 'job_title', 'sender_name', 'sender_email',
    ];

    private const PATTERN = '/\{\{\s*([A-Za-z0-9_.]+)\s*(?:\|([^{}]*?))?\s*\}\}/';

    /**
     * Replace variables in $template.
     *
     * @param  array<string, scalar|null>  $variables  see variablesFor()
     * @param  bool  $escape  HTML-escape inserted values (true for HTML bodies)
     */
    public function render(string $template, array $variables, bool $escape = false): string
    {
        return preg_replace_callback(self::PATTERN, function (array $m) use ($variables, $escape) {
            $value = $variables[$m[1]] ?? null;
            $value = is_scalar($value) ? trim((string) $value) : '';

            if ($value === '' && isset($m[2])) {
                $value = trim($m[2]);
            }

            return $escape ? e($value) : $value;
        }, $template) ?? $template;
    }

    /** Subjects are single-line: strip CR/LF so a value can never inject a header. */
    public function renderSubject(string $subject, array $variables): string
    {
        $rendered = $this->render($subject, $variables, escape: false);

        return trim(preg_replace('/\s+/', ' ', $rendered) ?? $rendered);
    }

    /**
     * Build the variable map for a lead + sending account.
     *
     * @param  array<string, scalar|null>  $extra  e.g. ['unsubscribe_url' => '...']
     * @return array<string, scalar|null>
     */
    public function variablesFor(Lead $lead, ?MailSetting $account = null, array $extra = []): array
    {
        $vars = [
            'first_name' => $lead->first_name,
            'last_name' => $lead->last_name,
            'email' => $lead->email,
            'company' => $lead->company_name,
            'website' => $lead->website,
            'country' => $lead->country,
            'job_title' => $lead->job_title,
            'sender_name' => $account?->from_name,
            'sender_email' => $account?->senderEmail(),
        ];

        foreach ((array) $lead->custom_fields as $key => $value) {
            if (is_scalar($value)) {
                $vars['custom.'.$key] = $value;
            }
        }

        return $extra + $vars;
    }

    /** Sample data for previews so users can see a realistic rendering. */
    public function sampleVariables(?MailSetting $account = null): array
    {
        return [
            'first_name' => 'Alex',
            'last_name' => 'Morgan',
            'email' => 'alex@example.com',
            'company' => 'Acme Inc',
            'website' => 'https://acme.example',
            'country' => 'United States',
            'job_title' => 'Head of Growth',
            'sender_name' => $account?->from_name ?? 'Your Name',
            'sender_email' => $account?->senderEmail() ?: 'you@yourdomain.com',
        ];
    }

    /**
     * Variables used in $template that are neither built in nor custom.* - i.e. likely typos.
     *
     * @return list<string>
     */
    public function unknownVariables(string $template, array $extraAllowed = ['unsubscribe_url']): array
    {
        preg_match_all(self::PATTERN, $template, $matches);

        $unknown = [];
        foreach (array_unique($matches[1]) as $name) {
            if (! in_array($name, self::VARIABLES, true)
                && ! in_array($name, $extraAllowed, true)
                && ! str_starts_with($name, 'custom.')) {
                $unknown[] = $name;
            }
        }

        return $unknown;
    }
}
