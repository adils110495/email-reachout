@extends('layouts.app')

@php $editing = $step->exists; @endphp
@section('title', $editing ? 'Edit step' : 'Add step')
@section('page-title', $editing ? 'Edit step '.$step->step_number : 'Add step')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('outreach.sequences.index') }}">Sequences</a></li>
    <li class="breadcrumb-item"><a href="{{ route('outreach.sequences.show', $sequence) }}">{{ $sequence->name }}</a></li>
    <li class="breadcrumb-item active">{{ $editing ? 'Edit step' : 'Add step' }}</li>
@endsection

@section('content')
@include('sequencer._errors')
<form method="POST" action="{{ $editing ? route('outreach.steps.update', $step) : route('outreach.steps.store', $sequence) }}">
    @csrf @if ($editing) @method('PUT') @endif
    <div class="row">
        <div class="col-lg-8">
            <div class="card"><div class="card-body">
                @if (! $editing && $templates->isNotEmpty())
                <div class="mb-3"><label class="form-label" for="template_id">Start from an <a href="{{ route('templates.index') }}">Email Template</a> <small class="text-muted">(fills blank subject/body)</small></label>
                    <select class="form-select" id="template_id" name="template_id"><option value="">— none —</option>
                        @foreach ($templates as $t)<option value="{{ $t->id }}" @selected((int) old('template_id') === $t->id)>{{ $t->name }}</option>@endforeach</select></div>
                @endif
                <div class="mb-3"><label class="form-label" for="subject">Subject</label><input class="form-control" id="subject" name="subject" value="{{ old('subject', $step->subject) }}" maxlength="998"></div>
                <div class="mb-3"><label class="form-label" for="body">Body <small class="text-muted">plain text or HTML</small></label>
                    <textarea class="form-control font-monospace" id="body" name="body" rows="14">{{ old('body', $step->body) }}</textarea></div>
                <fieldset class="mb-3"><legend class="form-label fs-6">Wait before sending {{ ($editing ? $step->step_number : 2) > 1 ? 'this step (after the previous one)' : 'this step (after enrolment)' }}</legend>
                    <div class="row g-2">
                        <div class="col-4"><div class="input-group"><input type="number" min="0" max="365" class="form-control" name="delay_days" value="{{ old('delay_days', $step->delay_days ?? 0) }}" aria-label="Days"><span class="input-group-text">days</span></div></div>
                        <div class="col-4"><div class="input-group"><input type="number" min="0" max="23" class="form-control" name="delay_hours" value="{{ old('delay_hours', $step->delay_hours ?? 0) }}" aria-label="Hours"><span class="input-group-text">hours</span></div></div>
                        <div class="col-4"><div class="input-group"><input type="number" min="0" max="59" class="form-control" name="delay_minutes" value="{{ old('delay_minutes', $step->delay_minutes ?? 0) }}" aria-label="Minutes"><span class="input-group-text">min</span></div></div>
                    </div></fieldset>
                @if ($editing)
                <div class="mb-3"><label class="form-label" for="status">Status</label>
                    <select class="form-select w-auto" id="status" name="status"><option value="active" @selected($step->status->value === 'active')>Active</option><option value="inactive" @selected($step->status->value === 'inactive')>Inactive (skipped)</option></select></div>
                @endif
                <button class="btn btn-primary">Save step</button> <a href="{{ route('outreach.sequences.show', $sequence) }}" class="btn btn-link">Cancel</a>
                @if ($editing)<a href="{{ route('outreach.steps.preview', $step) }}" class="btn btn-light float-end"><i class="bi bi-eye me-1"></i>Preview</a>@endif
            </div></div>
        </div>
        <div class="col-lg-4">@include('sequencer._variables', ['variables' => $variables])</div>
    </div>
</form>
@endsection
