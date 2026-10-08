@extends('layouts.app')

@section('title', 'Dashboard — AI Client Finder')
@section('page-title', 'Dashboard')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
@endsection

@section('content')

{{-- ===================== HEADLINE STATS ===================== --}}
<div class="row">
    @php
        // One shape for every card so the row stays even at any value length.
        $cards = [
            [
                'label' => 'Total Leads',
                'value' => $leadStats['total'],
                'meta'  => $leadStats['new'].' new · '.$leadStats['sent'].' contacted',
                'icon'  => 'bi-people-fill',
                'tint'  => 'primary',
            ],
            [
                'label' => 'Contactable',
                'value' => $leadStats['with_email'],
                'meta'  => $leadStats['coverage'].'% of leads have an email',
                'icon'  => 'bi-envelope-at-fill',
                'tint'  => 'info',
            ],
            [
                'label' => 'Emails Sent',
                'value' => $emailStats['sent'],
                'meta'  => $emailStats['today'].' today · '.$emailStats['this_week'].' this week',
                'icon'  => 'bi-send-fill',
                'tint'  => 'success',
            ],
            [
                'label' => 'Verified Valid',
                'value' => $verifyStats['valid'],
                'meta'  => $verifyStats['total'].' address'.($verifyStats['total'] === 1 ? '' : 'es').' checked',
                'icon'  => 'bi-patch-check-fill',
                'tint'  => 'warning',
            ],
        ];
    @endphp

    @foreach($cards as $card)
        <div class="col-xl-3 col-sm-6 mb-4">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="stat-icon tint-{{ $card['tint'] }}">
                        <i class="bi {{ $card['icon'] }}"></i>
                    </div>
                    <div class="stat-body">
                        <div class="stat-value">{{ number_format($card['value']) }}</div>
                        <div class="stat-label">{{ $card['label'] }}</div>
                        <div class="stat-meta">{{ $card['meta'] }}</div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="row">

    {{-- ===================== ACTIVITY ===================== --}}
    <div class="col-xl-8 mb-4">
        <div class="card h-100">
            <div class="card-header py-3 d-sm-flex d-block align-items-center justify-content-between">
                <div class="clearfix">
                    <h4 class="card-title"><i class="bi bi-graph-up-arrow me-2 text-primary"></i>Last 14 Days</h4>
                    <p class="mb-0 fs-13">Leads discovered against emails sent.</p>
                </div>
                <div class="chart-legend mt-2 mt-sm-0">
                    <span><span class="swatch bg-primary"></span>Leads found</span>
                    <span><span class="swatch bg-success"></span>Emails sent</span>
                </div>
            </div>
            <div class="card-body">
                @if(array_sum($activity['leads']) === 0 && array_sum($activity['emails']) === 0)
                    <div class="empty-state">
                        <i class="bi bi-bar-chart-line empty-state-icon"></i>
                        <p class="mb-1 fw-semibold">No activity in the last 14 days</p>
                        <p class="fs-13 mb-0">
                            Start with the <a href="{{ route('finder.index') }}">Finder</a> to add some leads.
                        </p>
                    </div>
                @else
                    {{-- Plain CSS columns: heights are a percentage of the shared
                         max, so both series stay comparable. --}}
                    <div class="activity-chart">
                        @foreach($activity['labels'] as $i => $label)
                            @php
                                $leadCount  = $activity['leads'][$i];
                                $emailCount = $activity['emails'][$i];
                            @endphp
                            <div class="activity-day">
                                <div class="activity-bars">
                                    <span class="activity-bar bar-leads"
                                          style="height: {{ max(2, round(($leadCount / $activity['max']) * 100)) }}%"
                                          title="{{ $label }}: {{ $leadCount }} lead(s) found"></span>
                                    <span class="activity-bar bar-emails"
                                          style="height: {{ max(2, round(($emailCount / $activity['max']) * 100)) }}%"
                                          title="{{ $label }}: {{ $emailCount }} email(s) sent"></span>
                                </div>
                                <span class="activity-label">{{ $label }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ===================== PIPELINE ===================== --}}
    <div class="col-xl-4 mb-4">
        <div class="card h-100">
            <div class="card-header py-3">
                <div class="clearfix">
                    <h4 class="card-title"><i class="bi bi-funnel-fill me-2 text-primary"></i>Lead Pipeline</h4>
                    <p class="mb-0 fs-13">Where every lead currently sits.</p>
                </div>
            </div>
            <div class="card-body">
                @php
                    $pipeline = [
                        ['label' => 'New',     'key' => 'new',     'colour' => 'primary'],
                        ['label' => 'Sent',    'key' => 'sent',    'colour' => 'success'],
                        ['label' => 'Replied', 'key' => 'replied', 'colour' => 'info'],
                        ['label' => 'Failed',  'key' => 'failed',  'colour' => 'danger'],
                    ];
                    $pipelineTotal = max(1, $leadStats['total']);
                @endphp

                @if($leadStats['total'] === 0)
                    <div class="empty-state">
                        <i class="bi bi-funnel empty-state-icon"></i>
                        <p class="mb-1 fw-semibold">No leads yet</p>
                        <p class="fs-13 mb-0">Run a search from the <a href="{{ route('finder.index') }}">Finder</a>.</p>
                    </div>
                @else
                    @foreach($pipeline as $stage)
                        @php $count = $leadStats[$stage['key']]; @endphp
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-baseline mb-1">
                                <span class="fw-medium">{{ $stage['label'] }}</span>
                                <span class="fs-13 text-muted">
                                    {{ number_format($count) }}
                                    <span class="ms-1">({{ round(($count / $pipelineTotal) * 100) }}%)</span>
                                </span>
                            </div>
                            <div class="score-meter">
                                <span class="bg-{{ $stage['colour'] }}" style="width: {{ round(($count / $pipelineTotal) * 100) }}%"></span>
                            </div>
                        </div>
                    @endforeach

                    <div class="d-flex justify-content-between align-items-center pt-2 border-top mt-3">
                        <span class="fs-13 text-muted">Reply rate</span>
                        <span class="fw-semibold">{{ $emailStats['reply_rate'] }}%</span>
                    </div>
                @endif
            </div>
            <div class="card-footer py-3">
                <a href="{{ route('leads.index') }}" class="btn btn-light btn-sm w-100">
                    <i class="bi bi-people me-1"></i>Open Leads
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">

    {{-- ===================== DELIVERABILITY ===================== --}}
    <div class="col-xl-4 mb-4">
        <div class="card h-100">
            <div class="card-header py-3">
                <div class="clearfix">
                    <h4 class="card-title"><i class="bi bi-patch-check-fill me-2 text-primary"></i>Deliverability</h4>
                    <p class="mb-0 fs-13">Results across every verification run.</p>
                </div>
            </div>
            <div class="card-body">
                @if($verifyStats['total'] === 0)
                    <div class="empty-state">
                        <i class="bi bi-patch-question empty-state-icon"></i>
                        <p class="mb-1 fw-semibold">Nothing verified yet</p>
                        <p class="fs-13 mb-0">Check an address in the <a href="{{ route('verifier.index') }}">Verifier</a>.</p>
                    </div>
                @else
                    @php
                        $verifyRows = [
                            ['label' => 'Valid',   'key' => 'valid',   'colour' => 'success'],
                            ['label' => 'Risky',   'key' => 'risky',   'colour' => 'warning'],
                            ['label' => 'Invalid', 'key' => 'invalid', 'colour' => 'danger'],
                            ['label' => 'Unknown', 'key' => 'unknown', 'colour' => 'secondary'],
                        ];
                    @endphp

                    @foreach($verifyRows as $row)
                        @php $count = $verifyStats[$row['key']]; @endphp
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-baseline mb-1">
                                <span class="fw-medium">
                                    <span class="badge badge-{{ $row['colour'] }} light">{{ $row['label'] }}</span>
                                </span>
                                <span class="fs-13 text-muted">
                                    {{ number_format($count) }}
                                    ({{ round(($count / max(1, $verifyStats['total'])) * 100) }}%)
                                </span>
                            </div>
                            <div class="score-meter">
                                <span class="bg-{{ $row['colour'] }}" style="width: {{ round(($count / max(1, $verifyStats['total'])) * 100) }}%"></span>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
            <div class="card-footer py-3">
                <a href="{{ route('verifier.index') }}" class="btn btn-light btn-sm w-100">
                    <i class="bi bi-patch-check me-1"></i>Open Verifier
                </a>
            </div>
        </div>
    </div>

    {{-- ===================== CATEGORY COVERAGE ===================== --}}
    <div class="col-xl-8 mb-4">
        <div class="card h-100">
            <div class="card-header py-3">
                <div class="clearfix">
                    <h4 class="card-title"><i class="bi bi-tags-fill me-2 text-primary"></i>Top Categories</h4>
                    <p class="mb-0 fs-13">Busiest categories and how many of their leads are contactable.</p>
                </div>
            </div>
            @if(empty($topCategories))
                <div class="card-body">
                    <div class="empty-state">
                        <i class="bi bi-tag empty-state-icon"></i>
                        <p class="mb-1 fw-semibold">No categorised leads yet</p>
                        <p class="fs-13 mb-0">Leads are filed under a category when they are found.</p>
                    </div>
                </div>
            @else
                <div class="card-body table-card-body px-0 pt-0 pb-2">
                    <div class="table-responsive">
                        <table class="table">
                            <thead class="table-light">
                                <tr>
                                    <th>Category</th>
                                    <th style="width:100px" class="text-end">Leads</th>
                                    <th style="width:110px" class="text-end d-none d-md-table-cell">With email</th>
                                    <th style="width:180px" class="d-none d-sm-table-cell">Coverage</th>
                                    <th style="width:60px"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($topCategories as $category)
                                    <tr>
                                        <td>
                                            <h6 class="mb-0 cell-wrap">{{ $category['name'] }}</h6>
                                            {{-- Carries the columns hidden at this width. --}}
                                            <div class="d-md-none fs-13 text-muted">
                                                {{ number_format($category['with_email']) }} with email
                                                ({{ $category['percent'] }}%)
                                            </div>
                                        </td>
                                        <td class="text-end">{{ number_format($category['total']) }}</td>
                                        <td class="text-end d-none d-md-table-cell">{{ number_format($category['with_email']) }}</td>
                                        <td class="d-none d-sm-table-cell">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="score-meter flex-grow-1">
                                                    <span class="bg-{{ $category['percent'] >= 60 ? 'success' : ($category['percent'] >= 30 ? 'warning' : 'danger') }}"
                                                          style="width: {{ $category['percent'] }}%"></span>
                                                </div>
                                                <span class="fs-13 text-muted">{{ $category['percent'] }}%</span>
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('finder.index', ['category' => $category['id']]) }}"
                                               class="btn btn-sm btn-light btn-square" title="View in Finder">
                                                <i class="bi bi-arrow-right"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<div class="row">

    {{-- ===================== RECENT LEADS ===================== --}}
    <div class="col-xl-7 mb-4">
        <div class="card h-100">
            <div class="card-header py-3 d-sm-flex d-block align-items-center justify-content-between">
                <div class="clearfix">
                    <h4 class="card-title"><i class="bi bi-clock-history me-2 text-primary"></i>Recent Leads</h4>
                </div>
                <a href="{{ route('leads.index') }}" class="btn btn-light btn-sm">View all</a>
            </div>

            @if($recentLeads->isEmpty())
                <div class="card-body">
                    <div class="empty-state">
                        <i class="bi bi-inbox empty-state-icon"></i>
                        No leads yet.
                    </div>
                </div>
            @else
                <div class="card-body table-card-body px-0 pt-0 pb-2">
                    <div class="table-responsive">
                        <table class="table">
                            <thead class="table-light">
                                <tr>
                                    <th class="mw-150">Company</th>
                                    <th class="mw-150">Email</th>
                                    <th style="width:110px">Status</th>
                                    <th style="width:110px" class="d-none d-md-table-cell">Found</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentLeads as $lead)
                                    <tr>
                                        <td>
                                            <h6 class="mb-0 cell-wrap">{{ Str::limit($lead->company_name, 40) }}</h6>
                                            <span class="fs-13 text-muted">
                                                {{ $lead->category?->name ?? '—' }}
                                                {{-- Found column is hidden below md. --}}
                                                <span class="d-md-none">· {{ $lead->created_at?->diffForHumans(short: true) }}</span>
                                            </span>
                                        </td>
                                        <td class="cell-wrap">
                                            @if($lead->email)
                                                <a href="mailto:{{ $lead->email }}">{{ $lead->email }}</a>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @php
                                                $leadColour = match ($lead->status) {
                                                    'sent'    => 'success',
                                                    'replied' => 'info',
                                                    'failed'  => 'danger',
                                                    default   => 'primary',
                                                };
                                            @endphp
                                            <span class="badge badge-{{ $leadColour }} light">{{ ucfirst($lead->status) }}</span>
                                        </td>
                                        <td class="fs-13 text-muted d-none d-md-table-cell">{{ $lead->created_at?->diffForHumans(short: true) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- ===================== BULK RUNS ===================== --}}
    <div class="col-xl-5 mb-4">
        <div class="card h-100">
            <div class="card-header py-3 d-sm-flex d-block align-items-center justify-content-between">
                <div class="clearfix">
                    <h4 class="card-title"><i class="bi bi-stack me-2 text-primary"></i>Bulk Runs</h4>
                    <p class="mb-0 fs-13">
                        {{ number_format($bulkStats['records']) }} record(s) processed across {{ $bulkStats['total'] }} run(s).
                    </p>
                </div>
                <a href="{{ route('bulks.index') }}" class="btn btn-light btn-sm">View all</a>
            </div>

            @if($runningBulks->isEmpty())
                <div class="card-body">
                    <div class="empty-state">
                        <i class="bi bi-file-earmark-arrow-up empty-state-icon"></i>
                        <p class="mb-1 fw-semibold">No bulk runs yet</p>
                        <p class="fs-13 mb-0">Upload a CSV from <a href="{{ route('bulks.index') }}">Bulks</a>.</p>
                    </div>
                </div>
            @else
                <div class="card-body">
                    @foreach($runningBulks as $bulk)
                        <div class="mb-3 pb-3 {{ $loop->last ? '' : 'border-bottom' }}">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                <div style="min-width:0">
                                    <a href="{{ route('bulks.show', $bulk->id) }}" class="fw-semibold cell-wrap">
                                        {{ Str::limit($bulk->name, 34) }}
                                    </a>
                                    <div class="fs-13 text-muted">
                                        {{ $bulk->type === 'find' ? 'Email finder' : 'Verification' }}
                                        · {{ $bulk->created_at?->diffForHumans(short: true) }}
                                    </div>
                                </div>
                                <span class="badge badge-{{ $bulk->status_colour }} light text-nowrap">
                                    {{ ucfirst($bulk->status) }}
                                </span>
                            </div>
                            <div class="progress bulk-progress">
                                <div class="progress-bar bg-{{ $bulk->status_colour }}"
                                     role="progressbar"
                                     style="width: {{ $bulk->progress }}%"
                                     aria-valuenow="{{ $bulk->progress }}" aria-valuemin="0" aria-valuemax="100"
                                     aria-label="{{ $bulk->name }} progress"></div>
                            </div>
                            <div class="fs-13 text-muted mt-1">
                                {{ number_format($bulk->processed_records) }} / {{ number_format($bulk->total_records) }} processed
                                · {{ number_format($bulk->successful_records) }} successful
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>

{{-- ===================== QUICK ACTIONS ===================== --}}
<div class="row">
    <div class="col-xl-12 mb-4">
        <div class="card">
            <div class="card-header py-3">
                <h4 class="card-title"><i class="bi bi-lightning-charge-fill me-2 text-primary"></i>Quick Actions</h4>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    @php
                        $actions = [
                            ['route' => 'finder.index',    'icon' => 'bi-search',        'label' => 'Find emails',      'meta' => 'Search a company domain'],
                            ['route' => 'verifier.index',  'icon' => 'bi-patch-check',   'label' => 'Verify an address', 'meta' => 'Check deliverability'],
                            ['route' => 'bulks.index',     'icon' => 'bi-stack',         'label' => 'Upload a CSV',      'meta' => 'Bulk verify or find'],
                            ['route' => 'templates.index', 'icon' => 'bi-envelope-paper','label' => 'Email templates',   'meta' => $templateCount.' active'],
                        ];
                    @endphp

                    @foreach($actions as $action)
                        <div class="col-xl-3 col-sm-6">
                            <a href="{{ route($action['route']) }}" class="card stat-card mb-0 text-decoration-none">
                                <div class="card-body">
                                    <div class="stat-icon tint-primary">
                                        <i class="bi {{ $action['icon'] }}"></i>
                                    </div>
                                    <div class="stat-body">
                                        <div class="fw-semibold">{{ $action['label'] }}</div>
                                        <div class="stat-meta">{{ $action['meta'] }}</div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
