@extends('layouts.auth')

@section('title', 'Sign in')
@section('heading', 'Welcome back')
@section('subheading', 'Sign in to continue to your workspace.')

@section('content')
<form method="POST" action="{{ route('login.attempt') }}" novalidate data-auth-form>
    @csrf

    <div class="mb-3">
        <label class="auth-label" for="login">Username or email</label>
        <div class="auth-field">
            <i class="bi bi-person auth-field-icon" aria-hidden="true"></i>
            <input type="text" id="login" name="login" value="{{ old('login') }}" autofocus required
                   autocomplete="username" placeholder="e.g. jane or jane@company.com"
                   class="form-control auth-input @error('login') is-invalid @enderror">
        </div>
        @error('login')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="mb-3">
        <label class="auth-label" for="password">Password</label>
        <div class="auth-field">
            <i class="bi bi-lock auth-field-icon" aria-hidden="true"></i>
            <input type="password" id="password" name="password" required autocomplete="current-password"
                   placeholder="Enter your password" data-capslock="passwordCapsLock"
                   class="form-control auth-input has-toggle @error('password') is-invalid @enderror">
            <button type="button" class="auth-toggle" data-toggle-password="password"
                    aria-label="Show password" aria-pressed="false">
                <i class="bi bi-eye" aria-hidden="true"></i>
            </button>
        </div>
        <div class="auth-hint auth-capslock" id="passwordCapsLock" hidden>
            <i class="bi bi-capslock-fill" aria-hidden="true"></i>Caps Lock is on
        </div>
        @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="form-check auth-remember mb-4">
        <input type="checkbox" class="form-check-input" id="remember" name="remember" value="1">
        <label class="form-check-label" for="remember">Keep me signed in</label>
    </div>

    <button type="submit" class="btn btn-primary w-100 auth-submit" data-loading-text="Signing in…">
        Sign in<i class="bi bi-arrow-right ms-2" aria-hidden="true"></i>
    </button>

    <p class="auth-switch">
        Don't have an account? <a href="{{ route('signup') }}">Create an account</a>
    </p>
</form>
@endsection
