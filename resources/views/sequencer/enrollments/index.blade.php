@extends('layouts.app')

@section('title', $sequence->name.' contacts')
@section('page-title', 'Sequence leads')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('outreach.sequences.index') }}">Sequences</a></li>
    <li class="breadcrumb-item"><a href="{{ route('outreach.sequences.show', $sequence) }}">{{ $sequence->name }}</a></li>
    <li class="breadcrumb-item active">Leads</li>
@endsection

@section('content')
@include('sequencer._errors')
<div class="row">
<div class="col-xl-9">
<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between gap-2">
        <h4 class="card-title mb-0">Enrolled leads <small class="text-muted">({{ $enrollments->total() }})</small> @include('sequencer._badge', ['status' => $sequence->status])</h4>
    </div>
    <div class="card-header d-block pb-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-md-5"><label class="form-label" for="q">Search</label><input class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Email, name, company"></div>
            <div class="col-6 col-md-3"><label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status"><option value="">All</option>
                    @foreach ($statuses as $s)<option value="{{ $s->value }}" @selected(($filters['status'] ?? '') === $s->value)>{{ $s->label() }}</option>@endforeach</select></div>
            <div class="col-6 col-md-4 d-flex gap-2"><button class="btn btn-primary">Filter</button><a href="{{ route('outreach.enrollments.index', $sequence) }}" class="btn btn-danger light">Clear</a></div>
        </form>
    </div>

    @if ($enrollments->isEmpty())
        <div class="card-body"><div class="empty-state"><i class="bi bi-person-plus empty-state-icon"></i>No leads {{ array_filter($filters) ? 'match' : 'enrolled yet' }}. Use the panel on the right to enrol a category, or tick leads on the Leads page.</div></div>
    @else
    <form method="POST" action="{{ route('outreach.enrollments.bulk', $sequence) }}">
        @csrf
        <div class="card-body border-bottom py-2 d-flex flex-wrap gap-2 align-items-center">
            <select name="action" class="form-select form-select-sm w-auto" required>
                <option value="">Bulk action…</option><option value="pause">Pause</option><option value="resume">Resume</option><option value="remove">Remove from sequence</option></select>
            <button class="btn btn-sm btn-primary" onclick="return confirm('Apply to the selected enrollments?')">Apply to selected</button>
            <div class="form-check ms-2"><input type="checkbox" class="form-check-input" id="all" name="all" value="1"><label class="form-check-label fs-13" for="all">…or to ALL {{ $enrollments->total() }}{{ array_filter($filters) ? ' (ignores filters)' : '' }} enrolments</label></div>
        </div>
        <div class="table-responsive"><table class="table mb-0">
            <thead class="table-light"><tr><th style="width:36px"><input type="checkbox" class="form-check-input" id="checkAll" aria-label="Select all"></th><th>Contact</th><th>Status</th><th>Step</th><th class="d-none d-md-table-cell">Next send</th><th class="d-none d-lg-table-cell">Account</th><th></th></tr></thead>
            <tbody>
            @foreach ($enrollments as $e)
                <tr>
                    <td><input type="checkbox" class="form-check-input row-check" name="ids[]" value="{{ $e->id }}" aria-label="Select"></td>
                    <td><a href="{{ route('outreach.activity.index', ['lead' => $e->lead_id]) }}">{{ $e->lead?->displayName() }}</a><div class="fs-13 text-muted">{{ $e->lead?->email }}</div></td>
                    <td>@include('sequencer._badge', ['status' => $e->status])@if ($e->stop_reason)<div class="fs-12 text-muted">{{ str_replace('_', ' ', $e->stop_reason) }}</div>@endif</td>
                    <td>{{ $e->current_step }}</td>
                    <td class="d-none d-md-table-cell fs-13">@localtime($e->next_action_at)</td>
                    <td class="d-none d-lg-table-cell fs-13">{{ $e->mailSetting?->name }}</td>
                    <td class="text-end text-nowrap">
                        @if (in_array($e->status->value, ['active', 'pending']))<button class="btn btn-sm btn-light" formaction="{{ route('outreach.enrollments.pause', $e) }}" formnovalidate title="Pause"><i class="bi bi-pause"></i></button>@endif
                        @if ($e->status->value === 'paused')<button class="btn btn-sm btn-light" formaction="{{ route('outreach.enrollments.resume', $e) }}" formnovalidate title="Resume"><i class="bi bi-play"></i></button>@endif
                        @if ($e->status->value === 'failed')<button class="btn btn-sm btn-light" formaction="{{ route('outreach.enrollments.retry', $e) }}" formnovalidate title="Retry"><i class="bi bi-arrow-repeat"></i></button>@endif
                        @if ($e->status->isOpen())<button class="btn btn-sm btn-danger light" formaction="{{ route('outreach.enrollments.remove', $e) }}" formnovalidate title="Remove" onclick="return confirm('Remove this lead from the sequence?')"><i class="bi bi-x-lg"></i></button>@endif
                    </td>
                </tr>
            @endforeach
            </tbody></table></div>
    </form>
    <div class="card-footer">{{ $enrollments->links() }}</div>
    @endif
</div>
</div>

<div class="col-xl-3">
    <div class="card"><div class="card-header"><h5 class="card-title mb-0">Enrol leads</h5></div>
        <form method="POST" action="{{ route('outreach.enrollments.store', $sequence) }}" class="card-body">@csrf
            <div class="mb-3"><label class="form-label" for="category_id">Every lead in a category</label>
                <select class="form-select" id="category_id" name="category_id" required><option value="">— choose category —</option>
                    @foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }} ({{ $c->leads_count }})</option>@endforeach</select></div>
            <div class="mb-3"><label class="form-label" for="mail_setting_id">Send from</label>
                <select class="form-select" id="mail_setting_id" name="mail_setting_id"><option value="">Sequence default</option>
                    @foreach ($accounts as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach</select></div>
            <button class="btn btn-primary w-100">Enrol category</button>
            <div class="form-text mt-2">To enrol individual leads, tick them on the <a href="{{ route('leads.index') }}">Leads</a> page and choose "Enroll in sequence". Enrolling runs in the background; unsubscribed, bounced and already-enrolled leads are skipped.</div>
        </form></div>
</div>
</div>
@endsection

@push('scripts')
<script>document.getElementById('checkAll')?.addEventListener('change', e => document.querySelectorAll('.row-check').forEach(c => c.checked = e.target.checked));</script>
@endpush
