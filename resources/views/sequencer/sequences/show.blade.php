@extends('layouts.app')

@section('title', $sequence->name)
@section('page-title', 'Sequence')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('outreach.sequences.index') }}">Sequences</a></li>
    <li class="breadcrumb-item active">{{ $sequence->name }}</li>
@endsection

@section('content')
@include('sequencer._errors')
@use('App\Sequencer\Enums\SequenceStatus', 'SS')
@php $a = $analytics; @endphp

<div class="card">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
            <h3 class="mb-1">{{ $sequence->name }} @include('sequencer._badge', ['status' => $sequence->status])</h3>
            <div class="text-muted fs-13">{{ $sequence->description }}</div>
            <div class="fs-13 mt-2">
                <i class="bi bi-clock me-1"></i>{{ $sequence->startTime() }}–{{ $sequence->endTime() }} {{ $sequence->effectiveTimezone() }},
                {{ collect($sequence->sending_days)->map(fn ($d) => [1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat',7=>'Sun'][$d] ?? '')->implode(' ') }}
                · <i class="bi bi-speedometer2 mx-1"></i>{{ $sequence->daily_limit }}/day
                · <i class="bi bi-at mx-1"></i>{{ $sequence->mailSetting?->senderEmail() ?? 'default account' }}
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @if (in_array($sequence->status, [SS::Draft]))
                <form method="POST" action="{{ route('outreach.sequences.activate', $sequence) }}">@csrf <button class="btn btn-success"><i class="bi bi-play-fill me-1"></i>Activate</button></form>
            @elseif ($sequence->status === SS::Active)
                <form method="POST" action="{{ route('outreach.sequences.pause', $sequence) }}">@csrf <button class="btn btn-warning"><i class="bi bi-pause-fill me-1"></i>Pause</button></form>
            @elseif ($sequence->status === SS::Paused)
                <form method="POST" action="{{ route('outreach.sequences.resume', $sequence) }}">@csrf <button class="btn btn-success"><i class="bi bi-play-fill me-1"></i>Resume</button></form>
            @endif
            <a href="{{ route('outreach.enrollments.index', $sequence) }}" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>Leads ({{ $a['enrollments']['total'] }})</a>
            <a href="{{ route('outreach.sequences.analytics', $sequence) }}" class="btn btn-light"><i class="bi bi-graph-up me-1"></i>Analytics</a>
            <div class="dropdown"><button class="btn btn-light" data-bs-toggle="dropdown" aria-label="More"><i class="bi bi-three-dots-vertical"></i></button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="{{ route('outreach.sequences.edit', $sequence) }}"><i class="bi bi-pencil me-2"></i>Edit settings</a></li>
                    <li><form method="POST" action="{{ route('outreach.sequences.duplicate', $sequence) }}">@csrf <button class="dropdown-item"><i class="bi bi-copy me-2"></i>Duplicate</button></form></li>
                    @if ($sequence->status !== SS::Archived)<li><form method="POST" action="{{ route('outreach.sequences.archive', $sequence) }}">@csrf <button class="dropdown-item"><i class="bi bi-archive me-2"></i>Archive</button></form></li>@endif
                    <li><form method="POST" action="{{ route('outreach.sequences.destroy', $sequence) }}" onsubmit="return confirm('Delete this sequence and its enrollments?')">@csrf @method('DELETE') <button class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>Delete</button></form></li>
                </ul></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="card-title mb-0">Steps</h4>
        <a href="{{ route('outreach.steps.create', $sequence) }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Add step</a>
    </div>
    @if ($sequence->steps->isEmpty())
        <div class="card-body"><div class="empty-state"><i class="bi bi-envelope empty-state-icon"></i>No steps yet. <a href="{{ route('outreach.steps.create', $sequence) }}">Add the first email</a> to this sequence.</div></div>
    @else
    <ul class="list-group list-group-flush">
        @foreach ($sequence->steps as $step)
            <li class="list-group-item d-flex flex-wrap gap-3 align-items-center">
                <span class="badge badge-primary light fs-14">{{ $step->step_number }}</span>
                <div class="flex-grow-1" style="min-width:200px">
                    <div class="fw-semibold">{{ $step->subject }}</div>
                    <div class="fs-13 text-muted">{{ $loop->first && $step->delayInMinutes() === 0 ? 'Sent right after enrolment' : 'Wait '.$step->delayLabel().' after the previous step' }} @if ($step->status->value !== 'active')<span class="badge badge-secondary light">inactive</span>@endif</div>
                </div>
                <div class="text-nowrap">
                    @if (! $loop->first)<form method="POST" action="{{ route('outreach.steps.move', $step) }}" class="d-inline">@csrf <input type="hidden" name="direction" value="up"><button class="btn btn-sm btn-light" aria-label="Move up"><i class="bi bi-arrow-up"></i></button></form>@endif
                    @if (! $loop->last)<form method="POST" action="{{ route('outreach.steps.move', $step) }}" class="d-inline">@csrf <input type="hidden" name="direction" value="down"><button class="btn btn-sm btn-light" aria-label="Move down"><i class="bi bi-arrow-down"></i></button></form>@endif
                    <a class="btn btn-sm btn-light" href="{{ route('outreach.steps.preview', $step) }}" aria-label="Preview"><i class="bi bi-eye"></i></a>
                    <a class="btn btn-sm btn-light" href="{{ route('outreach.steps.edit', $step) }}" aria-label="Edit"><i class="bi bi-pencil"></i></a>
                    <form method="POST" action="{{ route('outreach.steps.duplicate', $step) }}" class="d-inline">@csrf <button class="btn btn-sm btn-light" aria-label="Duplicate"><i class="bi bi-copy"></i></button></form>
                    <form method="POST" action="{{ route('outreach.steps.destroy', $step) }}" class="d-inline" onsubmit="return confirm('Delete this step?')">@csrf @method('DELETE') <button class="btn btn-sm btn-danger light" aria-label="Delete"><i class="bi bi-trash"></i></button></form>
                </div>
            </li>
        @endforeach
    </ul>
    @endif
</div>

<div class="row">
    @foreach (['active' => 'Active', 'completed' => 'Completed', 'replied' => 'Replied', 'bounced' => 'Bounced', 'unsubscribed' => 'Unsubscribed', 'paused' => 'Paused'] as $k => $label)
        <div class="col-6 col-md-2 mb-3"><div class="card h-100 mb-0"><div class="card-body py-3"><div class="text-muted fs-13">{{ $label }}</div><div class="fs-3 fw-semibold">{{ $a['enrollments'][$k] }}</div></div></div></div>
    @endforeach
</div>
@endsection
