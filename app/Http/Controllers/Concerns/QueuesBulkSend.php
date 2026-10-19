<?php

namespace App\Http\Controllers\Concerns;

use App\Models\EmailTemplate;
use App\Models\User;
use App\Models\WhatsappSetting;
use App\Services\EmailBulkService;
use App\Services\WhatsappBulkService;
use App\Support\EmailHtml;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * "Bulk send" on a list screen (GMB Leads, Deals): the selected rows, or every
 * row matching the list's current filters, get an approved WhatsApp template,
 * an email, or both at once.
 *
 * The list controller passes its own filtered query, so "all matching" means
 * exactly what the user is looking at, and names the channels its rows can be
 * reached on. Queuing itself is WhatsappBulkService and EmailBulkService.
 */
trait QueuesBulkSend
{
    /**
     * @param  Builder             $query      the list's rows - filtered when "all" is posted
     * @param  callable            $toContact  Model -> ['name', 'phone', 'email', 'gmb_lead_id' | 'cash_lead_id']
     * @param  array<int, string>  $channels   what this list can send: whatsapp, email
     */
    protected function queueBulkSend(Request $request, Builder $query, callable $toContact, array $channels): JsonResponse
    {
        $max = max(1, (int) config('services.whatsapp.bulk_max', 250));

        // WhatsApp needs its own module; email comes with the list.
        $allowed = array_values(array_filter(
            $channels,
            fn (string $channel) => $channel !== 'whatsapp' || $request->user()->can('module', 'whatsapp'),
        ));

        $data = $request->validate([
            'all'            => ['nullable', 'boolean'],
            'ids'            => ['exclude_if:all,1', 'required', 'array', 'max:' . $max],
            'ids.*'          => ['integer'],
            'channels'       => ['required', 'array', 'min:1'],
            'channels.*'     => ['distinct', Rule::in($allowed)],
            'skip_contacted' => ['nullable', 'boolean'],
        ], [
            'ids.required'      => 'Select at least one contact.',
            'ids.max'           => "One bulk send can have at most {$max} contacts.",
            'channels.required' => 'Choose how to send: WhatsApp, email or both.',
            'channels.*.in'     => 'You cannot send on that channel from here.',
        ]);

        $whatsapp = in_array('whatsapp', $data['channels'], true) ? $this->whatsappInput($request) : null;
        $email    = in_array('email', $data['channels'], true) ? $this->emailInput($request) : null;

        if (! $request->boolean('all')) {
            $query->whereKey(array_map('intval', $data['ids']));
        }

        // Only rows reachable on a chosen channel; the owner scope keeps it to rows the user may see.
        $query->where(function (Builder $q) use ($whatsapp, $email) {
            if ($whatsapp) {
                $q->orWhere(fn (Builder $w) => $w->whereNotNull('phone')->where('phone', '!=', ''));
            }
            if ($email) {
                $q->orWhere(fn (Builder $w) => $w->whereNotNull('email')->where('email', '!=', ''));
            }
        });

        $count = (clone $query)->count();

        if ($count === 0) {
            $needs = implode(' or ', array_filter([$whatsapp ? 'a phone number' : null, $email ? 'an email address' : null]));

            return $this->bulkFail("None of these contacts has {$needs}.");
        }

        if ($count > $max) {
            return $this->bulkFail("{$count} contacts match - one bulk send can have at most {$max}. Narrow the filters and send in parts.");
        }

        $records  = $query->get();
        $owners   = $records->pluck('user_id')->unique()->values();
        $ownerId  = (int) $owners->first();
        $senderId = (int) $request->user()->id;

        // WhatsApp goes out from one number: the owner's when it is connected,
        // else the sender's own - a super admin messaging a team member's leads
        // from the company number (WhatsappSetting::senderFor). Contacts of
        // several users can only share the sender's number.
        $waSender = null;
        if ($whatsapp) {
            $waSender = $owners->count() === 1
                ? WhatsappSetting::senderFor($ownerId, $senderId)
                : (WhatsappSetting::connectedFor($senderId) ? $senderId : null);
        }

        // An email goes out from its owner's mailbox, so one email send cannot
        // span two users' workspaces.
        if (($email && $owners->count() > 1) || ($whatsapp && $waSender === null)) {
            return $this->bulkFail("These contacts belong to different users. Select one user's contacts at a time.");
        }

        // Neither number can send. Name the owner: a super admin would otherwise
        // read "not connected" about their own, working, connection.
        if ($whatsapp && ! WhatsappSetting::connectedFor($waSender)) {
            return $this->bulkFail($waSender === $senderId
                ? 'WhatsApp is not connected for your account. Connect it under Settings > WhatsApp.'
                : 'WhatsApp is not connected for ' . (User::find($waSender)?->name ?? 'their owner')
                    . ' (these contacts are theirs) or for you. Connect it under Settings > WhatsApp.');
        }

        $skip     = $request->boolean('skip_contacted');
        $contacts = $records->map(fn (Model $record) => $toContact($record));

        try {
            // Checked before anything is queued, so a missing mailbox cannot
            // leave a WhatsApp + email send half done. WhatsappBulkService
            // checks its own connection before it queues anything.
            if ($email) {
                $this->access()->runAs($ownerId, fn () => app(EmailBulkService::class)->ensureReady());
            }

            $waSummary = $whatsapp
                ? $this->access()->runAs($waSender, fn () => app(WhatsappBulkService::class)->queue($contacts, $whatsapp['template'], $whatsapp['interval'], $skip))
                : null;

            $emailSummary = $email
                ? $this->access()->runAs($ownerId, fn () => app(EmailBulkService::class)->queue($contacts, $email['email'], $email['interval'], $skip))
                : null;
        } catch (RuntimeException $e) {
            return $this->bulkFail($e->getMessage());
        }

        $message = implode(' ', array_filter([
            $waSummary ? $this->bulkReport('WhatsApp message', $waSummary, $whatsapp['interval'], [
                'already_messaged' => 'already messaged',
                'no_number'        => 'without a usable number',
                'duplicate'        => 'with a repeated number',
            ]) : null,
            $emailSummary ? $this->bulkReport('email', $emailSummary, $email['interval'], [
                'already_emailed' => 'already emailed',
                'no_email'        => 'without an email address',
                'duplicate'       => 'with a repeated address',
            ]) : null,
        ]));

        if (($waSummary['queued'] ?? 0) + ($emailSummary['queued'] ?? 0) === 0) {
            return $this->bulkFail($message);
        }

        $links = [];

        if (($waSummary['queued'] ?? 0) > 0) {
            $links[] = ['url' => route('whatsapp.index', ['batch' => $waSummary['batch_id']]), 'label' => 'WhatsApp progress'];
        }
        if (($emailSummary['queued'] ?? 0) > 0 && $request->user()->can('module', 'email_activity')) {
            $links[] = ['url' => route('email-activity.index', ['batch' => $emailSummary['batch_id']]), 'label' => 'Email progress'];
        }

        return response()->json(['ok' => true, 'message' => $message, 'links' => $links]);
    }

    /**
     * The WhatsApp half of the form: one approved template and its values.
     *
     * @return array{template: array{name: string, language: string, header: ?string, body: ?string, params: array<int|string, string>}, interval: int}
     */
    private function whatsappInput(Request $request): array
    {
        $data = $request->validate([
            'template_name'     => ['required', 'string', 'max:512', 'regex:/^[a-z0-9_]+$/'],
            'template_language' => ['required', 'string', 'max:15', 'regex:/^[A-Za-z0-9_]+$/'],
            'template_header'   => ['nullable', 'string', 'max:1024'],
            'template_body'     => ['nullable', 'string', 'max:4096'],
            'params'            => ['nullable', 'array', 'max:20'],
            'params.*'          => ['required', 'string', 'max:1024'],
            'interval'          => ['required', 'integer', Rule::in(WhatsappBulkService::INTERVALS)],
        ], [
            'template_name.required' => 'Choose an approved WhatsApp template.',
        ]);

        foreach (array_keys($data['params'] ?? []) as $key) {
            if (! preg_match('/^[A-Za-z0-9_]{1,64}$/', (string) $key)) {
                throw ValidationException::withMessages(['params' => 'Invalid template variable.']);
            }
        }

        return [
            'template' => [
                'name'     => $data['template_name'],
                'language' => $data['template_language'],
                'header'   => $data['template_header'] ?? null,
                'body'     => $data['template_body'] ?? null,
                'params'   => $data['params'] ?? [],
            ],
            'interval' => (int) $data['interval'],
        ];
    }

    /**
     * The email half of the form: subject, body (editor HTML, made safe) and
     * the template attachments kept in the modal.
     *
     * @return array{email: array{subject: string, body: string, attachments: array<int, array<string, mixed>>}, interval: int}
     */
    private function emailInput(Request $request): array
    {
        $data = $request->validate([
            'email_template_id'   => ['nullable', 'integer'],
            'email_attachments'   => ['nullable', 'array', 'max:20'],
            'email_attachments.*' => ['string', 'max:512'],
            'email_subject'       => ['required', 'string', 'max:255'],
            'email_body'          => ['required', 'string', 'max:100000', EmailHtml::notBlankRule('Write the email.')],
            'email_interval'      => ['required', 'integer', Rule::in(EmailBulkService::INTERVALS)],
        ], [
            'email_subject.required' => 'Write the email subject.',
            'email_body.required'    => 'Write the email.',
        ]);

        // Attachments can only come from the chosen template, looked up as the
        // signed-in user - a posted path is never trusted on its own, or any
        // file on the server could be attached.
        $attachments = [];

        if (! empty($data['email_attachments'])) {
            $template = isset($data['email_template_id']) ? EmailTemplate::find((int) $data['email_template_id']) : null;
            $offered  = collect($template?->attachments ?? [])->keyBy('path');

            foreach (array_unique($data['email_attachments']) as $path) {
                if (! $offered->has($path)) {
                    throw ValidationException::withMessages(['email_attachments' => 'One of the template attachments is not available.']);
                }

                $attachments[] = Arr::only($offered[$path], ['name', 'path', 'size']);
            }
        }

        return [
            'email'    => ['subject' => $data['email_subject'], 'body' => EmailHtml::render($data['email_body']), 'attachments' => $attachments],
            'interval' => (int) $data['email_interval'],
        ];
    }

    /**
     * "12 emails queued, one every 30s (about 6 min). Skipped: 2 already emailed."
     *
     * @param  array<string, mixed>   $summary     a bulk service's queue() result
     * @param  array<string, string>  $skipLabels  summary key => why those contacts were left out
     */
    private function bulkReport(string $noun, array $summary, int $interval, array $skipLabels): string
    {
        $skipped = collect($skipLabels)
            ->filter(fn (string $label, string $key) => $summary[$key] > 0)
            ->map(fn (string $label, string $key) => "{$summary[$key]} {$label}")
            ->implode(', ');

        if ($summary['queued'] === 0) {
            return "No {$noun}s to send" . ($skipped !== '' ? " - {$skipped}." : '.');
        }

        $minutes = (int) ceil(($summary['queued'] - 1) * $interval / 60);

        return "{$summary['queued']} {$noun}" . ($summary['queued'] === 1 ? '' : 's') . ' queued'
            . ($minutes > 0 ? ", one every {$interval}s (about {$minutes} min)" : '') . '.'
            . ($skipped !== '' ? " Skipped: {$skipped}." : '');
    }

    private function bulkFail(string $message): JsonResponse
    {
        return response()->json(['ok' => false, 'message' => $message], 422);
    }
}
