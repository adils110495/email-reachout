@extends('layouts.app')

@section('title', 'Profile & settings')
@section('page-title', 'Profile & settings')

@section('breadcrumb')
    <li class="breadcrumb-item active">Profile</li>
@endsection

@section('content')
@include('sequencer._errors')
@php $s = $user->settings ?? []; $days = old('sending_days', $s['sending_days'] ?? config('sequencer.defaults.sending_days')); @endphp
<div class="row">
    <div class="col-lg-6">
        <div class="card"><div class="card-header"><h4 class="card-title mb-0">Profile</h4></div>
            <form method="POST" action="{{ route('outreach.profile.update') }}" class="card-body">@csrf @method('PUT')
                <div class="mb-3"><label class="form-label">Username</label><input class="form-control" value="{{ $user->username }}" disabled></div>
                <div class="mb-3"><label class="form-label" for="name">Name</label><input class="form-control" id="name" name="name" value="{{ old('name', $user->name) }}" required></div>
                <div class="mb-3"><label class="form-label" for="email">Email <small class="text-muted">(needed for password reset)</small></label><input type="email" class="form-control" id="email" name="email" value="{{ old('email', $user->email) }}"></div>
                <div class="mb-3"><label class="form-label" for="timezone">Timezone</label>
                    <select class="form-select" id="timezone" name="timezone">@foreach ($timezones as $tz)<option value="{{ $tz }}" @selected(old('timezone', $user->timezoneName()) === $tz)>{{ $tz }}</option>@endforeach</select>
                    <div class="form-text">Times in the app are shown in this timezone. New sequences default to it.</div></div>
                <button class="btn btn-primary">Save profile</button>
            </form></div>

        <div class="card"><div class="card-header"><h4 class="card-title mb-0">Change password</h4></div>
            <form method="POST" action="{{ route('outreach.profile.password') }}" class="card-body">@csrf @method('PUT')
                <div class="mb-3"><label class="form-label" for="current_password">Current password</label><input type="password" class="form-control" id="current_password" name="current_password" autocomplete="current-password" required></div>
                <div class="mb-3"><label class="form-label" for="password">New password (min 8)</label><input type="password" class="form-control" id="password" name="password" autocomplete="new-password" required></div>
                <div class="mb-3"><label class="form-label" for="password_confirmation">Confirm new password</label><input type="password" class="form-control" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required></div>
                <button class="btn btn-primary">Change password</button>
            </form></div>
    </div>
    <div class="col-lg-6">
        <div class="card"><div class="card-header"><h4 class="card-title mb-0">Sequence defaults</h4></div>
            <form method="POST" action="{{ route('outreach.profile.settings') }}" class="card-body">@csrf @method('PUT')
                <div class="row g-3">
                    <div class="col-12"><label class="form-label" for="daily_limit">Daily limit</label><input type="number" min="1" class="form-control" id="daily_limit" name="daily_limit" value="{{ old('daily_limit', $s['daily_limit'] ?? config('sequencer.defaults.daily_limit')) }}" required></div>
                    <div class="col-6"><label class="form-label" for="st">Window start</label><input type="time" class="form-control" id="st" name="sending_start_time" value="{{ old('sending_start_time', $s['sending_start_time'] ?? config('sequencer.defaults.sending_start')) }}" required></div>
                    <div class="col-6"><label class="form-label" for="et">Window end</label><input type="time" class="form-control" id="et" name="sending_end_time" value="{{ old('sending_end_time', $s['sending_end_time'] ?? config('sequencer.defaults.sending_end')) }}" required></div>
                    <div class="col-12">@foreach ([1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat',7=>'Sun'] as $n => $l)
                        <div class="form-check form-check-inline"><input type="checkbox" class="form-check-input" id="sd{{ $n }}" name="sending_days[]" value="{{ $n }}" @checked(in_array($n, $days))><label class="form-check-label" for="sd{{ $n }}">{{ $l }}</label></div>@endforeach</div>
                    <div class="col-12">
                        <div class="form-check form-check-inline"><input type="checkbox" class="form-check-input" id="to" name="track_opens" value="1" @checked($s['track_opens'] ?? true)><label class="form-check-label" for="to">Track opens</label></div>
                        <div class="form-check form-check-inline"><input type="checkbox" class="form-check-input" id="tc" name="track_clicks" value="1" @checked($s['track_clicks'] ?? true)><label class="form-check-label" for="tc">Track clicks</label></div></div>
                </div>
                <button class="btn btn-primary mt-3">Save defaults</button>
            </form></div>
    </div>
</div>
@endsection
