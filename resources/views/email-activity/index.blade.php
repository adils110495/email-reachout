@extends('layouts.app')

@section('title', 'Email Activity')
@section('page-title', 'Email Activity')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Email Activity</li>
@endsection

@section('content')

<div class="row">
    <div class="col-xl-12">
        {{-- data-ajax-root wires up assets/js/ajax-filters.js: the server-side
             search, the Select2 activity filter and the AJAX swap of .ajax-content. --}}
        <div class="card" data-ajax-root>

            <div class="card-header py-3 d-sm-flex d-block align-items-center justify-content-between">
                <div class="clearfix">
                    <h4 class="card-title"><i class="bi bi-activity me-2 text-primary"></i>Email Activity</h4>
                    <p class="mb-0 fs-13">What happened to every email you sent: opened, replied, or not opened yet.</p>
                </div>
                <div class="clearfix">
                    {{-- Submitted over AJAX (script below); the plain POST is only a no-JS fallback. --}}
                    <form method="POST" action="{{ route('email-activity.check-replies') }}" class="d-inline" id="checkRepliesForm">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-sm m-1" id="checkRepliesBtn">
                            <i class="bi bi-arrow-repeat me-1"></i>Check Replies
                        </button>
                    </form>
                </div>
            </div>

            {{-- Result of the last "Check Replies" click. --}}
            <div id="checkRepliesResult" class="px-3 pt-3" hidden></div>

            {{-- Filter bar --}}
            <div class="card-header d-block pb-2">
                <div class="row filter-bar align-items-start">

                    {{-- Server-side search: matches across every page --}}
                    <div class="col-12 col-md-6 col-xl-4 mb-3">
                        <label class="form-label" for="activitySearch">Search</label>
                        <input type="text" id="activitySearch" class="form-control" data-search-param="q"
                               value="{{ $search }}" placeholder="Company, email or subject…" autocomplete="off">
                    </div>

                    {{-- Activity filter --}}
                    <div class="col-6 col-md-4 col-xl-3 mb-3">
                        <label class="form-label" for="activityFilter">Activity</label>
                        <select id="activityFilter" class="form-select select2" data-param="activity" data-placeholder="All Activity">
                            <option value="">All Activity ({{ $counts['total'] }})</option>
                            @foreach($activityOptions as $value => $label)
                                <option value="{{ $value }}" {{ $activity === $value ? 'selected' : '' }}>
                                    {{ $label }} ({{ $counts[$value] }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Sequence filter: sequence steps are logged alongside direct sends --}}
                    @if($sequences->isNotEmpty())
                    <div class="col-6 col-md-4 col-xl-3 mb-3">
                        <label class="form-label" for="sequenceFilter">Sequence</label>
                        <select id="sequenceFilter" class="form-select select2" data-param="sequence" data-placeholder="All Emails">
                            <option value="">All Emails</option>
                            @foreach($sequences as $seq)
                                <option value="{{ $seq->id }}" {{ $sequenceId === $seq->id ? 'selected' : '' }}>{{ $seq->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif

                    {{-- Clear --}}
                    <div class="col-6 col-md-2 col-xl-2 mb-3">
                        {{-- Spacer keeps the button on the same baseline as the labelled controls. --}}
                        <label class="form-label" aria-hidden="true">&nbsp;</label>
                        <a href="{{ route('email-activity.index') }}" class="btn btn-danger light" title="Clear all filters">
                            <i class="bi bi-x-circle me-1"></i>Clear
                        </a>
                    </div>
                </div>
            </div>

            {{-- Persistent wrapper: survives the AJAX swap so the loader can sit
                 over the table area while .ajax-content is being replaced. --}}
            <div class="ajax-region">
                @include('email-activity._table')
            </div>

        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
// "Check Replies" without a page reload: show a spinner while the inbox is scanned,
// then show the result, update the filter counts and reload just the table.
(function () {
    const form   = document.getElementById('checkRepliesForm');
    const btn    = document.getElementById('checkRepliesBtn');
    const result = document.getElementById('checkRepliesResult');
    const root   = form.closest('[data-ajax-root]');
    const idle   = btn.innerHTML;

    function showResult(type, icon, message) {
        result.innerHTML = '';
        const alert = document.createElement('div');
        alert.className = 'alert alert-' + type + ' alert-dismissible fade show mb-0';
        alert.setAttribute('role', 'alert');
        alert.innerHTML = '<i class="bi ' + icon + ' me-2"></i><span></span>'
            + '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        alert.querySelector('span').textContent = message;   // server text, never parsed as HTML
        alert.addEventListener('closed.bs.alert', function () { result.hidden = true; });
        result.appendChild(alert);
        result.hidden = false;
    }

    function updateCounts(counts) {
        const select = document.getElementById('activityFilter');
        if (! select || ! counts) return;
        Array.from(select.options).forEach(function (option) {
            const key = option.value === '' ? 'total' : option.value;
            if (counts[key] !== undefined) {
                option.textContent = option.textContent.replace(/\(\d+\)\s*$/, '(' + counts[key] + ')');
            }
        });
        if (window.jQuery && jQuery.fn.select2) jQuery(select).trigger('change.select2');   // refresh the visible label only
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        if (btn.disabled) return;

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Checking…';
        showResult('info', 'bi-hourglass-split', 'Checking the inbox for new replies and bounces…');

        const sequence = new URLSearchParams(window.location.search).get('sequence');
        const body = new FormData(form);
        if (sequence) body.append('sequence', sequence);

        fetch(form.action, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: body,
        })
            .then(function (response) {
                return response.json().catch(function () { return { ok: false, message: 'Unexpected response (HTTP ' + response.status + ').' }; });
            })
            .then(function (data) {
                if (! data.ok) {
                    showResult('danger', 'bi-exclamation-triangle-fill', data.message || 'Could not check replies.');
                    return;
                }
                showResult('success', 'bi-check-circle-fill', data.message);
                updateCounts(data.counts);
                if (data.replies > 0 && root) root.dispatchEvent(new CustomEvent('ajax-filters:reload'));
            })
            .catch(function () {
                showResult('danger', 'bi-exclamation-triangle-fill', 'Network error - please try again.');
            })
            .finally(function () {
                btn.disabled = false;
                btn.innerHTML = idle;
            });
    });
})();
</script>
@endpush
