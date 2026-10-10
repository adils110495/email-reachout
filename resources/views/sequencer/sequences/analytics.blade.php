@extends('layouts.app')

@section('title', $sequence->name.' analytics')
@section('page-title', 'Sequence analytics')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('outreach.sequences.index') }}">Sequences</a></li>
    <li class="breadcrumb-item"><a href="{{ route('outreach.sequences.show', $sequence) }}">{{ $sequence->name }}</a></li>
    <li class="breadcrumb-item active">Analytics</li>
@endsection

@section('content')
@php $e = $analytics['enrollments']; $m = $analytics['emails']; $r = $analytics['rates']; @endphp
<div class="row">
    @foreach (['Total enrolled' => $e['total'], 'Active' => $e['active'], 'Completed' => $e['completed'], 'Replied' => $e['replied'], 'Bounced' => $e['bounced'], 'Unsubscribed' => $e['unsubscribed'], 'Removed' => $e['removed']] as $label => $v)
        <div class="col-6 col-md-3 col-xl mb-3"><div class="card h-100 mb-0"><div class="card-body py-3"><div class="text-muted fs-13">{{ $label }}</div><div class="fs-3 fw-semibold">{{ $v }}</div></div></div></div>
    @endforeach
</div>

<div class="row">
    @foreach (['Open rate' => ['open', $m['opened']], 'Click rate' => ['click', $m['clicked']], 'Reply rate' => ['reply', $m['replied']], 'Bounce rate' => ['bounce', $m['bounced']]] as $label => [$key, $count])
        <div class="col-6 col-lg-3 mb-3"><div class="card h-100 mb-0"><div class="card-body">
            <div class="text-muted fs-13">{{ $label }}</div><div class="fs-2 fw-semibold">{{ $r[$key] }}%</div>
            <div class="fs-13 text-muted">{{ $count }} of {{ $m['attempts'] }} emails</div>
        </div></div></div>
    @endforeach
</div>

<div class="card">
    <div class="card-header"><h4 class="card-title mb-0">Per step</h4></div>
    <div class="table-responsive"><table class="table mb-0">
        <thead class="table-light"><tr><th>Step</th><th>Emails</th><th>Opened</th><th>Clicked</th><th>Replied</th><th>Bounced</th></tr></thead>
        <tbody>
        @forelse ($analytics['steps'] as $row)
            @php $n = $row['attempts']; $pct = fn ($v) => $n > 0 ? round($v / $n * 100, 1) : 0; @endphp
            <tr><td>{{ $row['step']->step_number }}. {{ \Illuminate\Support\Str::limit($row['step']->subject, 50) }}</td>
                <td>{{ $n }}</td><td>{{ $row['opened'] }} <small class="text-muted">({{ $pct($row['opened']) }}%)</small></td>
                <td>{{ $row['clicked'] }} <small class="text-muted">({{ $pct($row['clicked']) }}%)</small></td>
                <td>{{ $row['replied'] }} <small class="text-muted">({{ $pct($row['replied']) }}%)</small></td>
                <td>{{ $row['bounced'] }} <small class="text-muted">({{ $pct($row['bounced']) }}%)</small></td></tr>
        @empty<tr><td colspan="6" class="text-muted">No steps.</td></tr>@endforelse
        </tbody></table></div>
</div>
<p class="fs-13 text-muted">Rates use emails that left our servers as the denominator; a rate is 0% (never an error) while nothing has been sent. Open tracking is approximate: some mail clients block or pre-load images.</p>
@endsection
