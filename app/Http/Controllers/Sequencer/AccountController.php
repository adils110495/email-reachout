<?php

namespace App\Http\Controllers\Sequencer;

use App\Http\Requests\Sequencer\ProfileRequest;
use App\Sequencer\Support\Tz;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** The signed-in user's profile, password and sequencer preferences. */
class AccountController extends SequencerController
{
    public function edit(): View
    {
        return view('sequencer.profile', ['user' => $this->user(), 'timezones' => Tz::all()]);
    }

    public function update(ProfileRequest $request): RedirectResponse
    {
        $this->user()->update($request->validated());

        return back()->with('success', 'Profile updated.');
    }

    public function password(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $this->user()->update(['password' => $data['password']]);

        return back()->with('success', 'Password changed.');
    }

    /** Defaults that pre-fill new accounts and sequences. */
    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'daily_limit' => ['required', 'integer', 'min:1', 'max:1000000'],
            'sending_start_time' => ['required', 'date_format:H:i'],
            'sending_end_time' => ['required', 'date_format:H:i', 'after:sending_start_time'],
            'sending_days' => ['required', 'array', 'min:1'],
            'sending_days.*' => ['integer', 'between:1,7'],
            'track_opens' => ['nullable', 'boolean'],
            'track_clicks' => ['nullable', 'boolean'],
        ]);

        $this->user()->update(['settings' => [
            'daily_limit' => (int) $data['daily_limit'],
            'sending_start_time' => $data['sending_start_time'],
            'sending_end_time' => $data['sending_end_time'],
            'sending_days' => array_values(array_map('intval', $data['sending_days'])),
            'track_opens' => $request->boolean('track_opens'),
            'track_clicks' => $request->boolean('track_clicks'),
        ]]);

        return back()->with('success', 'Settings saved.');
    }
}
