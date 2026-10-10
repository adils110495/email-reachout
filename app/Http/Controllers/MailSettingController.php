<?php

namespace App\Http\Controllers;

use App\Models\MailSetting;
use App\Rules\PublicHost;
use App\Sequencer\Enums\EnrollmentStatus;
use App\Sequencer\Mail\EmailProviderManager;
use App\Services\MailConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Settings > Mail Settings: the sending accounts.
 *
 * Each account has SMTP (sending) and optionally IMAP (Sent-folder copy + reply /
 * bounce detection). The default account serves the Leads compose window; sequences
 * can send from any active account. Passwords are stored encrypted and never shown.
 */
class MailSettingController extends Controller
{
    public function __construct(
        private readonly MailConfigService $mailConfig,
        private readonly EmailProviderManager $providers,
    ) {}

    public function index(): View
    {
        return view('mail-settings.index', [
            'accounts' => MailSetting::orderByDesc('is_default')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('mail-settings.form', $this->formData(new MailSetting([
            'provider' => 'smtp',
            'port' => 587,
            'encryption' => 'tls',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_folder' => 'INBOX',
            'folder' => 'INBOX.Sent',
            'rate_limit_per_minute' => config('sequencer.defaults.rate_per_minute'),
            'is_active' => true,
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $account = new MailSetting($this->validated($request));
        $account->save();

        // The first account, or one explicitly ticked, becomes the default.
        if ($request->boolean('is_default') || MailSetting::where('is_default', true)->doesntExist()) {
            $account->makeDefault();
        }

        return redirect()->route('mail-settings.index')->with('success', "Account \"{$account->name}\" saved. Use Test to verify the connection.");
    }

    public function edit(MailSetting $account): View
    {
        return view('mail-settings.form', $this->formData($account));
    }

    public function update(Request $request, MailSetting $account): RedirectResponse
    {
        $data = $this->validated($request, $account);

        // A blank password keeps the stored one.
        foreach (['password', 'imap_password'] as $secret) {
            if (blank($data[$secret] ?? null)) {
                unset($data[$secret]);
            }
        }

        $account->fill($data)->save();

        if ($request->boolean('is_default')) {
            $account->makeDefault();
        }

        return redirect()->route('mail-settings.index')->with('success', 'Account updated.');
    }

    public function destroy(MailSetting $account): RedirectResponse
    {
        // Deleting an account that running enrollments depend on would strand them.
        $inUse = $account->enrollments()->whereIn('status', array_map(fn ($s) => $s->value, EnrollmentStatus::open()))->exists();
        if ($inUse) {
            return back()->with('error', 'This account is used by running sequence enrollments. Pause or remove them first, or deactivate the account instead.');
        }

        $wasDefault = $account->is_default;
        $account->delete();

        if ($wasDefault && ($next = MailSetting::active()->orderBy('id')->first())) {
            $next->makeDefault();
        }

        return redirect()->route('mail-settings.index')->with('success', 'Account deleted.');
    }

    public function makeDefault(MailSetting $account): RedirectResponse
    {
        $account->makeDefault();

        return back()->with('success', "\"{$account->name}\" is now the default sending account.");
    }

    /** Test a saved account's SMTP and IMAP and remember the result. */
    public function check(MailSetting $account): RedirectResponse
    {
        $result = $this->mailConfig->testAccount($account);

        $message = 'SMTP: '.$result['smtp']->message.($result['imap'] ? ' IMAP: '.$result['imap']->message : ' IMAP: not configured.');
        $ok = $result['smtp']->ok && (! $result['imap'] || $result['imap']->ok);

        return back()->with($ok ? 'success' : 'error', $message);
    }

    /**
     * Try the credentials in the form (falling back to the saved passwords when the
     * fields are left blank) without saving anything.
     */
    public function test(Request $request): JsonResponse
    {
        $existing = $request->filled('account_id') ? MailSetting::find($request->integer('account_id')) : null;

        $probe = new MailSetting;
        if ($existing) {
            $probe->setRawAttributes($existing->getAttributes(), true);   // stored (encrypted) values
        }

        $data = $this->validated($request, $existing);
        foreach (['password', 'imap_password'] as $secret) {
            if (blank($data[$secret] ?? null)) {
                unset($data[$secret]);
            }
        }
        $probe->fill($data);
        $probe->exists = false;   // never persist the probe

        $result = $this->mailConfig->testAccount($probe, remember: false);

        return response()->json([
            'smtp' => ['ok' => $result['smtp']->ok, 'message' => $result['smtp']->message],
            'imap' => $result['imap'] ? ['ok' => $result['imap']->ok, 'message' => $result['imap']->message] : null,
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?MailSetting $account = null): array
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'provider'     => ['nullable', 'string', Rule::in(array_keys($this->providers->available()))],
            'host'         => ['required', 'string', 'max:255', new PublicHost],
            'port'         => ['required', 'integer', 'between:1,65535'],
            'encryption'   => ['required', 'in:tls,ssl,none'],
            'username'     => ['nullable', 'string', 'max:255'],
            'password'     => ['nullable', 'string', 'max:500'],
            'from_address' => ['required', 'email', 'max:255'],
            'from_name'    => ['nullable', 'string', 'max:255'],

            'imap_host'       => ['nullable', 'string', 'max:255', new PublicHost],
            'imap_port'       => ['nullable', 'integer', 'between:1,65535'],
            'imap_encryption' => ['nullable', 'in:ssl,tls,none'],
            'imap_username'   => ['nullable', 'required_with:imap_host', 'string', 'max:255'],
            // Needed with a host unless one is already stored.
            'imap_password'   => ['nullable', Rule::requiredIf(fn () => $request->filled('imap_host') && blank($account?->imap_password)), 'string', 'max:500'],
            'imap_folder'     => ['nullable', 'string', 'max:255'],
            'folder'          => ['nullable', 'string', 'max:255'],

            'daily_limit'           => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'rate_limit_per_minute' => ['required', 'integer', 'between:1,1000'],
        ]);

        $data['provider'] = $data['provider'] ?? 'smtp';
        $data['is_active'] = $request->boolean('is_active');

        // IMAP is optional: an empty host means "no Sent copy, no reply detection".
        if (blank($data['imap_host'] ?? null)) {
            $data['imap_host'] = $data['imap_username'] = null;
            $data['imap_password'] = null;
            if ($account) {
                $account->imap_password = null;
            }
        } else {
            $data['imap_port'] = $data['imap_port'] ?? 993;
            $data['imap_encryption'] = $data['imap_encryption'] ?? 'ssl';
            $data['imap_folder'] = $data['imap_folder'] ?? 'INBOX';
            $data['folder'] = $data['folder'] ?? 'INBOX.Sent';
        }

        return $data;
    }

    private function formData(MailSetting $account): array
    {
        return ['account' => $account, 'providers' => $this->providers->available()];
    }
}
