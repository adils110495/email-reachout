@extends('layouts.app')

@php
    $editing = $sequence->exists;
    $days = old('sending_days', $sequence->sending_days ?? [1,2,3,4,5]);
    $dayNames = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
@endphp
@section('title', $editing ? 'Edit sequence' : 'New sequence')
@section('page-title', $editing ? 'Edit sequence' : 'New sequence')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('outreach.sequences.index') }}">Sequences</a></li>
    <li class="breadcrumb-item active">{{ $editing ? 'Edit' : 'New' }}</li>
@endsection

@section('content')
@include('sequencer._errors')
<form method="POST" action="{{ $editing ? route('outreach.sequences.update', $sequence) : route('outreach.sequences.store') }}">
    @csrf @if ($editing) @method('PUT') @endif
    <div class="card"><div class="card-header"><h4 class="card-title mb-0">Details</h4></div><div class="card-body row g-3">
        <div class="col-md-6"><label class="form-label" for="name">Name</label><input class="form-control" id="name" name="name" value="{{ old('name', $sequence->name) }}" required></div>
        <div class="col-md-6"><label class="form-label" for="mail_setting_id">Send from <a href="{{ route('mail-settings.index') }}" class="fs-13 ms-1">(Mail Settings)</a></label>
            <select class="form-select" id="mail_setting_id" name="mail_setting_id"><option value="">Default account</option>
                @foreach ($accounts as $a)<option value="{{ $a->id }}" @selected((int) old('mail_setting_id', $sequence->mail_setting_id) === $a->id)>{{ $a->label() }}</option>@endforeach</select></div>
        <div class="col-12"><label class="form-label" for="description">Description</label><textarea class="form-control" id="description" name="description" rows="2">{{ old('description', $sequence->description) }}</textarea></div>
    </div></div>

    <div class="card"><div class="card-header"><h4 class="card-title mb-0">Sending schedule</h4></div><div class="card-body row g-3">
        <div class="col-md-4"><label class="form-label" for="timezone">Timezone</label>
            <select class="form-select" id="timezone" name="timezone">@foreach ($timezones as $tz)<option value="{{ $tz }}" @selected(old('timezone', $sequence->timezone) === $tz)>{{ $tz }}</option>@endforeach</select></div>
        <div class="col-6 col-md-2"><label class="form-label" for="sending_start_time">From</label><input type="time" class="form-control" id="sending_start_time" name="sending_start_time" value="{{ old('sending_start_time', substr((string) $sequence->sending_start_time, 0, 5)) }}" required></div>
        <div class="col-6 col-md-2"><label class="form-label" for="sending_end_time">Until</label><input type="time" class="form-control" id="sending_end_time" name="sending_end_time" value="{{ old('sending_end_time', substr((string) $sequence->sending_end_time, 0, 5)) }}" required></div>
        <div class="col-md-4"><label class="form-label" for="daily_limit">Daily limit (emails/day)</label><input type="number" min="1" class="form-control" id="daily_limit" name="daily_limit" value="{{ old('daily_limit', $sequence->daily_limit) }}" required></div>
        <div class="col-12"><span class="form-label d-block">Sending days</span>
            @foreach ($dayNames as $n => $label)
                <div class="form-check form-check-inline"><input type="checkbox" class="form-check-input" id="d{{ $n }}" name="sending_days[]" value="{{ $n }}" @checked(in_array($n, $days))><label class="form-check-label" for="d{{ $n }}">{{ $label }}</label></div>
            @endforeach
            <div class="form-text">Emails that fall due outside these days and hours wait for the next allowed time.</div></div>
        <div class="col-12">
            <div class="form-check form-check-inline"><input type="checkbox" class="form-check-input" id="track_opens" name="track_opens" value="1" @checked(old('track_opens', $sequence->track_opens ?? true))><label class="form-check-label" for="track_opens">Track opens</label></div>
            <div class="form-check form-check-inline"><input type="checkbox" class="form-check-input" id="track_clicks" name="track_clicks" value="1" @checked(old('track_clicks', $sequence->track_clicks ?? true))><label class="form-check-label" for="track_clicks">Track link clicks</label></div>
        </div>
    </div></div>
    <button class="btn btn-primary">Save sequence</button> <a href="{{ $editing ? route('outreach.sequences.show', $sequence) : route('outreach.sequences.index') }}" class="btn btn-link">Cancel</a>
</form>
@endsection
