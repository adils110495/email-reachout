<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The signed-in user's own account: the account details, and a new username
 * once every User::USERNAME_CHANGE_DAYS days (the first change is free - the
 * sign-up username is generated).
 */
class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        return view('profile.index', [
            'user'       => $request->user(),
            'changeDays' => User::USERNAME_CHANGE_DAYS,
        ]);
    }

    public function updateUsername(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->canChangeUsername()) {
            return back()->with('error', 'You can change your username again on ' . $user->usernameChangeAvailableAt()->format('j M Y') . '.');
        }

        $data = $request->validate(
            ['username' => [...User::usernameRules($user), Rule::notIn([$user->username])]],
            ['username.not_in' => 'That is already your username.'],
        );

        // One conditional UPDATE, so two quick submits cannot both slip past
        // the limit; the unique index settles a name taken in the meantime.
        try {
            $changed = User::whereKey($user->id)
                ->where(fn ($q) => $q->whereNull('username_changed_at')
                    ->orWhere('username_changed_at', '<=', now()->subDays(User::USERNAME_CHANGE_DAYS)))
                ->update(['username' => $data['username'], 'username_changed_at' => now()]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['username' => 'The username has already been taken.']);
        }

        if ($changed === 0) {
            return back()->with('error', 'You can change your username only once every ' . User::USERNAME_CHANGE_DAYS . ' days.');
        }

        return redirect()->route('profile.index')
            ->with('success', "Your username is now \"{$data['username']}\". Sign in with it or your email.");
    }
}
