<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BrandingController extends Controller
{
    /** Uploads live under public/ so email clients can fetch them by URL. */
    private const UPLOAD_DIR = 'uploads/branding';

    private const KEYS = [AppSetting::ADMIN_LOGO, AppSetting::ADMIN_ICON, AppSetting::EMAIL_LOGO];

    public function index(): View
    {
        return view('branding.index');
    }

    public function update(Request $request): RedirectResponse
    {
        // No SVG: it can carry scripts, and most email clients will not render it.
        $request->validate([
            AppSetting::ADMIN_LOGO => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            AppSetting::ADMIN_ICON => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            AppSetting::EMAIL_LOGO => ['nullable', 'image', 'mimes:png,jpg,jpeg,gif', 'max:2048'],
        ], [], [
            AppSetting::ADMIN_LOGO => 'admin panel logo',
            AppSetting::ADMIN_ICON => 'admin panel icon',
            AppSetting::EMAIL_LOGO => 'email logo',
        ]);

        $saved = 0;
        foreach (self::KEYS as $key) {
            if ($request->hasFile($key)) {
                $this->store($key, $request->file($key));
                $saved++;
            }
        }

        return redirect()->route('branding.index')->with(
            $saved ? 'success' : 'error',
            $saved ? 'Branding updated.' : 'Choose at least one image to upload.',
        );
    }

    /** Company name and social links shown in the outreach email footer. */
    public function updateFooter(Request $request): RedirectResponse
    {
        $rules = [AppSetting::COMPANY_NAME => ['nullable', 'string', 'max:255']];
        $names = [AppSetting::COMPANY_NAME => 'company name'];
        foreach (AppSetting::SOCIALS as $key => $social) {
            $rules[$key] = ['nullable', 'url:http,https', 'max:255'];
            $names[$key] = $social['label'].' URL';
        }

        $data = $request->validate($rules, [], $names);

        foreach ($data as $key => $value) {
            AppSetting::write($key, filled($value) ? trim($value) : null);
        }

        return redirect()->route('branding.index')->with('success', 'Email footer updated.');
    }

    /** Remove an uploaded image so the default artwork is used again. */
    public function reset(string $key): RedirectResponse
    {
        abort_unless(in_array($key, self::KEYS, true), 404);

        $this->deleteFile(AppSetting::read($key));
        AppSetting::write($key, null);

        return redirect()->route('branding.index')->with('success', 'Reset to the default image.');
    }

    private function store(string $key, UploadedFile $file): void
    {
        $old  = AppSetting::read($key);
        $name = $key.'-'.Str::random(8).'.'.strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());

        $file->move(public_path(self::UPLOAD_DIR), $name);
        AppSetting::write($key, self::UPLOAD_DIR.'/'.$name);

        $this->deleteFile($old);
    }

    /** Only ever deletes files we uploaded - never the shipped defaults. */
    private function deleteFile(?string $path): void
    {
        if ($path && str_starts_with($path, self::UPLOAD_DIR.'/') && is_file(public_path($path))) {
            @unlink(public_path($path));
        }
    }
}
