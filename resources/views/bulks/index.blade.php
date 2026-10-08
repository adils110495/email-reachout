@extends('layouts.app')

@section('title', 'Bulks — AI Client Finder')
@section('page-title', 'Bulks')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Bulks</li>
@endsection

@section('content')

{{-- ===================== SUMMARY ===================== --}}
<div class="row">
    @php
        $summary = [
            ['label' => 'Total runs',        'value' => $totals['runs'],       'icon' => 'bi-stack',              'tint' => 'primary'],
            ['label' => 'In progress',       'value' => $totals['running'],    'icon' => 'bi-hourglass-split',    'tint' => 'warning'],
            ['label' => 'Records processed', 'value' => $totals['records'],    'icon' => 'bi-list-ol',            'tint' => 'info'],
            ['label' => 'Successful',        'value' => $totals['successful'], 'icon' => 'bi-check-circle-fill',  'tint' => 'success'],
        ];
    @endphp

    @foreach($summary as $card)
        <div class="col-xl-3 col-sm-6 mb-4">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="stat-icon tint-{{ $card['tint'] }}"><i class="bi {{ $card['icon'] }}"></i></div>
                    <div class="stat-body">
                        <div class="stat-value">{{ number_format($card['value']) }}</div>
                        <div class="stat-label">{{ $card['label'] }}</div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>

{{-- ===================== RUNS ===================== --}}
<div class="row">
    <div class="col-xl-12">
        <div class="card" data-ajax-root>

            <div class="card-header py-3 d-sm-flex d-block align-items-center justify-content-between">
                <div class="clearfix">
                    <h4 class="card-title">
                        <i class="bi bi-stack me-2 text-primary"></i>Bulk Runs
                        <span class="badge badge-primary light ms-1">{{ number_format($bulks->total()) }}</span>
                    </h4>
                    <p class="mb-0 fs-13">Upload a CSV to verify a list of addresses, or find addresses for a list of domains.</p>
                </div>
                <div class="clearfix">
                    <button class="btn btn-primary btn-sm m-1" data-bs-toggle="modal" data-bs-target="#uploadBulkModal">
                        <i class="bi bi-upload me-1"></i>New Bulk Upload
                    </button>
                </div>
            </div>

            {{-- Filter bar --}}
            <div class="card-header d-block pb-2">
                <div class="row filter-bar align-items-start">

                    <div class="col-12 col-md-6 col-xl-4 mb-3">
                        <label class="form-label" for="bulkSearch">Search</label>
                        {{-- Client-side: the run list is short, so hiding rows on
                             screen is enough and costs no request. --}}
                        <input type="text" id="bulkSearch" class="form-control" data-live-filter
                               placeholder="Search by name or file…" autocomplete="off">
                    </div>

                    <div class="col-6 col-md-3 col-xl-3 mb-3">
                        <label class="form-label" for="bulkType">Type</label>
                        <select id="bulkType" class="form-select select2" data-param="type" data-placeholder="All Types">
                            <option value="">All Types</option>
                            <option value="verify" {{ $filters['type'] === 'verify' ? 'selected' : '' }}>Verification</option>
                            <option value="find"   {{ $filters['type'] === 'find'   ? 'selected' : '' }}>Email finder</option>
                        </select>
                    </div>

                    <div class="col-6 col-md-3 col-xl-3 mb-3">
                        <label class="form-label" for="bulkStatus">Status</label>
                        <select id="bulkStatus" class="form-select select2" data-param="status" data-placeholder="All Statuses">
                            <option value="">All Statuses</option>
                            @foreach(['pending' => 'Pending', 'processing' => 'Processing', 'completed' => 'Completed', 'failed' => 'Failed', 'cancelled' => 'Cancelled'] as $value => $label)
                                <option value="{{ $value }}" {{ $filters['status'] === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-6 col-md-3 col-xl-2 mb-3">
                        <label class="form-label" aria-hidden="true">&nbsp;</label>
                        <a href="{{ route('bulks.index') }}" class="btn btn-danger light" title="Clear all filters">
                            <i class="bi bi-x-circle me-1"></i>Clear
                        </a>
                    </div>
                </div>
            </div>

            <div class="ajax-region">
                @include('bulks._table')
            </div>

        </div>
    </div>
</div>

{{-- ===================== UPLOAD MODAL ===================== --}}
<div class="modal fade" id="uploadBulkModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <form method="POST" action="{{ route('bulks.store') }}" enctype="multipart/form-data" id="bulkUploadForm">
                @csrf

                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-upload me-2 text-primary"></i>New Bulk Upload</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">

                        {{-- Type — decides which column the file must contain --}}
                        <div class="col-12">
                            <label class="form-label">What should this run do? <span class="text-danger">*</span></label>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <input type="radio" class="btn-check" name="type" id="typeVerify" value="verify"
                                           {{ old('type', 'verify') === 'verify' ? 'checked' : '' }} required>
                                    <label class="btn btn-light w-100 text-start p-3" for="typeVerify">
                                        <i class="bi bi-patch-check me-1 text-primary"></i>
                                        <strong>Verify addresses</strong>
                                        <div class="fs-13 text-muted mt-1">Your CSV contains email addresses.</div>
                                    </label>
                                </div>
                                <div class="col-md-6">
                                    <input type="radio" class="btn-check" name="type" id="typeFind" value="find"
                                           {{ old('type') === 'find' ? 'checked' : '' }}>
                                    <label class="btn btn-light w-100 text-start p-3" for="typeFind">
                                        <i class="bi bi-search me-1 text-primary"></i>
                                        <strong>Find addresses</strong>
                                        <div class="fs-13 text-muted mt-1">Your CSV contains company domains.</div>
                                    </label>
                                </div>
                            </div>
                            @error('type')<div class="text-danger fs-13 mt-1">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="bulkFile">CSV file <span class="text-danger">*</span></label>
                            <input type="file" name="file" id="bulkFile"
                                   class="form-control @error('file') is-invalid @enderror"
                                   accept=".csv,text/csv,text/plain" required>
                            @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">
                                Up to 10 MB. Duplicate and unusable rows are skipped automatically.
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="bulkName">Run name</label>
                            <input type="text" name="name" id="bulkName" class="form-control"
                                   value="{{ old('name') }}" maxlength="120" placeholder="Defaults to the file name">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label" for="bulkColumn">Data column</label>
                            <input type="number" name="column" id="bulkColumn" class="form-control"
                                   value="{{ old('column', 1) }}" min="1" max="50">
                            <div class="form-text">1 = first column.</div>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label" for="bulkNameColumn">Name column</label>
                            <input type="number" name="name_column" id="bulkNameColumn" class="form-control"
                                   value="{{ old('name_column') }}" min="1" max="50" placeholder="Optional">
                            <div class="form-text">Company name, if present.</div>
                        </div>

                        {{-- Only a "find" run creates leads, so only it needs a category. --}}
                        <div class="col-md-6 {{ old('type') === 'find' ? '' : 'd-none' }}" id="bulkCategoryField">
                            <label class="form-label" for="bulkCategory">File new leads under</label>
                            <select name="category_id" id="bulkCategory" class="form-select">
                                <option value="">— No category —</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" {{ (string) old('category_id') === (string) $category->id ? 'selected' : '' }}>
                                        {{ $category->name }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">The Leads module filters by category.</div>
                        </div>

                        <div class="col-12">
                            <div class="form-check">
                                <input type="checkbox" name="has_header" id="bulkHasHeader" value="1"
                                       class="form-check-input" {{ old('has_header', true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="bulkHasHeader">
                                    The first row is a header and should be skipped
                                </label>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="alert alert-info mb-0 fs-13" role="alert">
                                <i class="bi bi-info-circle-fill me-1"></i>
                                Processing runs in the background — this page will show live progress.
                                A queue worker must be running for the run to start.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="bulkUploadSubmit">
                        <span class="spinner-border spinner-border-sm d-none me-1" id="bulkUploadSpinner"></span>
                        <i class="bi bi-upload me-1"></i>Upload &amp; Start
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    const categoryField = document.getElementById('bulkCategoryField');
    const form          = document.getElementById('bulkUploadForm');
    const submitBtn     = document.getElementById('bulkUploadSubmit');
    const spinner       = document.getElementById('bulkUploadSpinner');

    // Only a "find" run creates leads, so the category picker follows the type.
    document.querySelectorAll('input[name="type"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            categoryField.classList.toggle('d-none', radio.value !== 'find');
        });
    });

    // Parsing a large CSV takes a moment; block a double submit.
    form.addEventListener('submit', function () {
        submitBtn.disabled = true;
        spinner.classList.remove('d-none');
    });

    // A validation error re-renders the page with the modal closed - reopen it
    // so the user sees the message next to the field it belongs to.
    @if($errors->any() && old('type'))
        new bootstrap.Modal(document.getElementById('uploadBulkModal')).show();
    @endif
}());

/* ── Live progress for in-flight runs ──────────────────────────────────────
   Only rows marked data-bulk-running are polled, and each stops as soon as its
   run finishes, so a page of completed runs makes no requests at all. */
(function () {
    'use strict';

    // route() with a placeholder id keeps the URL owned by routes/web.php.
    const statusUrlTemplate = @json(route('bulks.status', ['id' => '__ID__']));
    const POLL_MS = 5000;

    function fmt(n) { return Number(n || 0).toLocaleString(); }

    function colourFor(status) {
        if (status === 'completed')  return 'success';
        if (status === 'processing') return 'primary';
        if (status === 'failed')     return 'danger';
        if (status === 'cancelled')  return 'dark';
        return 'warning';
    }

    function paintRow(row, data) {
        const colour = colourFor(data.status);
        const bar    = row.querySelector('[data-cell="bar"]');
        const status = row.querySelector('[data-cell="status"]');
        const counts = row.querySelector('[data-cell="counts"]');

        if (bar) {
            bar.style.width = data.progress + '%';
            bar.setAttribute('aria-valuenow', data.progress);
            bar.className = 'progress-bar bg-' + colour +
                (data.running ? ' progress-bar-striped progress-bar-animated' : '');
        }

        if (status) {
            status.className   = 'badge badge-' + colour + ' light';
            status.textContent = data.status.charAt(0).toUpperCase() + data.status.slice(1);
        }

        if (counts) {
            counts.textContent = fmt(data.processed) + ' / ' + fmt(data.total) + ' (' + data.progress + '%)';
        }

        // Two targets each: the wide column, and the summary line that replaces
        // it below lg. Only one is visible at a time, but both must stay current
        // so a resize never reveals a stale number.
        row.querySelectorAll('[data-cell="successful"], [data-cell="successful-sm"]')
           .forEach(function (el) { el.textContent = fmt(data.successful); });

        row.querySelectorAll('[data-cell="failed"], [data-cell="failed-sm"]')
           .forEach(function (el) { el.textContent = fmt(data.failed); });
    }

    function watch(row) {
        const id = row.dataset.bulkRunning;
        let failures = 0;

        const timer = window.setInterval(function () {
            // The row is replaced whenever a filter changes; drop the timer
            // rather than paint a node that is no longer on the page.
            if (! row.isConnected) {
                window.clearInterval(timer);
                return;
            }

            fetch(statusUrlTemplate.replace('__ID__', id), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then(function (r) {
                    if (! r.ok) throw new Error('HTTP ' + r.status);
                    return r.json();
                })
                .then(function (data) {
                    failures = 0;
                    paintRow(row, data);

                    if (! data.running) window.clearInterval(timer);
                })
                .catch(function () {
                    // Tolerate a blip, but stop after three consecutive failures.
                    if (++failures >= 3) window.clearInterval(timer);
                });
        }, POLL_MS);
    }

    function start() {
        document.querySelectorAll('[data-bulk-running]').forEach(watch);
    }

    // Re-arm after an AJAX filter swap replaces the rows.
    const region = document.querySelector('.ajax-region');

    if (region) {
        new MutationObserver(function () { start(); })
            .observe(region, { childList: true, subtree: false });
    }

    start();
}());
</script>
@endpush
