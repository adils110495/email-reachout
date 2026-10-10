@extends('layouts.auth')

@section('title', 'Sign in')
@section('heading', 'Sign in')
@section('subheading', 'Welcome back. Enter your username and password.')

@section('content')
<form method="POST" action="{{ route('login.attempt') }}" novalidate>
    @csrf

    <div class="mb-3">
        <label class="form-label" for="login">Username</label>
        <input type="text" id="login" name="login" value="{{ old('login') }}" autofocus required
               autocomplete="username" class="form-control @error('login') is-invalid @enderror">
        @error('login')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="mb-3">
        <label class="form-label" for="password">Password</label>
        <input type="password" id="password" name="password" required autocomplete="current-password"
               class="form-control @error('password') is-invalid @enderror">
        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="form-check mb-0">
            <input type="checkbox" class="form-check-input" id="remember" name="remember" value="1">
            <label class="form-check-label" for="remember">Remember me</label>
        </div>
        <a href="{{ route('password.request') }}" class="fs-13">Forgot password?</a>
    </div>

    <button type="submit" class="btn btn-primary w-100">
        <i class="bi bi-box-arrow-in-right me-1"></i>Sign in
    </button>

    {{-- Sign-up link hidden for now. To show it again, remove this comment:
    <p class="text-center fs-13 mt-3 mb-0">
        New here? <a href="{{ route('signup') }}">Create an account</a>
    </p>
    --}}
</form>
@endsection
