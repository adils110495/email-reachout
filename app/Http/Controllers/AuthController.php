<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    /**
     * Sign in with a username (or an email address) and a password.
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'login'    => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $field = filter_var($credentials['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if (Auth::attempt([$field => $credentials['login'], 'password' => $credentials['password']], $request->boolean('remember'))) {
            if (! Auth::user()->is_active) {
                Auth::logout();

                return back()
                    ->withErrors(['login' => 'This account has been deactivated. Contact the administrator.'])
                    ->onlyInput('login');
            }

            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'));
        }

        // One message for "no such user" and "wrong password", so the form does
        // not reveal which usernames exist.
        return back()
            ->withErrors(['login' => 'These credentials do not match our records.'])
            ->onlyInput('login');
    }

    public function showSignup(): View
    {
        return view('auth.signup');
    }

    public function signup(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        // The form does not ask for a username: one is made from the email
        // (the user can change it on their Profile). Only the validated fields
        // reach the model, and role / is_active / modules are not fillable, so
        // a signup is always a normal user with their own empty workspace.
        // Retried in case two sign-ups grab the same free username at once.
        $user = retry(3, fn () => User::create($data + [
            'username' => User::uniqueUsernameFrom($data['email'], $data['name']),
        ]), 0, fn ($e) => $e instanceof UniqueConstraintViolationException);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success',
            "Welcome, {$user->name}! Your username is \"{$user->username}\" - sign in with it or your email. You can change it from My Profile.");
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
