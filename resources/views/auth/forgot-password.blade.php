@extends('layouts.auth')

@section('title', 'Forgot password')
@section('heading', 'Forgot your password?')
@section('subheading', 'Enter the email address on your account and we will send you a reset link.')

@section('content')
<form method="POST" action="{{ route('password.email') }}" novalidate>
    @csrf
    <div class="mb-3">
        <label class="form-label" for="email">Email</label>
        <input type="email" id="email" name="email" value="{{ old('email') }}" autofocus required autocomplete="email" class="form-control @error('email') is-invalid @enderror">
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <button type="submit" class="btn btn-primary w-100">Send reset link</button>
    <p class="text-center fs-13 mt-3 mb-0"><a href="{{ route('login') }}">Back to sign in</a></p>
</form>
@endsection
