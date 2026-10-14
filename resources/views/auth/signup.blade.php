@extends('layouts.auth')

@section('title', 'Sign up')
@section('heading', 'Create your account')
@section('subheading', 'Get your own private workspace to find and reach new clients.')

@section('content')
<form method="POST" action="{{ route('signup.attempt') }}" novalidate data-auth-form>
    @csrf

    <div class="mb-3">
        <label class="auth-label" for="name">Full name <span class="auth-required" aria-hidden="true">*</span></label>
        <div class="auth-field">
            <i class="bi bi-person auth-field-icon" aria-hidden="true"></i>
            <input type="text" id="name" name="name" value="{{ old('name') }}" autofocus required maxlength="255"
                   autocomplete="name" placeholder="Jane Cooper"
                   class="form-control auth-input @error('name') is-invalid @enderror">
        </div>
        @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    {{-- No username field: one is made from the email address, and the user
         can change it later on their Profile page. --}}
    <div class="mb-3">
        <label class="auth-label" for="email">Email address <span class="auth-required" aria-hidden="true">*</span></label>
        <div class="auth-field">
            <i class="bi bi-envelope auth-field-icon" aria-hidden="true"></i>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required maxlength="255"
                   autocomplete="email" placeholder="jane@company.com" aria-describedby="emailHint"
                   class="form-control auth-input @error('email') is-invalid @enderror">
        </div>
        <div class="auth-hint" id="emailHint">Your username is created from it — you can change it later in your profile.</div>
        @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-6">
            <label class="auth-label" for="password">Password <span class="auth-required" aria-hidden="true">*</span></label>
            <div class="auth-field">
                <i class="bi bi-lock auth-field-icon" aria-hidden="true"></i>
                <input type="password" id="password" name="password" required minlength="6"
                       autocomplete="new-password" placeholder="••••••••"
                       data-strength-source data-capslock="passwordCapsLock"
                       class="form-control auth-input has-toggle @error('password') is-invalid @enderror">
                <button type="button" class="auth-toggle" data-toggle-password="password"
                        aria-label="Show password" aria-pressed="false">
                    <i class="bi bi-eye" aria-hidden="true"></i>
                </button>
            </div>
            <div class="auth-strength" data-strength-meter aria-hidden="true">
                <span></span><span></span><span></span><span></span>
            </div>
            <div class="auth-hint" data-strength-label aria-live="polite">Use at least 6 characters.</div>
            <div class="auth-hint auth-capslock" id="passwordCapsLock" hidden>
                <i class="bi bi-capslock-fill" aria-hidden="true"></i>Caps Lock is on
            </div>
            @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>

        <div class="col-sm-6">
            <label class="auth-label" for="password_confirmation">Confirm password <span class="auth-required" aria-hidden="true">*</span></label>
            <div class="auth-field">
                <i class="bi bi-shield-lock auth-field-icon" aria-hidden="true"></i>
                <input type="password" id="password_confirmation" name="password_confirmation" required
                       autocomplete="new-password" placeholder="••••••••" data-match-source
                       class="form-control auth-input has-toggle">
                <button type="button" class="auth-toggle" data-toggle-password="password_confirmation"
                        aria-label="Show password" aria-pressed="false">
                    <i class="bi bi-eye" aria-hidden="true"></i>
                </button>
            </div>
            <div class="auth-hint auth-match" data-match-message aria-live="polite" hidden></div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary w-100 auth-submit" data-loading-text="Creating your account…">
        Create account<i class="bi bi-arrow-right ms-2" aria-hidden="true"></i>
    </button>

    <p class="auth-switch">
        Already have an account? <a href="{{ route('login') }}">Sign in</a>
    </p>
</form>
@endsection
