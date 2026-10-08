@extends('layouts.auth')

@section('title', 'Sign up')
@section('heading', 'Create an account')
@section('subheading', 'Pick a username and password to get started.')

@section('content')
<form method="POST" action="{{ route('signup.attempt') }}" novalidate>
    @csrf

    <div class="mb-3">
        <label class="form-label" for="name">Full name <span class="text-danger">*</span></label>
        <input type="text" id="name" name="name" value="{{ old('name') }}" autofocus required maxlength="255"
               autocomplete="name" class="form-control @error('name') is-invalid @enderror">
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="mb-3">
        <label class="form-label" for="username">Username <span class="text-danger">*</span></label>
        <input type="text" id="username" name="username" value="{{ old('username') }}" required minlength="3" maxlength="50"
               autocomplete="username" class="form-control @error('username') is-invalid @enderror">
        <div class="form-text">Letters, numbers, dashes and underscores.</div>
        @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="mb-3">
        <label class="form-label" for="email">Email <span class="text-muted">(optional)</span></label>
        <input type="email" id="email" name="email" value="{{ old('email') }}" maxlength="255"
               autocomplete="email" class="form-control @error('email') is-invalid @enderror">
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="mb-3">
        <label class="form-label" for="password">Password <span class="text-danger">*</span></label>
        <input type="password" id="password" name="password" required minlength="6"
               autocomplete="new-password" class="form-control @error('password') is-invalid @enderror">
        <div class="form-text">At least 6 characters.</div>
        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="mb-4">
        <label class="form-label" for="password_confirmation">Confirm password <span class="text-danger">*</span></label>
        <input type="password" id="password_confirmation" name="password_confirmation" required
               autocomplete="new-password" class="form-control">
    </div>

    <button type="submit" class="btn btn-primary w-100">
        <i class="bi bi-person-plus me-1"></i>Create account
    </button>

    <p class="text-center fs-13 mt-3 mb-0">
        Already have an account? <a href="{{ route('login') }}">Sign in</a>
    </p>
</form>
@endsection
