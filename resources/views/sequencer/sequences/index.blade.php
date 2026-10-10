@extends('layouts.app')

@section('title', 'Sequences')
@section('page-title', 'Sequences')

@section('breadcrumb')
    <li class="breadcrumb-item active">Sequences</li>
@endsection

@section('content')
@include('sequencer._errors')
<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between gap-2">
        <h4 class="card-title mb-0"><i class="bi bi-diagram-3 me-2 text-primary"></i>Sequences</h4>
        <a href="{{ route('outreach.sequences.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New sequence</a>
    </div>
    <div class="card-header d-block pb-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-md-5"><label class="form-label" for="q">Search</label><input class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}"></div>
            <div class="col-6 col-md-3"><label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status"><option value="">All</option>
                    @foreach ($statuses as $s)<option value="{{ $s->value }}" @selected(($filters['status'] ?? '') === $s->value)>{{ $s->label() }}</option>@endforeach</select></div>
            <div class="col-6 col-md-4 d-flex gap-2"><button class="btn btn-primary">Filter</button><a href="{{ route('outreach.sequences.index') }}" class="btn btn-danger light">Clear</a></div>
        </form>
    </div>
    @if ($sequences->isEmpty())
        <div class="card-body"><div class="empty-state"><i class="bi bi-diagram-3 empty-state-icon"></i>No sequences found. <a href="{{ route('outreach.sequences.create') }}">Create your first sequence</a>.</div></div>
    @else
    <div class="table-responsive"><table class="table mb-0">
        <thead class="table-light"><tr><th>Name</th><th>Status</th><th>Steps</th><th>Enrolled</th><th class="d-none d-md-table-cell">Window</th><th class="d-none d-md-table-cell">Daily limit</th></tr></thead>
        <tbody>
        @foreach ($sequences as $s)
            <tr><td><a href="{{ route('outreach.sequences.show', $s) }}"><strong>{{ $s->name }}</strong></a></td>
                <td>@include('sequencer._badge', ['status' => $s->status])</td>
                <td>{{ $s->steps_count }}</td><td>{{ $s->enrollments_count }}</td>
                <td class="d-none d-md-table-cell fs-13">{{ $s->startTime() }}–{{ $s->endTime() }} {{ $s->effectiveTimezone() }}</td>
                <td class="d-none d-md-table-cell">{{ $s->daily_limit }}</td></tr>
        @endforeach
        </tbody></table></div>
    <div class="card-footer">{{ $sequences->links() }}</div>
    @endif
</div>
@endsection
