@extends('layouts.app')

@section('title', 'Activity')
@section('page-title', 'Activity')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('outreach.sequences.index') }}">Sequences</a></li>
    <li class="breadcrumb-item active">Activity</li>
@endsection

@section('content')
<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between gap-2"><h4 class="card-title mb-0"><i class="bi bi-activity me-2 text-primary"></i>Activity timeline @if ($lead)<small class="text-muted">· {{ $lead->displayName() }} ({{ $lead->email }})</small>@endif</h4>
        @if ($lead)<a href="{{ route('leads.index') }}" class="btn btn-light btn-sm"><i class="bi bi-people me-1"></i>Back to Leads</a>@endif</div>
    <div class="card-header d-block pb-2">
        <form method="GET" class="row g-2 align-items-end">@if ($lead)<input type="hidden" name="lead" value="{{ $lead->id }}">@endif
            <div class="col-6 col-md-3"><label class="form-label" for="type">Event</label>
                <select class="form-select" id="type" name="type"><option value="">All events</option>
                    @foreach ($types as $t)<option value="{{ $t->value }}" @selected(($filters['type'] ?? '') === $t->value)>{{ $t->label() }}</option>@endforeach</select></div>
            <div class="col-6 col-md-4"><label class="form-label" for="sequence">Sequence</label>
                <select class="form-select" id="sequence" name="sequence"><option value="">All</option>
                    @foreach ($sequences as $s)<option value="{{ $s->id }}" @selected((int) ($filters['sequence'] ?? 0) === $s->id)>{{ $s->name }}</option>@endforeach</select></div>
            <div class="col-12 col-md-3 d-flex gap-2"><button class="btn btn-primary">Filter</button><a href="{{ route('outreach.activity.index') }}" class="btn btn-danger light">Clear</a></div>
        </form>
    </div>
    <div class="card-body">
        @php $groups = $events->getCollection()->groupBy(fn ($e) => \App\Sequencer\Support\Tz::format($e->occurred_at, 'd M Y')); @endphp
        @forelse ($groups as $day => $items)
            <div class="fw-semibold text-muted fs-13 mt-3 mb-1">{{ $day }}</div>
            @foreach ($items as $event)
                <div class="d-flex gap-2 py-1">
                    <i class="bi {{ $event->type->icon() }} text-{{ $event->type->badge() }} mt-1"></i>
                    <div><span class="fw-medium">{{ $event->type->label() }}</span> <span class="text-muted fs-13">· @localtime($event->occurred_at, 'H:i')
                        @if ($event->lead) · <a href="{{ route('outreach.activity.index', ['lead' => $event->lead_id]) }}">{{ $event->lead->displayName() }}</a>@endif
                        @if ($event->sequence) · {{ $event->sequence->name }}@endif</span>
                        <div class="fs-13 text-muted">{{ $event->description }}</div></div>
                </div>
            @endforeach
        @empty
            <div class="empty-state"><i class="bi bi-activity empty-state-icon"></i>No activity yet.</div>
        @endforelse
    </div>
    <div class="card-footer">{{ $events->links() }}</div>
</div>
@endsection
