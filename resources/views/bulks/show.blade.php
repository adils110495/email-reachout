@extends('layouts.app')

@section('title', $bulk->name.' — Bulks')
@section('page-title', 'Bulk Run')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('bulks.index') }}">Bulks</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ Str::limit($bulk->name, 30) }}</li>
@endsection

@section('content')

{{-- ===================== PROGRESS ===================== --}}
<div class="row">
    <div class="col-xl-12">
        <div class="card" id="bulkCard" data-bulk-id="{{ $bulk->id }}" data-running="{{ $bulk->isRunning() ? '1' : '0' }}">

            <div class="card-header py-3 d-sm-flex d-block align-items-center justify-content-between">
                <div class="clearfix">
                    <h4 class="card-title">
                        @if($bulk->type === 'find')
                            <i class="bi bi-search me-2 text-primary"></i>
                        @else
                            <i class="bi bi-patch-check me-2 text-primary"></i>
                        @endif
                        {{ $bulk->name }}
                        <span class="badge badge-{{ $bulk->status_colour }} light ms-1" id="bulkStatusBadge">
                            {{ ucfirst($bulk->status) }}
                        </span>
                    </h4>
                    <p class="mb-0 fs-13">
                        {{ match(true) {
                            $bulk->type === 'find' => 'Finding an address for each domain',
                            $bulk->type === 'verify' => 'Verifying each address',
                            $bulk->isImport() => 'Importing each row as a lead',
                            default => $bulk->type_label,
                        } }}
                        @if($bulk->original_filename) · {{ $bulk->original_filename }} @endif
                        @if($bulk->category) · filed under <strong>{{ $bulk->category->name }}</strong> @endif
                    </p>
                </div>
                <div class="clearfix">
                    <a href="{{ route('bulks.export', $bulk->id) }}" class="btn btn-light btn-sm m-1">
                        <i class="bi bi-download me-1"></i>Download Results
                    </a>

                    @if($bulk->isRunning())
                        <form method="POST" action="{{ route('bulks.cancel', $bulk->id) }}" class="d-inline" id="bulkCancelForm"
                              onsubmit="return confirm('Cancel this run? Results collected so far are kept.')">
                            @csrf
                            <button type="submit" class="btn btn-warning light btn-sm m-1">
                                <i class="bi bi-stop-circle me-1"></i>Cancel
                            </button>
                        </form>
                    @elseif(! $bulk->isImport() && $bulk->status !== 'draft' && ($bulk->failed_records > 0 || in_array($bulk->status, ['failed', 'cancelled'], true)))
                        <form method="POST" action="{{ route('bulks.retry', $bulk->id) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-success light btn-sm m-1">
                                <i class="bi bi-arrow-clockwise me-1"></i>Retry Unfinished
                            </button>
                        </form>
                    @endif

                    <a href="{{ route('bulks.index') }}" class="btn btn-light btn-sm m-1">
                        <i class="bi bi-arrow-left me-1"></i>All Runs
                    </a>
                </div>
            </div>

            <div class="card-body">
                @if($bulk->error)
                    <div class="alert alert-danger d-flex align-items-start gap-2" role="alert">
                        <i class="bi bi-exclamation-triangle-fill mt-1"></i>
                        <div><strong>This run failed.</strong> <span class="fs-13">{{ $bulk->error }}</span></div>
                    </div>
                @endif

                {{-- Draft import: first rows + column mapping. Nothing is imported until confirmed. --}}
                @if($preview)
                    <div class="alert alert-info d-flex flex-wrap align-items-center gap-2" role="status">
                        <i class="bi bi-eye-fill"></i>
                        <span>Preview of the first {{ count($preview['rows']) }} of {{ number_format($preview['total']) }} rows:</span>
                        <span class="badge badge-success light">{{ $preview['stats']['valid'] }} valid</span>
                        <span class="badge badge-warning light">{{ $preview['stats']['duplicate'] }} duplicate</span>
                        <span class="badge badge-danger light">{{ $preview['stats']['invalid'] }} invalid</span>
                    </div>
                    @error('mapping')<div class="alert alert-danger">{{ $message }}</div>@enderror

                    <form method="POST" action="{{ route('bulks.confirm', $bulk->id) }}" class="mb-4">
                        @csrf
                        <div class="table-responsive mb-3">
                            <table class="table table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        @foreach($preview['headers'] as $i => $header)
                                            <th style="min-width:170px">
                                                <div class="fs-13 text-muted mb-1">{{ $header }}</div>
                                                <select class="form-select form-select-sm" name="mapping[{{ $i }}]" aria-label="Map column {{ $header }}">
                                                    <option value="">— ignore —</option>
                                                    @foreach($importFields as $field)
                                                        <option value="{{ $field }}" @selected(($bulk->options['mapping'][$i] ?? null) === $field)>{{ $field === 'custom' ? 'custom field' : str_replace('_', ' ', $field) }}</option>
                                                    @endforeach
                                                </select>
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($preview['rows'] as $row)
                                        <tr class="{{ $row['problem'] === 'invalid' ? 'table-danger' : ($row['problem'] === 'duplicate' ? 'table-warning' : '') }}">
                                            <td>{{ $row['number'] }}</td>
                                            @foreach($preview['headers'] as $i => $header)<td>{{ $row['cells'][$i] ?? '' }}</td>@endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <input type="hidden" name="update_existing" value="0">
                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="importUpdateExisting" name="update_existing" value="1" @checked($bulk->options['update_existing'] ?? false)>
                            <label class="form-check-label" for="importUpdateExisting">Update leads that already exist (blank cells never erase data)</label>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Import {{ number_format($preview['total']) }} rows</button>
                    </form>
                @endif

                {{-- Progress bar --}}
                <div class="d-flex justify-content-between align-items-baseline mb-2">
                    <span class="fw-medium">Progress</span>
                    <span class="fs-13 text-muted">
                        <span id="bulkProcessed">{{ number_format($bulk->processed_records) }}</span>
                        / <span id="bulkTotal">{{ number_format($bulk->total_records) }}</span> records
                        (<span id="bulkPercent">{{ $bulk->progress }}</span>%)
                    </span>
                </div>
                <div class="progress bulk-progress mb-3">
                    <div class="progress-bar bg-{{ $bulk->status_colour }} {{ $bulk->isRunning() ? 'progress-bar-striped progress-bar-animated' : '' }}"
                         role="progressbar" id="bulkProgressBar"
                         style="width: {{ $bulk->progress }}%"
                         aria-valuenow="{{ $bulk->progress }}" aria-valuemin="0" aria-valuemax="100"
                         aria-label="Run progress"></div>
                </div>

                {{-- Counters --}}
                <div class="row g-3 mb-3">
                    @php
                        $counters = [
                            ['id' => 'countTotal',      'label' => 'Total records',   'value' => $bulk->total_records,      'tint' => 'primary', 'icon' => 'bi-list-ol'],
                            ['id' => 'countProcessed',  'label' => 'Processed',       'value' => $bulk->processed_records,  'tint' => 'info',    'icon' => 'bi-arrow-repeat'],
                            ['id' => 'countSuccessful', 'label' => 'Successful',      'value' => $bulk->successful_records, 'tint' => 'success', 'icon' => 'bi-check-circle-fill'],
                            ['id' => 'countFailed',     'label' => 'Failed',          'value' => $bulk->failed_records,     'tint' => 'danger',  'icon' => 'bi-x-circle-fill'],
                        ];
                    @endphp

                    @foreach($counters as $counter)
                        <div class="col-xl-3 col-sm-6">
                            <div class="card stat-card mb-0">
                                <div class="card-body">
                                    <div class="stat-icon tint-{{ $counter['tint'] }}"><i class="bi {{ $counter['icon'] }}"></i></div>
                                    <div class="stat-body">
                                        <div class="stat-value" id="{{ $counter['id'] }}">{{ number_format($counter['value']) }}</div>
                                        <div class="stat-label">{{ $counter['label'] }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Per-result breakdown. Clicking a chip filters the table below. --}}
                <div class="chip-row" id="bulkBreakdown">
                    @foreach($breakdown as $key => $count)
                        @php
                            $chipColour = match ($key) {
                                'valid', 'found'  => 'success',
                                'risky'           => 'warning',
                                'invalid'         => 'danger',
                                'not_found'       => 'secondary',
                                'pending'         => 'info',
                                default           => 'dark',
                            };
                        @endphp
                        <a href="{{ $key === 'pending' ? route('bulks.show', $bulk->id) : route('bulks.show', ['id' => $bulk->id, 'result' => $key]) }}"
                           class="stat-chip text-decoration-none"
                           data-breakdown="{{ $key }}">
                            <span class="badge badge-{{ $chipColour }} light">{{ str_replace('_', ' ', ucfirst($key)) }}</span>
                            <strong>{{ number_format($count) }}</strong>
                        </a>
                    @endforeach
                </div>

                @if($bulk->isRunning())
                    <p class="fs-13 text-muted mt-3 mb-0" id="bulkLiveNote">
                        <span class="spinner-border spinner-border-sm me-1"></span>
                        Running in the background — this page updates automatically.
                    </p>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ===================== RESULTS ===================== --}}
<div class="row">
    <div class="col-xl-12">
        <div class="card" data-ajax-root>

            <div class="card-header py-3 d-sm-flex d-block align-items-center justify-content-between">
                <div class="clearfix">
                    <h4 class="card-title">
                        <i class="bi bi-table me-2 text-primary"></i>Results
                        <span class="badge badge-primary light ms-1">{{ number_format($items->total()) }}</span>
                    </h4>
                </div>
            </div>

            {{-- Filter bar --}}
            <div class="card-header d-block pb-2">
                <div class="row filter-bar align-items-start">

                    <div class="col-12 col-md-6 col-xl-4 mb-3">
                        <label class="form-label" for="itemSearch">Search</label>
                        <input type="text" id="itemSearch" class="form-control" data-search-param="q"
                               value="{{ $filters['q'] }}"
                               placeholder="{{ $bulk->type === 'find' ? 'Domain or found address…' : 'Email address…' }}"
                               autocomplete="off">
                    </div>

                    <div class="col-6 col-md-3 col-xl-3 mb-3">
                        <label class="form-label" for="itemResult">Result</label>
                        <select id="itemResult" class="form-select select2" data-param="result" data-placeholder="All Results">
                            <option value="">All Results</option>
                            @php
                                $resultOptions = match (true) {
                                    $bulk->type === 'find' => ['found' => 'Found', 'not_found' => 'Not found', 'unknown' => 'Unknown'],
                                    $bulk->isImport() => ['imported' => 'Imported', 'updated' => 'Updated', 'duplicate' => 'Duplicate', 'invalid' => 'Invalid'],
                                    $bulk->isSequenceAction() => ['done' => 'Done', 'skipped' => 'Skipped'],
                                    default => ['valid' => 'Valid', 'risky' => 'Risky', 'invalid' => 'Invalid', 'unknown' => 'Unknown'],
                                };
                            @endphp
                            @foreach($resultOptions as $value => $label)
                                <option value="{{ $value }}" {{ $filters['result'] === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-6 col-md-3 col-xl-2 mb-3">
                        <label class="form-label" aria-hidden="true">&nbsp;</label>
                        <a href="{{ route('bulks.show', $bulk->id) }}" class="btn btn-danger light" title="Clear all filters">
                            <i class="bi bi-x-circle me-1"></i>Clear
                        </a>
                    </div>
                </div>
            </div>

            <div class="ajax-region">
                @include('bulks._items')
            </div>

        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    const card = document.getElementById('bulkCard');
    if (! card || card.dataset.running !== '1') return;

    // Poll only while the run is in flight, and stop as soon as it finishes -
    // a completed run's numbers never change again.
    const statusUrl = @json(route('bulks.status', ['id' => $bulk->id]));
    const POLL_MS   = 3000;

    const bar        = document.getElementById('bulkProgressBar');
    const badge      = document.getElementById('bulkStatusBadge');
    const percentEl  = document.getElementById('bulkPercent');
    const processed  = document.getElementById('bulkProcessed');
    const liveNote   = document.getElementById('bulkLiveNote');
    const breakdown  = document.getElementById('bulkBreakdown');

    const counters = {
        countTotal:      document.getElementById('countTotal'),
        countProcessed:  document.getElementById('countProcessed'),
        countSuccessful: document.getElementById('countSuccessful'),
        countFailed:     document.getElementById('countFailed'),
    };

    let timer   = null;
    let failures = 0;

    function fmt(n) {
        return Number(n || 0).toLocaleString();
    }

    function colourFor(status) {
        if (status === 'completed')  return 'success';
        if (status === 'processing') return 'primary';
        if (status === 'failed')     return 'danger';
        if (status === 'cancelled')  return 'dark';
        return 'warning';
    }

    function paint(data) {
        const colour = colourFor(data.status);

        bar.style.width = data.progress + '%';
        bar.setAttribute('aria-valuenow', data.progress);
        bar.className = 'progress-bar bg-' + colour +
            (data.running ? ' progress-bar-striped progress-bar-animated' : '');

        badge.className   = 'badge badge-' + colour + ' light ms-1';
        badge.textContent = data.status.charAt(0).toUpperCase() + data.status.slice(1);

        percentEl.textContent = data.progress;
        processed.textContent = fmt(data.processed);

        counters.countTotal.textContent      = fmt(data.total);
        counters.countProcessed.textContent  = fmt(data.processed);
        counters.countSuccessful.textContent = fmt(data.successful);
        counters.countFailed.textContent     = fmt(data.failed);

        // The breakdown chips are anchors built server-side; only their counts move.
        Object.keys(data.breakdown || {}).forEach(function (key) {
            const chip = breakdown.querySelector('[data-breakdown="' + key + '"] strong');
            if (chip) chip.textContent = fmt(data.breakdown[key]);
        });
    }

    function stop(message) {
        window.clearInterval(timer);
        card.dataset.running = '0';

        if (liveNote) {
            liveNote.innerHTML = message;
        }
    }

    function poll() {
        fetch(statusUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) {
                if (! r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function (data) {
                failures = 0;
                paint(data);

                if (! data.running) {
                    // The run is over: nothing left to cancel, and the rows below were
                    // rendered while it was still queued - refresh them in place.
                    const cancelForm = document.getElementById('bulkCancelForm');
                    if (cancelForm) cancelForm.remove();

                    const results = document.querySelector('[data-ajax-root]');
                    if (results) results.dispatchEvent(new CustomEvent('ajax-filters:reload'));

                    stop('<i class="bi bi-check-circle-fill text-success me-1"></i>' +
                         'Finished. The results below are up to date.');
                }
            })
            .catch(function () {
                // Tolerate a blip; give up after three consecutive failures so a
                // dead server does not leave the page hammering it.
                if (++failures >= 3) {
                    stop('<i class="bi bi-wifi-off text-warning me-1"></i>' +
                         'Lost contact with the server. <a href="">Reload the page</a> to check progress.');
                }
            });
    }

    timer = window.setInterval(poll, POLL_MS);

    // Stop polling while the tab is hidden, and catch up immediately on return.
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            window.clearInterval(timer);
        } else if (card.dataset.running === '1') {
            poll();
            timer = window.setInterval(poll, POLL_MS);
        }
    });
}());
</script>
@endpush
