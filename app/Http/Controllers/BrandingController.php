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

    private const KEYS = [AppSetting::ADMIN_LOGO, AppSetting::ADMIN_ICON];

    public function index(): View
    {
        return view('branding.index');
    }

    /** Brand name plus any logo/icon chosen in the form. */
    public function update(Request $request): RedirectResponse
    {
        // The logo also goes into emails: no SVG (can carry scripts) or WEBP (most mail clients will not show it).
        $data = $request->validate([
            AppSetting::COMPANY_NAME => ['required', 'string', 'max:255'],
            AppSetting::ADMIN_LOGO   => ['nullable', 'image', 'mimes:png,jpg,jpeg,gif', 'max:2048'],
            AppSetting::ADMIN_ICON   => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
        ], [], [
            AppSetting::COMPANY_NAME => 'brand name',
            AppSetting::ADMIN_LOGO   => 'logo',
            AppSetting::ADMIN_ICON   => 'icon',
        ]);

        AppSetting::write(AppSetting::COMPANY_NAME, trim($data[AppSetting::COMPANY_NAME]));

        foreach (self::KEYS as $key) {
            if ($request->hasFile($key)) {
                $this->store($key, $request->file($key));
            }
        }

        return redirect()->route('branding.index')->with('success', 'Branding updated.');
    }

    /** Social links shown in the outreach email footer. */
    public function updateFooter(Request $request): RedirectResponse
    {
        $rules = [];
        $names = [];
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
