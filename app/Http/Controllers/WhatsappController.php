<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExportsCsv;
use App\Http\Controllers\Concerns\FiltersRequests;
use App\Models\CashLead;
use App\Models\GmbLead;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Models\WhatsappSetting;
use App\Services\WhatsappBulkService;
use App\Services\WhatsappService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * WhatsApp messages: the activity log, and the compose modal's endpoints
 * (templates, send through the Cloud API, open the chat with wa.me).
 *
 * A message to a GMB lead or deal is sent as that record's owner - their
 * WhatsApp number, their log - also when a super admin sends it.
 */
class WhatsappController extends Controller
{
    use ExportsCsv;
    use FiltersRequests;

    private const SOURCES = ['gmb', 'deal', 'direct'];

    public function __construct(private readonly WhatsappService $whatsapp) {}

    public function index(Request $request, WhatsappBulkService $bulk): View
    {
        $activities = WhatsappMessage::ACTIVITIES;
        $activity   = $this->enumParam($request, 'activity', array_keys($activities));
        $search     = $this->strParam($request, 'q');
        $batch      = Str::isUuid($this->strParam($request, 'batch')) ? $this->strParam($request, 'batch') : '';

        // The user filter is a super admin tool; for anyone else the owner
        // scope already limits the list to their own messages.
        $isSuperAdmin = $this->access()->isSuperAdmin();
        $users        = $isSuperAdmin ? User::orderBy('name')->get(['id', 'name', 'username']) : collect();
        $owner        = $isSuperAdmin ? $this->intParam($request, 'user') : null;

        $base = WhatsappMessage::query()
            ->when($owner, fn (Builder $q) => $q->where('user_id', $owner))
            ->when($batch !== '', fn (Builder $q) => $q->where('batch_id', $batch));

        $messages = (clone $base)
            ->with('user')
            ->when($activity !== '', fn (Builder $q) => $q->activity($activity))
            ->when($search !== '', fn (Builder $q) => $q->where(function (Builder $w) use ($search) {
                $like   = $this->likePattern($search);
                $digits = preg_replace('/\D+/', '', $search);

                $w->where('contact_name', 'like', $like)
                  ->orWhere('body', 'like', $like)
                  ->orWhere('template_name', 'like', $like)
                  ->when($digits !== '', fn (Builder $p) => $p->orWhere('phone', 'like', $this->likePattern($digits)));
            }))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        // Per-status counts for the filter dropdown in one grouped query; a
        // reply outranks the status, so replied messages are counted apart.
        $byStatus = (clone $base)->whereNull('replied_at')->toBase()
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $counts = [
            'total'                            => (clone $base)->count(),
            WhatsappMessage::ACTIVITY_REPLIED => (clone $base)->whereNotNull('replied_at')->count(),
        ];
        foreach (array_keys($activities) as $key) {
            $counts[$key] ??= (int) ($byStatus[$key] ?? 0);
        }

        // A bulk send gets a progress panel above its messages.
        $batchProgress = $batch !== '' ? $bulk->progress($batch) : null;
        $batchFirst    = $batch !== '' ? WhatsappMessage::where('batch_id', $batch)->oldest('id')->first() : null;

        $data = compact('messages', 'activities', 'activity', 'search', 'counts', 'isSuperAdmin', 'users', 'owner', 'batch', 'batchProgress', 'batchFirst');

        // A filter / search / page change fetches just the table partial.
        if ($request->ajax()) {
            return view('whatsapp._table', $data);
        }

        return view('whatsapp.index', $data + ['setting' => WhatsappSetting::forActingUser()]);
    }

    public function export(): StreamedResponse
    {
        return $this->streamCsv(
            'whatsapp_messages',
            ['ID', 'Contact', 'Phone', 'Channel', 'Type', 'Template', 'Message', 'Status', 'Error', 'Sent At', 'Delivered At', 'Read At', 'Replied At', 'Reply'],
            WhatsappMessage::latest('id')->get(),
            fn (WhatsappMessage $m) => [
                $m->id, $m->contact_name, $m->display_phone, $m->channel, $m->type, $m->template_name, $m->body,
                $m->badge['label'], $m->error, $m->sent_at?->toDateTimeString(), $m->delivered_at?->toDateTimeString(),
                $m->read_at?->toDateTimeString(), $m->replied_at?->toDateTimeString(), $m->reply_body,
            ],
        );
    }

    /**
     * Polled by the WhatsApp page while a bulk send is running (JSON).
     */
    public function batchStatus(string $batch, WhatsappBulkService $bulk): JsonResponse
    {
        $progress = $bulk->progress($batch);

        abort_if($progress['total'] === 0, 404);

        return response()->json($progress);
    }

    /**
     * Stop a bulk send: what is still queued is cancelled.
     */
    public function cancelBatch(Request $request, string $batch, WhatsappBulkService $bulk): RedirectResponse
    {
        $cancelled = $bulk->cancel($batch);

        return redirect()->route('whatsapp.index', ['batch' => $batch])->with(
            $cancelled ? 'success' : 'error',
            $cancelled ? "Bulk send stopped - {$cancelled} queued message" . ($cancelled === 1 ? '' : 's') . ' cancelled.' : 'Nothing left to cancel.',
        );
    }

    /**
     * Approved templates of the account the message would go out from (JSON).
     */
    public function templates(Request $request): JsonResponse
    {
        [, $ownerId] = $this->recipient($request);

        return $this->access()->runAs($ownerId, function () use ($request) {
            $setting = WhatsappSetting::forActingUser();

            if (! $setting?->canSend()) {
                return $this->fail('The WhatsApp Cloud API is not connected for this account.');
            }

            try {
                return response()->json(['ok' => true, 'templates' => $this->whatsapp->templates($setting, $request->boolean('refresh'))]);
            } catch (\Throwable $e) {
                return $this->fail('Could not load templates: ' . $e->getMessage());
            }
        });
    }

    /**
     * Send through the Cloud API - an approved template, or free text inside
     * the 24-hour window. Synchronous, so the modal can show Meta's error.
     */
    public function send(Request $request): JsonResponse
    {
        [$record, $ownerId] = $this->recipient($request);

        $data = $request->validate([
            'name'              => ['nullable', 'string', 'max:255'],
            'phone'             => ['required', 'string', 'max:50'],
            'mode'              => ['required', Rule::in([WhatsappMessage::TYPE_TEMPLATE, WhatsappMessage::TYPE_TEXT])],
            'body'              => ['exclude_unless:mode,text', 'required', 'string', 'max:4096'],
            'template_name'     => ['exclude_unless:mode,template', 'required', 'string', 'max:512', 'regex:/^[a-z0-9_]+$/'],
            'template_language' => ['exclude_unless:mode,template', 'required', 'string', 'max:15', 'regex:/^[A-Za-z0-9_]+$/'],
            'template_body'     => ['exclude_unless:mode,template', 'nullable', 'string', 'max:4096'],
            'params'            => ['exclude_unless:mode,template', 'nullable', 'array', 'max:20'],
            'params.*'          => ['exclude_unless:mode,template', 'required', 'string', 'max:1024'],
        ]);

        foreach (array_keys($data['params'] ?? []) as $key) {
            if (! preg_match('/^[A-Za-z0-9_]{1,64}$/', (string) $key)) {
                return $this->fail('Invalid template variable.');
            }
        }

        return $this->access()->runAs($ownerId, fn () => $this->deliver($record, $data));
    }

    /**
     * "Open in WhatsApp": log the chat and hand back the wa.me link. Works with
     * no Cloud API at all - the user sends from their own WhatsApp.
     */
    public function openChat(Request $request): JsonResponse
    {
        [$record, $ownerId] = $this->recipient($request);

        $data = $request->validate([
            'name'  => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'body'  => ['nullable', 'string', 'max:4096'],
        ]);

        return $this->access()->runAs($ownerId, function () use ($record, $data) {
            $phone = $this->normalizedPhone($data['phone']);

            if (! $phone) {
                return $this->fail($this->invalidPhoneMessage());
            }

            $body = (string) ($data['body'] ?? '');

            WhatsappMessage::create($this->recordLink($record, $data) + [
                'phone'   => $phone,
                'channel' => WhatsappMessage::CHANNEL_LINK,
                'type'    => WhatsappMessage::TYPE_TEXT,
                'body'    => $body !== '' ? $body : null,
                'status'  => WhatsappMessage::STATUS_MANUAL,
                'sent_at' => now(),
            ]);

            return response()->json(['ok' => true, 'url' => $this->whatsapp->chatLink($phone, $body)]);
        });
    }

    /**
     * Send and log one message. Runs as the owner of the record.
     */
    private function deliver(GmbLead|CashLead|null $record, array $data): JsonResponse
    {
        $setting = WhatsappSetting::forActingUser();

        if (! $setting?->canSend()) {
            return $this->fail('The WhatsApp Cloud API is not connected. Set it up under Settings > WhatsApp, or use "Open in WhatsApp".');
        }

        $phone = $this->normalizedPhone($data['phone'], $setting);

        if (! $phone) {
            return $this->fail($this->invalidPhoneMessage());
        }

        $isTemplate = $data['mode'] === WhatsappMessage::TYPE_TEMPLATE;

        $message = new WhatsappMessage($this->recordLink($record, $data) + [
            'phone'             => $phone,
            'channel'           => WhatsappMessage::CHANNEL_API,
            'type'              => $data['mode'],
            'template_name'     => $isTemplate ? $data['template_name'] : null,
            'template_language' => $isTemplate ? $data['template_language'] : null,
            'body'              => $isTemplate ? ($data['template_body'] ?? null) : $data['body'],
            'sent_at'           => now(),
        ]);

        try {
            $wamid = $isTemplate
                ? $this->whatsapp->sendTemplate($setting, $phone, $data['template_name'], $data['template_language'], $data['params'] ?? [])
                : $this->whatsapp->sendText($setting, $phone, $data['body']);
        } catch (\Throwable $e) {
            $message->fill(['status' => WhatsappMessage::STATUS_FAILED, 'error' => $e->getMessage()])->save();

            Log::warning('WhatsApp: send failed', ['message_id' => $message->id, 'error' => $e->getMessage()]);

            return $this->fail('WhatsApp did not send the message. ' . $e->getMessage());
        }

        $message->fill(['wamid' => $wamid, 'status' => WhatsappMessage::STATUS_SENT])->save();

        return response()->json([
            'ok'      => true,
            'message' => 'WhatsApp message sent to ' . ($message->contact_name ?: $message->display_phone) . '.',
        ]);
    }

    /**
     * The record a message is for - a GMB lead, a deal, or none for a typed-in
     * number - and the user it is sent as. Records come through the owner scope,
     * so an id from someone else's workspace is a 404.
     *
     * @return array{0: GmbLead|CashLead|null, 1: int}
     */
    private function recipient(Request $request): array
    {
        $data = $request->validate([
            'source' => ['required', Rule::in(self::SOURCES)],
            'id'     => ['nullable', 'required_unless:source,direct', 'integer'],
        ]);

        // Messaging a record needs the module the record lives in, too.
        $module = ['gmb' => 'gmb_leads', 'deal' => 'deals'][$data['source']] ?? null;
        abort_if($module && ! $request->user()->can('module', $module), 403, 'You do not have access to this module.');

        $record = match ($data['source']) {
            'gmb'    => GmbLead::findOrFail((int) $data['id']),
            'deal'   => CashLead::findOrFail((int) $data['id']),
            'direct' => null,
        };

        return [$record, $record?->user_id ?? $request->user()->id];
    }

    /** The record columns of a new message: its link and the contact name snapshot. */
    private function recordLink(GmbLead|CashLead|null $record, array $data): array
    {
        return [
            'gmb_lead_id'  => $record instanceof GmbLead ? $record->id : null,
            'cash_lead_id' => $record instanceof CashLead ? $record->id : null,
            'contact_name' => match (true) {
                $record instanceof GmbLead  => $record->name,
                $record instanceof CashLead => $record->company_name,
                default                     => $data['name'] ?? null,
            },
        ];
    }

    private function normalizedPhone(string $raw, ?WhatsappSetting $setting = null): ?string
    {
        $setting ??= WhatsappSetting::forActingUser();

        return $this->whatsapp->normalizePhone($raw, $this->whatsapp->countryCode($setting));
    }

    private function invalidPhoneMessage(): string
    {
        return 'Not a usable WhatsApp number. Write it with the country code (+91 98765 43210), '
            . 'or set a default country code under Settings > WhatsApp.';
    }

    private function fail(string $message): JsonResponse
    {
        return response()->json(['ok' => false, 'message' => $message], 422);
    }
}
