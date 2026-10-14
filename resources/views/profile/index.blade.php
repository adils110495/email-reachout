@extends('layouts.app')

@section('title', 'My Profile')
@section('page-title', 'My Profile')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">My Profile</li>
@endsection

@section('content')

@php
    $canChange   = $user->canChangeUsername();
    $availableAt = $user->usernameChangeAvailableAt();
@endphp

<div class="row">

    {{-- Account summary --}}
    <div class="col-xl-4 col-lg-5">
        <div class="card">
            <div class="card-body text-center py-4">
                <div class="profile-avatar mx-auto mb-3" aria-hidden="true">{{ Str::upper(Str::substr($user->name, 0, 1)) }}</div>
                <h4 class="mb-1">{{ $user->name }}</h4>
                <p class="text-muted mb-2">{{ '@' . $user->username }}</p>
                @if($user->isSuperAdmin())
                    <span class="badge badge-primary light">Super Admin</span>
                @else
                    <span class="badge badge-secondary light">User</span>
                @endif
            </div>
            <ul class="list-group list-group-flush fs-13">
                <li class="list-group-item d-flex justify-content-between gap-3">
                    <span class="text-muted">Email</span>
                    <span class="text-end text-break">{{ $user->email ?: '—' }}</span>
                </li>
                <li class="list-group-item d-flex justify-content-between gap-3">
                    <span class="text-muted">Member since</span>
                    <span>{{ $user->created_at?->format('j M Y') ?? '—' }}</span>
                </li>
            </ul>
        </div>
    </div>

    {{-- Username --}}
    <div class="col-xl-8 col-lg-7">
        <div class="card">
            <div class="card-header py-3 d-block">
                <h4 class="card-title"><i class="bi bi-at me-2 text-primary"></i>Username</h4>
                <p class="mb-0 fs-13">You sign in with your username or your email address. The username can be changed once every {{ $changeDays }} days.</p>
            </div>

            <div class="card-body">
                <form method="POST" action="{{ route('profile.username') }}"
                      @if($canChange) onsubmit="return confirm('Change your username? You will not be able to change it again for {{ $changeDays }} days.')" @endif>
                    @csrf @method('PUT')

                    <div class="mb-3">
                        <label class="form-label" for="username">Username</label>
                        <div class="input-group profile-username">
                            <span class="input-group-text">@</span>
                            <input type="text" id="username" name="username" maxlength="50" required autocomplete="username"
                                   value="{{ old('username', $user->username) }}" @disabled(! $canChange)
                                   class="form-control @error('username') is-invalid @enderror" aria-describedby="usernameHelp">
                        </div>
                        <div class="form-text" id="usernameHelp">3–50 characters: letters, numbers, dashes (-) and underscores (_). It must not be used by anyone else.</div>
                        @error('username')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    @if($canChange)
                        <div class="alert alert-info d-flex align-items-start gap-2 fs-13 mb-4" role="note">
                            <i class="bi bi-info-circle-fill mt-1"></i>
                            <span>
                                @if($user->username_changed_at)
                                    You last changed your username on {{ $user->username_changed_at->format('j M Y') }}.
                                @else
                                    You have not changed your username yet.
                                @endif
                                After a change, the next one is possible {{ $changeDays }} days later.
                            </span>
                        </div>
                    @else
                        <div class="alert alert-warning d-flex align-items-start gap-2 fs-13 mb-4" role="status">
                            <i class="bi bi-hourglass-split mt-1"></i>
                            <span>
                                You changed your username on {{ $user->username_changed_at->format('j M Y') }}.
                                You can change it again on <strong>{{ $availableAt->format('j M Y') }}</strong> ({{ $availableAt->diffForHumans() }}).
                            </span>
                        </div>
                    @endif

                    <button type="submit" class="btn btn-primary" @disabled(! $canChange)>
                        <i class="bi bi-check2 me-1"></i>Save username
                    </button>
                </form>
            </div>
        </div>
    </div>

</div>

@endsection
