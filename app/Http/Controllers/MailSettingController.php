<?php

namespace App\Http\Controllers;

use App\Models\MailSetting;
use App\Services\ImapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

/**
 * Each user's own SMTP / IMAP. Always mine() - a super admin manages their own
 * mailbox here too, never another user's credentials.
 */
class MailSettingController extends Controller
{
    public function index(): View
    {
        return view('mail-settings.index', [
            'smtp' => MailSetting::mine()->where('type', MailSetting::TYPE_SMTP)->first(),
            'imap' => MailSetting::mine()->where('type', MailSetting::TYPE_IMAP)->first(),
        ]);
    }

    public function updateSmtp(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'host'         => ['required', 'string', 'max:255'],
            'port'         => ['required', 'integer', 'between:1,65535'],
            'encryption'   => ['required', 'in:tls,ssl,none'],
            'username'     => ['nullable', 'string', 'max:255'],
            'password'     => ['nullable', 'string', 'max:255'],
            'from_address' => ['required', 'email', 'max:255'],
            'from_name'    => ['nullable', 'string', 'max:255'],
        ]);

        $this->save(MailSetting::TYPE_SMTP, $data, $request->boolean('is_active'));

        return redirect()->route('mail-settings.index')->with('success', 'SMTP settings saved.');
    }

    public function updateImap(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'host'       => ['required', 'string', 'max:255'],
            'port'       => ['required', 'integer', 'between:1,65535'],
            'encryption' => ['required', 'in:ssl,tls,notls'],
            'username'   => ['required', 'string', 'max:255'],
            'password'   => ['nullable', 'string', 'max:255'],
            'folder'     => ['required', 'string', 'max:255'],
        ]);

        $this->save(MailSetting::TYPE_IMAP, $data, $request->boolean('is_active'));

        return redirect()->route('mail-settings.index')->with('success', 'IMAP settings saved.');
    }

    /**
     * Try the credentials in the form (falling back to the saved password when
     * the field is left blank) without saving anything.
     */
    public function test(Request $request, string $type): JsonResponse
    {
        abort_unless(in_array($type, [MailSetting::TYPE_SMTP, MailSetting::TYPE_IMAP], true), 404);

        $request->validate([
            'host' => ['required', 'string'],
            'port' => ['required', 'integer'],
        ]);

        $saved = MailSetting::mine()->where('type', $type)->first();

        if (! $request->filled('password') && $saved?->passwordUnreadable()) {
            return response()->json(['ok' => false, 'message' => 'The saved password cannot be read on this server (it was saved with a different APP_KEY). Type the password in the form and test again.']);
        }

        $password = $request->filled('password') ? $request->input('password') : (string) $saved?->readablePassword();
        $username = (string) $request->input('username');
        $host     = $request->input('host');
        $port     = (int) $request->input('port');
        $enc      = $request->input('encryption');

        try {
            if ($type === MailSetting::TYPE_SMTP) {
                // ssl = implicit TLS (465); tls = STARTTLS (587); none = plain
                $transport = new EsmtpTransport($host, $port, $enc === 'ssl');
                if ($enc === 'none') {
                    $transport->setAutoTls(false);
                }
                if ($username !== '') {
                    $transport->setUsername($username)->setPassword($password);
                }
                $transport->getStream()->setTimeout(10);
                $transport->start();
                $transport->stop();
                $error = null;
            } else {
                $error = app(ImapService::class)->testConnection(
                    $host, $port, $enc ?: 'ssl', $username, $password, (string) $request->input('folder', 'INBOX'),
                );
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return response()->json(
            ['ok' => $error === null, 'message' => $error ?? 'Connection successful.'],
        );
    }

    /**
     * Upsert one row; a blank password keeps the stored one.
     */
    private function save(string $type, array $data, bool $active): void
    {
        $setting = MailSetting::mine()->firstOrNew(['type' => $type]);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $setting->fill($data + ['is_active' => $active])->save();
    }
}
