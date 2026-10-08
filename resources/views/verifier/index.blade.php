@extends('layouts.app')

@section('title', 'Verifier — AI Client Finder')
@section('page-title', 'Verifier')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Verifier</li>
@endsection

@section('content')

{{-- ===================== SUMMARY ===================== --}}
<div class="row">
    @php
        $summary = [
            ['label' => 'Checked',  'value' => $statusCounts['total'],   'icon' => 'bi-patch-check-fill',      'tint' => 'primary'],
            ['label' => 'Valid',    'value' => $statusCounts['valid'],   'icon' => 'bi-check-circle-fill',     'tint' => 'success'],
            ['label' => 'Risky',    'value' => $statusCounts['risky'],   'icon' => 'bi-exclamation-triangle-fill', 'tint' => 'warning'],
            ['label' => 'Invalid',  'value' => $statusCounts['invalid'], 'icon' => 'bi-x-circle-fill',         'tint' => 'danger'],
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

{{-- ===================== VERIFY CARD ===================== --}}
<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header py-3">
                <div class="clearfix">
                    <h4 class="card-title"><i class="bi bi-patch-check-fill me-2 text-primary"></i>Verify Email Addresses</h4>
                    <p class="mb-0 fs-13">
                        Checks syntax, the domain's DNS and mail exchangers, and whether the address is
                        disposable, role-based or on a free provider.
                    </p>
                </div>
            </div>

            <div class="card-body">
                @unless($smtpProbe)
                    {{-- The user needs to know what "valid" does and does not
                         mean while the mailbox itself is never probed. --}}
                    <div class="alert alert-info d-flex align-items-start gap-2" role="alert">
                        <i class="bi bi-info-circle-fill mt-1"></i>
                        <div class="fs-13">
                            <strong>Mailbox probing is off.</strong>
                            Results confirm the domain can receive mail, but not that the individual mailbox
                            exists. Set <code>VERIFY_SMTP_PROBE=true</code> to enable the SMTP check — it needs
                            outbound port 25, which many hosts block.
                        </div>
                    </div>
                @endunless

                <ul class="nav nav-pills mb-3" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" data-verify-mode="single" type="button" role="tab" aria-selected="true">
                            <i class="bi bi-envelope me-1"></i>Single address
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-verify-mode="many" type="button" role="tab" aria-selected="false">
                            <i class="bi bi-list-check me-1"></i>Multiple addresses
                        </button>
                    </li>
                </ul>

                {{-- Single --}}
                <form id="verifySingleForm" novalidate>
                    <div class="row g-3 align-items-start">
                        <div class="col-12 col-md-9">
                            <label class="form-label" for="verifyEmail">Email address <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="verifyEmail" name="email"
                                   placeholder="e.g. jamie@example.com" maxlength="254"
                                   value="{{ $filters['q'] }}" autocomplete="off" autofocus>
                            <div class="invalid-feedback" id="verifyEmailError"></div>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label d-none d-md-block">&nbsp;</label>
                            <button type="submit" class="btn btn-primary w-100" id="verifySubmit">
                                <span class="spinner-border spinner-border-sm d-none me-1" id="verifySpinner"></span>
                                <i class="bi bi-shield-check me-1" id="verifyIcon"></i>Verify
                            </button>
                        </div>
                    </div>
                </form>

                {{-- Multiple --}}
                <form id="verifyManyForm" class="d-none" novalidate>
                    <div class="mb-3">
                        <label class="form-label" for="verifyEmails">Email addresses <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="verifyEmails" name="emails" rows="5" maxlength="20000"
                                  placeholder="One per line, or separated by commas."></textarea>
                        <div class="invalid-feedback" id="verifyEmailsError"></div>
                        <div class="form-text">
                            Up to 10 addresses are checked immediately. Longer lists are queued as a bulk run
                            so you are not left waiting — you will be taken to its progress page.
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary" id="verifyManySubmit">
                        <span class="spinner-border spinner-border-sm d-none me-1" id="verifyManySpinner"></span>
                        <i class="bi bi-shield-check me-1" id="verifyManyIcon"></i>Verify list
                    </button>
                </form>

                {{-- Loading / error / result panel --}}
                <div id="verifyResult" class="mt-4 d-none"></div>
            </div>
        </div>
    </div>
</div>

{{-- ===================== HISTORY ===================== --}}
<div class="row">
    <div class="col-xl-12">
        <div class="card" data-ajax-root>

            <div class="card-header py-3 d-sm-flex d-block align-items-center justify-content-between">
                <div class="clearfix">
                    <h4 class="card-title">
                        <i class="bi bi-clock-history me-2 text-primary"></i>Verification History
                        <span class="badge badge-primary light ms-1">{{ number_format($history->total()) }}</span>
                    </h4>
                    <p class="mb-0 fs-13">Every check performed, including those from bulk runs.</p>
                </div>
                <div class="clearfix">
                    <a href="{{ route('verifier.export', request()->query()) }}" class="btn btn-light btn-sm m-1">
                        <i class="bi bi-download me-1"></i>Export CSV
                    </a>
                    @if($history->total() > 0)
                        <form method="POST" action="{{ route('verifier.clear') }}" class="d-inline"
                              onsubmit="return confirm('Clear {{ $filters['status'] ? $filters['status'].' ' : '' }}verification history? This cannot be undone.')">
                            @csrf
                            <input type="hidden" name="status" value="{{ $filters['status'] }}">
                            <button type="submit" class="btn btn-danger light btn-sm m-1">
                                <i class="bi bi-trash me-1"></i>Clear
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            {{-- Filter bar --}}
            <div class="card-header d-block pb-2">
                <div class="row filter-bar align-items-start">

                    <div class="col-12 col-md-6 col-xl-4 mb-3">
                        <label class="form-label" for="historySearch">Search</label>
                        <input type="text" id="historySearch" class="form-control" data-search-param="q"
                               value="{{ $filters['q'] }}" placeholder="Email or domain…" autocomplete="off">
                    </div>

                    <div class="col-6 col-md-3 col-xl-3 mb-3">
                        <label class="form-label" for="historyStatus">Result</label>
                        <select id="historyStatus" class="form-select select2" data-param="status" data-placeholder="All Results">
                            <option value="">All Results</option>
                            @foreach(\App\Models\EmailVerification::STATUSES as $value => $label)
                                <option value="{{ $value }}" {{ $filters['status'] === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-6 col-md-3 col-xl-3 mb-3">
                        <label class="form-label" for="historySource">Source</label>
                        <select id="historySource" class="form-select select2" data-param="source" data-placeholder="All Sources">
                            <option value="">All Sources</option>
                            @foreach(['single' => 'Single check', 'bulk' => 'Bulk run', 'finder' => 'Finder'] as $value => $label)
                                <option value="{{ $value }}" {{ $filters['source'] === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-6 col-md-3 col-xl-2 mb-3">
                        <label class="form-label" aria-hidden="true">&nbsp;</label>
                        <a href="{{ route('verifier.index') }}" class="btn btn-danger light" title="Clear all filters">
                            <i class="bi bi-x-circle me-1"></i>Clear
                        </a>
                    </div>
                </div>
            </div>

            <div class="ajax-region">
                @include('verifier._history')
            </div>

        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    const singleForm = document.getElementById('verifySingleForm');
    const manyForm   = document.getElementById('verifyManyForm');
    const panel      = document.getElementById('verifyResult');
    const csrf       = document.querySelector('meta[name="csrf-token"]').content;

    const verifyUrl     = @json(route('verifier.verify'));
    const verifyManyUrl = @json(route('verifier.verify-many'));

    // ── Mode switch ──────────────────────────────────────────────────────────
    document.querySelectorAll('[data-verify-mode]').forEach(function (tab) {
        tab.addEventListener('click', function () {
            const many = tab.dataset.verifyMode === 'many';

            document.querySelectorAll('[data-verify-mode]').forEach(function (t) {
                const on = t === tab;
                t.classList.toggle('active', on);
                t.setAttribute('aria-selected', on ? 'true' : 'false');
            });

            singleForm.classList.toggle('d-none', many);
            manyForm.classList.toggle('d-none', ! many);

            panel.classList.add('d-none');
            panel.innerHTML = '';
        });
    });

    // ── Helpers ──────────────────────────────────────────────────────────────
    function esc(value) {
        const div = document.createElement('div');
        div.textContent = value === null || value === undefined ? '' : String(value);
        return div.innerHTML;
    }

    function render(html) {
        panel.innerHTML = html;
        panel.classList.remove('d-none');
    }

    function renderState(iconName, title, detail) {
        render(
            '<div class="empty-state">' +
                '<i class="bi ' + iconName + ' empty-state-icon"></i>' +
                '<p class="mb-1 fw-semibold">' + esc(title) + '</p>' +
                '<p class="fs-13 mb-0">' + esc(detail) + '</p>' +
            '</div>',
        );
    }

    function renderLoading(message) {
        render('<div class="inline-loading">' +
                   '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Checking…</span></div>' +
                   '<span>' + esc(message) + '</span>' +
               '</div>');
    }

    function setLoading(btnId, spinnerId, iconId, on) {
        document.getElementById(btnId).disabled = on;
        document.getElementById(spinnerId).classList.toggle('d-none', ! on);
        document.getElementById(iconId).classList.toggle('d-none', on);
    }

    // A tri-state check: true = pass, false = fail, null = not checked.
    function checkMark(pass) {
        if (pass === true)  return '<i class="bi bi-check-circle-fill text-success check-mark"></i>';
        if (pass === false) return '<i class="bi bi-x-circle-fill text-danger check-mark"></i>';
        return '<i class="bi bi-dash-circle text-muted check-mark"></i>';
    }

    // ── Detailed single result ───────────────────────────────────────────────
    function renderSingle(result) {
        const checks = result.checks.map(function (c) {
            return '<li>' +
                       checkMark(c.pass) +
                       '<div>' +
                           '<div class="check-name">' + esc(c.label) + '</div>' +
                           '<div class="check-detail">' + esc(c.detail) + '</div>' +
                       '</div>' +
                   '</li>';
        }).join('');

        render('' +
            '<div class="result-panel">' +
                '<div class="row g-4">' +
                    '<div class="col-lg-5">' +
                        '<div class="result-email mb-2">' + esc(result.email) + '</div>' +
                        '<div class="mb-3">' +
                            '<span class="badge badge-' + esc(result.colour) + ' light fs-6">' +
                                esc(result.status.toUpperCase()) +
                            '</span>' +
                        '</div>' +
                        '<div class="mb-3">' +
                            '<div class="d-flex justify-content-between fs-13 text-muted mb-1">' +
                                '<span>Confidence</span><span>' + esc(result.score) + '%</span>' +
                            '</div>' +
                            '<div class="score-meter">' +
                                '<span class="bg-' + esc(result.colour) + '" style="width:' + esc(result.score) + '%"></span>' +
                            '</div>' +
                        '</div>' +
                        '<p class="fs-13 mb-3">' + esc(result.reason) + '</p>' +
                        '<div class="fs-13 text-muted">Domain: <strong>' + esc(result.domain) + '</strong></div>' +
                    '</div>' +
                    '<div class="col-lg-7">' +
                        '<h6 class="mb-2">Checks</h6>' +
                        '<ul class="check-list">' + checks + '</ul>' +
                    '</div>' +
                '</div>' +
            '</div>');
    }

    // ── Compact list result ──────────────────────────────────────────────────
    function renderList(results) {
        const rows = results.map(function (r) {
            const colour = r.colour
                || (r.status === 'valid' ? 'success'
                 : r.status === 'invalid' ? 'danger'
                 : r.status === 'risky' ? 'warning' : 'secondary');

            return '' +
                '<div class="result-row">' +
                    '<div class="result-main">' +
                        '<div class="result-addr">' + esc(r.email) + '</div>' +
                        '<div class="fs-13 text-muted">' + esc(r.reason) + '</div>' +
                    '</div>' +
                    '<div class="d-flex align-items-center gap-3 flex-shrink-0">' +
                        '<div class="score-meter" style="width:4.5rem" title="' + esc(r.score) + '% confidence">' +
                            '<span class="bg-' + colour + '" style="width:' + esc(r.score) + '%"></span>' +
                        '</div>' +
                        '<span class="badge badge-' + colour + ' light">' + esc(r.status) + '</span>' +
                    '</div>' +
                '</div>';
        }).join('');

        render('<div class="result-panel">' +
                   '<h6 class="mb-3">' + results.length + ' address(es) checked</h6>' +
                   rows +
                   '<div class="mt-3 fs-13 text-muted">' +
                       'Reload the page to see these in the history table below.' +
                   '</div>' +
               '</div>');
    }

    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body),
        }).then(function (r) {
            return r.json().then(function (payload) { return { ok: r.ok, body: payload }; });
        });
    }

    function failureMessage(body) {
        return body.message
            || (body.errors && Object.values(body.errors)[0][0])
            || 'The check could not be completed.';
    }

    // ── Single submit ────────────────────────────────────────────────────────
    singleForm.addEventListener('submit', function (e) {
        e.preventDefault();

        const input = document.getElementById('verifyEmail');
        const email = input.value.trim();

        input.classList.remove('is-invalid');

        if (! email) {
            input.classList.add('is-invalid');
            document.getElementById('verifyEmailError').textContent = 'Enter an email address.';
            return;
        }

        setLoading('verifySubmit', 'verifySpinner', 'verifyIcon', true);
        renderLoading('Checking ' + email + '…');

        post(verifyUrl, { email: email })
            .then(function (res) {
                if (! res.ok || ! res.body.ok) {
                    renderState('bi-exclamation-triangle', 'Check failed', failureMessage(res.body));
                    return;
                }
                renderSingle(res.body.result);
            })
            .catch(function () {
                renderState('bi-wifi-off', 'Connection problem',
                    'The request did not reach the server. Check your connection and try again.');
            })
            .finally(function () {
                setLoading('verifySubmit', 'verifySpinner', 'verifyIcon', false);
            });
    });

    // ── List submit ──────────────────────────────────────────────────────────
    manyForm.addEventListener('submit', function (e) {
        e.preventDefault();

        const input = document.getElementById('verifyEmails');
        const blob  = input.value.trim();

        input.classList.remove('is-invalid');

        if (! blob) {
            input.classList.add('is-invalid');
            document.getElementById('verifyEmailsError').textContent = 'Paste at least one email address.';
            return;
        }

        setLoading('verifyManySubmit', 'verifyManySpinner', 'verifyManyIcon', true);
        renderLoading('Checking your list…');

        post(verifyManyUrl, { emails: blob })
            .then(function (res) {
                if (! res.ok || ! res.body.ok) {
                    renderState('bi-exclamation-triangle', 'Check failed', failureMessage(res.body));
                    return;
                }

                // Long lists become a queued bulk run - follow it to its page.
                if (res.body.queued) {
                    renderState('bi-hourglass-split', 'Queued as a bulk run', res.body.message);
                    window.location.href = res.body.redirect;
                    return;
                }

                renderList(res.body.results);
            })
            .catch(function () {
                renderState('bi-wifi-off', 'Connection problem',
                    'The request did not reach the server. Check your connection and try again.');
            })
            .finally(function () {
                setLoading('verifyManySubmit', 'verifyManySpinner', 'verifyManyIcon', false);
            });
    });
}());
</script>
@endpush
