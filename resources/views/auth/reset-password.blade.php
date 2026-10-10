@extends('layouts.auth')

@section('title', 'Reset password')
@section('heading', 'Choose a new password')
@section('subheading', 'Use at least 8 characters.')

@section('content')
<form method="POST" action="{{ route('password.update') }}" novalidate>
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <div class="mb-3">
        <label class="form-label" for="email">Email</label>
        <input type="email" id="email" name="email" value="{{ old('email', $email) }}" required autocomplete="email" class="form-control @error('email') is-invalid @enderror">
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="mb-3">
        <label class="form-label" for="password">New password</label>
        <input type="password" id="password" name="password" required autocomplete="new-password" class="form-control @error('password') is-invalid @enderror">
        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="mb-4">
        <label class="form-label" for="password_confirmation">Confirm password</label>
        <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password" class="form-control">
    </div>
    <button type="submit" class="btn btn-primary w-100">Reset password</button>
</form>
@endsection
