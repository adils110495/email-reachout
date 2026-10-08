@extends('layouts.app')

@section('title', 'AI Client Finder - Leads')
@section('page-title', 'Leads')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Leads</li>
@endsection

@section('content')

{{-- ===================== SEARCH CARD ===================== --}}
<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header py-3">
                <h4 class="card-title"><i class="bi bi-search me-2 text-primary"></i>Find New Leads</h4>
            </div>
            <div class="card-body">
                <form action="{{ route('leads.search') }}" method="POST" id="searchForm">
                    @csrf
                    <div class="row g-3 align-items-start">
                        {{-- Category dropdown --}}
                        <div class="col-12 col-md-3">
                            <label class="form-label">Category</label>
                            <select name="search_category" id="search_category" class="form-select" required>
                                <option value="">-- Select Category --</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}" {{ (string)old('search_category') === (string)$cat->id ? 'selected' : '' }}>
                                        {{ $cat->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        {{-- Keyword input --}}
                        <div class="col-12 col-md-6">
                            <label class="form-label">Keyword</label>
                            <input
                                type="text" name="keyword" id="keyword"
                                class="form-control @error('keyword') is-invalid @enderror"
                                placeholder="e.g. web design agency London, plumbing company Manchester"
                                value="{{ old('keyword') }}" required minlength="2" maxlength="200" autofocus
                            >
                            @error('keyword')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Enter a keyword leads will appear instantly, emails extracted in the background.</div>
                        </div>
                        {{-- Find button --}}
                        <div class="col-12 col-md-3">
                            <label class="form-label d-none d-md-block">&nbsp;</label>
                            <button type="submit" class="btn btn-primary w-100" id="findBtn">
                                <span class="spinner-border spinner-border-sm d-none me-1" id="spinner"></span>
                                <i class="bi bi-lightning-charge-fill me-1" id="btnIcon"></i>Find Leads
                            </button>
                        </div>
                    </div>
                    <div class="text-danger fs-13 mt-2 d-none" id="searchError"></div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- ===================== LEADS TABLE ===================== --}}
<div class="row">
    <div class="col-xl-12">
        <div class="card">

            {{-- Table header --}}
            <div class="card-header py-3 d-sm-flex d-block align-items-center justify-content-between">
                <div class="clearfix">
                    <h4 class="card-title">
                        <i class="bi bi-people-fill me-2 text-primary"></i>Leads
                        <span id="leadsCount" class="badge badge-primary light ms-1">{{ $activeCategory ? $leads->total() : 0 }}</span>
                    </h4>
                </div>
                <div class="clearfix">
                    <a href="{{ route('leads.export') }}" class="btn btn-light btn-sm m-1">
                        <i class="bi bi-download me-1"></i>Export CSV
                    </a>
                    <button class="btn btn-primary btn-sm m-1" data-bs-toggle="modal" data-bs-target="#addLeadModal">
                        <i class="bi bi-plus-lg me-1"></i>Add Lead
                    </button>
                </div>
            </div>

            {{-- Filter bar --}}
            <div class="card-header d-block pb-2">
                @php
                    $activeStatus  = request('status');
                    $statusOptions = [
                        'new'     => 'New',
                        'sent'    => 'Sent',
                        'failed'  => 'Failed',
                        'replied' => 'Replied',
                    ];
                @endphp

                <div class="row filter-bar align-items-start">

                    {{-- Live filter --}}
                    <div class="col-12 col-md-6 col-xl-3 mb-3">
                        <label class="form-label" for="tableFilter">Search</label>
                        <input
                            type="text" id="tableFilter" class="form-control"
                            placeholder="Filter by company, email, website, status…" autocomplete="off"
                        >
                    </div>

                    {{-- Status filter --}}
                    <div class="col-6 col-md-3 col-xl-2 mb-3">
                        <label class="form-label" for="statusFilter">Status</label>
                        <select id="statusFilter" class="form-select select2" data-param="status" data-placeholder="All Statuses">
                            <option value="">All Statuses</option>
                            @foreach($statusOptions as $value => $label)
                                <option value="{{ $value }}" {{ $activeStatus === $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Platform filter --}}
                    <div class="col-6 col-md-3 col-xl-2 mb-3">
                        <label class="form-label" for="platformFilter">Platform</label>
                        <select id="platformFilter" class="form-select select2" data-param="platform" data-placeholder="All Platforms">
                            <option value="">All Platforms</option>
                            @foreach($platforms as $plt)
                                <option value="{{ $plt->id }}" {{ (int)$activePlatform === $plt->id ? 'selected' : '' }}>
                                    {{ $plt->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Category filter --}}
                    <div class="col-6 col-md-6 col-xl-3 mb-3">
                        <label class="form-label" for="categoryFilter">Category</label>
                        <select id="categoryFilter" class="form-select select2" data-param="category" data-placeholder="All Categories">
                            <option value="">All Categories</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" {{ (int)$activeCategory === $cat->id ? 'selected' : '' }}>
                                    {{ $cat->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Clear --}}
                    <div class="col-6 col-md-6 col-xl-2 mb-3">
                        {{-- Spacer keeps the button on the same baseline as the labelled controls. --}}
                        <label class="form-label" aria-hidden="true">&nbsp;</label>
                        <a href="{{ route('leads.index') }}" class="btn btn-danger light" title="Clear all filters">
                            <i class="bi bi-x-circle me-1"></i>Clear
                        </a>
                    </div>
                </div>

                {{-- Bulk action toolbar (hidden until rows are selected) --}}
                <div id="bulkToolbar" class="mb-3 d-none">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="fs-13 me-1">
                            <span id="selectedCount">0</span> selected
                        </span>

                        {{-- Bulk status update --}}
                        <form method="POST" action="{{ route('leads.bulk-status') }}" id="bulkStatusForm" class="d-flex gap-2">
                            @csrf
                            <div id="bulkStatusIds"></div>
                            <select name="status" class="form-select form-select-sm" style="width:130px">
                                <option value="new">New</option>
                                <option value="sent">Sent</option>
                                <option value="failed">Failed</option>
                                <option value="replied">Replied</option>
                            </select>
                            <button type="submit" class="btn btn-sm btn-primary light">
                                <i class="bi bi-tag me-1"></i>Set Status
                            </button>
                        </form>

                        {{-- Bulk delete --}}
                        <form method="POST" action="{{ route('leads.bulk-delete') }}" id="bulkDeleteForm">
                            @csrf
                            <input type="hidden" name="_redirect_back" value="{{ request()->getQueryString() ? '?'.request()->getQueryString() : '' }}">
                            <div id="bulkDeleteIds"></div>
                            <button
                                type="submit" class="btn btn-sm btn-danger light"
                                onclick="return confirm('Delete selected leads? This cannot be undone.')"
                            >
                                <i class="bi bi-trash me-1"></i>Delete Selected
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Persistent wrapper: survives the AJAX swap so the loader can sit
                 over the table area while #leadsContent is being replaced. --}}
            <div class="leads-region" id="leadsRegion">
                <div class="leads-loader" id="leadsLoader" hidden>
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading…</span>
                    </div>
                    <span class="fs-13">Loading leads…</span>
                </div>

                @include('leads._table')
            </div>

        </div>
    </div>
</div>

{{-- ===================== COMPOSE MODAL (Gmail-style) ===================== --}}
<div class="modal fade" id="composeModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            {{-- Header bar --}}
            <div class="modal-header bg-primary py-3 px-3">
                <span class="text-white fw-semibold" id="composeModalTitle">New Message</span>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="composeForm" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="_redirect_back" id="compose_redirect_back">

                {{-- Template selector --}}
                <div class="border-bottom px-3 py-2 d-flex align-items-center gap-2 compose-row">
                    <span class="fs-13 text-nowrap compose-label">Template</span>
                    <select id="compose_template" class="form-select form-select-sm border-0 bg-transparent shadow-none">
                        <option value="">— Select a template (optional) —</option>
                    </select>
                </div>

                {{-- Address selector --}}
                <div class="border-bottom px-3 py-2 d-flex align-items-center gap-2 compose-row">
                    <span class="fs-13 text-nowrap compose-label">Address</span>
                    <select name="address_id" id="compose_address" class="form-select form-select-sm border-0 bg-transparent shadow-none">
                        <option value="">— Select an address (optional) —</option>
                        @foreach($addresses as $addr)
                            <option value="{{ $addr->id }}">
                                {{ $addr->address }} | {{ $addr->phone }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- To --}}
                <div class="border-bottom px-3 py-2 d-flex align-items-center gap-2">
                    <span class="fs-13 compose-label">To</span>
                    {{-- One option per address of the lead, primary first. The chosen one is the only recipient. --}}
                    <select name="to_email" id="compose_to"
                        class="form-select form-select-sm border-0 shadow-none bg-transparent fw-semibold"></select>
                </div>

                {{-- Subject --}}
                <div class="border-bottom px-3 py-2 d-flex align-items-center gap-2">
                    <span class="fs-13 compose-label">Subject</span>
                    <input type="text" name="subject" id="compose_subject"
                        class="form-control form-control-sm border-0 shadow-none bg-transparent"
                        placeholder="Subject" required>
                </div>

                {{-- Body --}}
                <div class="px-1">
                    <div id="compose_body"
                        contenteditable="true"
                        class="form-control border-0 shadow-none"
                        style="min-height:240px;max-height:400px;overflow-y:auto;font-size:.92rem;line-height:1.6;outline:none;"
                        data-placeholder="Write your message here…"></div>
                    <textarea name="body" id="compose_body_hidden" class="d-none"></textarea>
                </div>

                {{-- Attachment previews --}}
                <div id="attachmentList" class="px-3 pb-1 d-flex flex-wrap gap-2" style="min-height:0;"></div>

                {{-- Footer toolbar --}}
                <div class="modal-footer justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-send-fill me-1"></i>Send
                        </button>
                        {{-- Attach button --}}
                        <label for="attachmentInput" class="btn btn-light mb-0" title="Attach files" style="cursor:pointer;">
                            <i class="bi bi-paperclip"></i>
                        </label>
                        <input type="file" id="attachmentInput" name="attachments[]" multiple class="d-none" accept="*/*">
                    </div>
                    <button type="button" class="btn btn-danger light" data-bs-dismiss="modal" id="discardBtn">
                        <i class="bi bi-trash me-1"></i>Discard
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>

{{-- ===================== SHOW EMAIL MODAL ===================== --}}
<div class="modal fade" id="showEmailModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title text-white">
                    <i class="bi bi-envelope-open me-2"></i>Sent Email
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" id="showEmailBody">
                <div class="text-center py-5"><div class="spinner-border text-primary"></div></div>
            </div>
        </div>
    </div>
</div>

{{-- ===================== VIEW MODAL ===================== --}}
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-eye me-2 text-primary"></i>View Lead</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
            </div>
        </div>
    </div>
</div>

{{-- ===================== EDIT MODAL ===================== --}}
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil me-2 text-warning"></i>Edit Lead</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editForm" method="POST">
                    @csrf @method('PUT')
                    <input type="hidden" name="_redirect_back" id="edit_redirect_back">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Platform</label>
                            <select name="platform_id" id="edit_platform_id" class="form-select">
                                <option value="">— Select Platform —</option>
                                @foreach($platforms as $plt)
                                    <option value="{{ $plt->id }}">{{ $plt->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select name="status" id="edit_status" class="form-select">
                                <option value="new">New</option>
                                <option value="sent">Sent</option>
                                <option value="failed">Failed</option>
                                <option value="replied">Replied</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Company Name <span class="text-danger">*</span></label>
                            <input type="text" name="company_name" id="edit_company_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Website <span class="text-danger">*</span></label>
                            <input type="url" name="website" id="edit_website" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">LinkedIn URL</label>
                            <input type="url" name="linkedin" id="edit_linkedin" class="form-control" placeholder="https://linkedin.com/company/...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Emails</label>
                            {{-- Sent empty so clearing every chip still clears the lead's emails. --}}
                            <input type="hidden" name="emails[]" value="">
                            <select name="emails[]" id="edit_emails" class="form-select js-email-tags" multiple
                                    data-placeholder="Type an email and press Enter"></select>
                            {{-- The first email is the main one. The pencil picks a different main
                                 by moving that address to the front of the list. --}}
                            <div class="d-flex align-items-center gap-2 mt-2 fs-13">
                                <span class="text-muted">Main email:</span>
                                <strong id="edit_main_label">—</strong>
                                <button type="button" class="btn btn-link btn-sm p-0 d-none" id="edit_main_pencil" title="Change main email">
                                    <i class="bi bi-pencil"></i>
                                </button>
                            </div>
                            <div class="mt-1 d-none" id="edit_main_picker">
                                <select id="edit_main_select" class="form-select form-select-sm" aria-label="Main email"></select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Category</label>
                            <select name="category_id" id="edit_category_id" class="form-select">
                                <option value="">— Select Category —</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- ===================== ADD LEAD MODAL ===================== --}}
<div class="modal fade" id="addLeadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2 text-primary"></i>Add Lead</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="{{ route('leads.store') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Platform</label>
                            <select name="platform_id" class="form-select">
                                <option value="">— Select Platform —</option>
                                @foreach($platforms as $plt)
                                    <option value="{{ $plt->id }}" {{ $plt->name === 'Google' ? 'selected' : '' }}>
                                        {{ $plt->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="new">New</option>
                                <option value="sent">Sent</option>
                                <option value="failed">Failed</option>
                                <option value="replied">Replied</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Company Name <span class="text-danger">*</span></label>
                            <input type="text" name="company_name" class="form-control" placeholder="e.g. Acme Ltd" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Website <span class="text-danger">*</span></label>
                            <input type="url" name="website" class="form-control" placeholder="https://example.com" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">LinkedIn URL</label>
                            <input type="url" name="linkedin" class="form-control" placeholder="https://linkedin.com/company/...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Emails</label>
                            <select name="emails[]" id="add_emails" class="form-select js-email-tags" multiple
                                    data-placeholder="contact@example.com — press Enter to add more">
                                @foreach((array) old('emails', []) as $oldEmail)
                                    @if($oldEmail)<option value="{{ $oldEmail }}" selected>{{ $oldEmail }}</option>@endif
                                @endforeach
                            </select>
                            <div class="form-text">Add as many as you like. The first one is used for sending.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Category</label>
                            <select name="category_id" class="form-select">
                                <option value="">— Select Category —</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add Lead</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
// ── Route map ─────────────────────────────────────────────────
// Every URL used below is generated from a *named route*, so paths
// stay in sync with routes/web.php instead of being hardcoded.
const leadRoutes = {
    index:              @json(route('leads.index')),
    apiTemplates:       @json(route('api.templates')),
    show:               @json(route('leads.show',           ['id' => '__ID__'])),
    edit:               @json(route('leads.edit',           ['id' => '__ID__'])),
    update:             @json(route('leads.update',         ['id' => '__ID__'])),
    sendEmail:          @json(route('leads.send-email',     ['id' => '__ID__'])),
    sentEmail:          @json(route('leads.sent-email',     ['id' => '__ID__'])),
    scrapeContact:      @json(route('leads.scrape-contact', ['id' => '__ID__'])),
    attachmentDownload: @json(route('leads.attachment.download')),
};

function leadRoute(template, id) {
    return template.replace('__ID__', encodeURIComponent(id));
}

// ── AJAX table loading ────────────────────────────────────────
// Every filter, per-page change and pagination click swaps #leadsContent
// instead of reloading the page. The server returns just leads/_table.blade.php
// for XHR requests, and the address bar is kept in sync via pushState so the
// current filters stay bookmarkable and the back button works.
const leadsWrap = () => document.getElementById('leadsContent');

let leadsRequest = null; // in-flight request, so a fast second change wins

function setLeadsLoading(on) {
    const region = document.getElementById('leadsRegion');
    const loader = document.getElementById('leadsLoader');

    if (region) region.classList.toggle('is-loading', on);
    if (loader) loader.hidden = ! on;
}

function loadLeads(url, { push = true, quiet = false } = {}) {
    if (leadsRequest) leadsRequest.abort();

    const controller = new AbortController();
    leadsRequest = controller;
    // Background polling refreshes silently - no spinner flashing every few seconds.
    if (! quiet) setLeadsLoading(true);

    fetch(url, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        signal: controller.signal,
    })
        .then(r => {
            if (! r.ok) throw new Error('Server error: ' + r.status);
            return r.text();
        })
        .then(html => {
            const wrap = leadsWrap();
            if (! wrap) return;

            wrap.outerHTML = html;

            if (push) window.history.pushState({ leadsUrl: url }, '', url);

            // Header count badge lives outside the swapped region.
            const badge = document.getElementById('leadsCount');
            const fresh = leadsWrap();
            if (badge && fresh) badge.textContent = fresh.dataset.total || '0';

            resetBulkSelection();
        })
        .catch(err => {
            // An aborted request was superseded - leave the loader up for the new one.
            if (err.name === 'AbortError') return;
            alert('Could not load leads. Please try again.');
        })
        .finally(() => {
            if (leadsRequest === controller) {
                leadsRequest = null;
                setLeadsLoading(false);
            }
        });
}

// Back / forward buttons replay the same AJAX load.
window.addEventListener('popstate', function () {
    loadLeads(window.location.href, { push: false });
});

// Pagination links (inside the swapped region) load over AJAX.
document.addEventListener('click', function (e) {
    const link = e.target.closest('#leadsContent .pagination a.page-link');
    if (! link || ! link.href) return;

    e.preventDefault();
    loadLeads(link.href);
});

// ── Per-page selector ─────────────────────────────────────────
document.addEventListener('change', function (e) {
    if (e.target.id !== 'perPageSelect') return;

    const url = new URL(window.location.href);
    url.searchParams.set('per_page', e.target.value);
    url.searchParams.delete('page'); // reset to page 1
    loadLeads(url.toString());
});

// ── Column sorting ───────────────────────────────────────────
// Client-side sort of the rows currently on screen. Delegated, so it keeps
// working after the table is swapped in over AJAX.
(function () {
    // col index mapping: th data-col maps to the Nth <td> in each row
    // checkbox col is index 0 (skip), then id=0, company=1 … found=5
    const CELL_OFFSET = 1; // skip the checkbox td

    let sortCol = null;
    let sortDir = 'asc';

    document.addEventListener('click', function (e) {
        const th = e.target.closest('#leadsTable th.sortable');
        if (! th) return;

        const col = parseInt(th.dataset.col);

        if (sortCol === col) {
            sortDir = sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            sortCol = col;
            sortDir = 'asc';
        }

        // Update header icons
        document.querySelectorAll('#leadsTable th.sortable').forEach(function (h) {
            h.classList.remove('sort-asc', 'sort-desc');
            h.querySelector('.sort-icon').className = 'bi bi-chevron-expand sort-icon text-muted ms-1';
        });
        th.classList.add(sortDir === 'asc' ? 'sort-asc' : 'sort-desc');

        // Sort rows
        const tbody = document.querySelector('#leadsTable tbody');
        const rows  = Array.from(tbody.querySelectorAll('tr'));

        rows.sort(function (a, b) {
            const tdA = a.querySelectorAll('td')[col + CELL_OFFSET];
            const tdB = b.querySelectorAll('td')[col + CELL_OFFSET];

            const valA = (tdA ? (tdA.dataset.val || tdA.innerText) : '').trim().toLowerCase();
            const valB = (tdB ? (tdB.dataset.val || tdB.innerText) : '').trim().toLowerCase();

            // Numeric sort for id and timestamp columns (col 0 and col 5)
            if (col === 0 || col === 5) {
                return sortDir === 'asc'
                    ? parseFloat(valA) - parseFloat(valB)
                    : parseFloat(valB) - parseFloat(valA);
            }

            // String sort for everything else
            return sortDir === 'asc'
                ? valA.localeCompare(valB)
                : valB.localeCompare(valA);
        });

        rows.forEach(function (row) { tbody.appendChild(row); });
    });
}());

// ── Select2 + dropdown filters ────────────────────────────────
// Each .select2 filter carries data-param naming the query string key it drives.
// Changing one loads the matching page of leads over AJAX (page reset to 1).
jQuery(function ($) {
    const $filters = $('#statusFilter, #platformFilter, #categoryFilter');

    // Order matters. Select2 fires a `change` on init while it applies the
    // placeholder; binding first would let that init event clear the filter the
    // page was just loaded with. So: initialise first, then bind. The try/catch
    // keeps the binding happening even if Select2 itself fails to load.
    try {
        $filters.each(function () {
            $(this).select2({
                width: '100%',
                minimumResultsForSearch: 5,
                placeholder: $(this).data('placeholder'),
                allowClear: false,
            });
        });
    } catch (err) {
        console.warn('Select2 unavailable - falling back to native selects.', err);
    }

    $filters.on('select2:select change', function (e) {
        const select = this;
        const key    = select.dataset.param;
        // select2:select carries the chosen item and is authoritative even if the
        // underlying <select> has not been written yet.
        const value  = (e && e.params && e.params.data ? e.params.data.id : select.value) || '';

        const url = new URL(window.location.href);

        if (value) {
            url.searchParams.set(key, value);
        } else {
            url.searchParams.delete(key);
        }
        url.searchParams.delete('page'); // reset to page 1

        loadLeads(url.toString());
    });
});

// ── Multiple emails per lead ──────────────────────────────────
// Every address of a lead (falls back to the single `email` of older rows).
function emailsOf(lead) {
    return lead.emails && lead.emails.length ? lead.emails : (lead.email ? [lead.email] : []);
}

// Select2 in "tags" mode: type an address and press Enter / comma / space to add
// it, click the x on a chip to drop it. Only well-formed addresses are accepted.
const EMAIL_RE = /^[^\s@,;]+@[^\s@,;]+\.[^\s@,;]+$/;

function initEmailTags(select) {
    const $ = window.jQuery;
    if (! $ || ! $.fn.select2 || select.dataset.tagsReady) return;

    $(select).select2({
        width: '100%',
        tags: true,
        multiple: true,
        tokenSeparators: [',', ' ', ';'],
        placeholder: select.dataset.placeholder || '',
        selectOnClose: true, // a typed address is kept when the box loses focus
        dropdownParent: $(select).closest('.modal'),
        createTag: function (params) {
            const term = (params.term || '').trim().toLowerCase();
            return EMAIL_RE.test(term) ? { id: term, text: term } : null;
        },
    });

    select.dataset.tagsReady = '1';
}

// Replace the chips with this list.
function setEmailTags(select, emails) {
    initEmailTags(select);

    select.innerHTML = '';
    emails.forEach(function (address) {
        select.appendChild(new Option(address, address, true, true));
    });
    window.jQuery(select).trigger('change');
}

// ── Main email (Edit modal) ───────────────────────────────────
// The first selected address is the main one: messages go to it by default.
// The pencil lets the user pick another; that address is moved to the front.
(function () {
    const select = document.getElementById('edit_emails');
    const label  = document.getElementById('edit_main_label');
    const pencil = document.getElementById('edit_main_pencil');
    const picker = document.getElementById('edit_main_picker');
    const pick   = document.getElementById('edit_main_select');

    function selected() {
        return Array.from(select.selectedOptions).map(o => o.value);
    }

    // Keep the label, pencil and picker in step with the chips.
    function refresh() {
        const list = selected();

        label.textContent = list[0] || '—';
        pencil.classList.toggle('d-none', list.length < 2); // nothing to choose between
        if (list.length < 2) picker.classList.add('d-none');

        pick.innerHTML = '';
        list.forEach(function (address, i) {
            pick.appendChild(new Option(address, address, i === 0, i === 0));
        });
    }

    window.jQuery(select).on('change', refresh);

    pencil.addEventListener('click', function () {
        picker.classList.toggle('d-none');
    });

    pick.addEventListener('change', function () {
        const chosen = Array.from(select.options).find(o => o.value === pick.value);
        if (! chosen) return;

        select.insertBefore(chosen, select.firstChild); // first = main
        window.jQuery(select).trigger('change');
        picker.classList.add('d-none');
    });

    document.getElementById('editModal').addEventListener('hidden.bs.modal', function () {
        picker.classList.add('d-none');
    });
})();

document.getElementById('addLeadModal').addEventListener('shown.bs.modal', function () {
    initEmailTags(document.getElementById('add_emails'));
});
document.getElementById('editModal').addEventListener('shown.bs.modal', function () {
    initEmailTags(document.getElementById('edit_emails'));
});

// ── Find Leads: runs in the background ────────────────────────
// Submitted by fetch so the page does not reload. Find stays disabled while the
// job runs; the notification poller announces the end ('app:notification'),
// which re-enables it and refreshes the table.
(function () {
    const form    = document.getElementById('searchForm');
    const btn     = document.getElementById('findBtn');
    const spinner = document.getElementById('spinner');
    const icon    = document.getElementById('btnIcon');
    const errBox  = document.getElementById('searchError');
    let failsafe  = null;

    function setBusy(on) {
        btn.disabled = on;
        spinner.classList.toggle('d-none', ! on);
        icon.classList.toggle('d-none', on);
        window.clearTimeout(failsafe);
        // Never leave the button locked forever if the notification is missed.
        if (on) failsafe = window.setTimeout(function () { setBusy(false); }, 180000);
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        errBox.classList.add('d-none');

        const cat = document.getElementById('search_category');
        if (! cat.value) {
            cat.classList.add('is-invalid');
            cat.focus();
            return;
        }
        cat.classList.remove('is-invalid');

        setBusy(true);

        fetch(form.action, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(form),
        })
            .then(function (r) {
                if (r.ok) {
                    document.getElementById('keyword').value = '';
                    return; // stay busy until the job's notification arrives
                }
                return r.json().catch(function () { return {}; }).then(function (data) {
                    errBox.textContent = data.errors ? Object.values(data.errors)[0][0] : (data.message || 'Could not start the search.');
                    errBox.classList.remove('d-none');
                    setBusy(false);
                });
            })
            .catch(function () {
                errBox.textContent = 'Could not start the search. Please try again.';
                errBox.classList.remove('d-none');
                setBusy(false);
            });
    });

    document.addEventListener('app:notification', function (e) {
        if (! /^Lead search/.test(e.detail.title || '')) return;

        setBusy(false);
        loadLeads(window.location.href, { push: false, quiet: true });
    });
})();

// ── Live table filter (keyup) ─────────────────────────────────
document.getElementById('tableFilter').addEventListener('keyup', function () {
    applyLiveFilter(this.value);
});

function applyLiveFilter(value) {
    const term = (value || '').toLowerCase().trim();
    document.querySelectorAll('#leadsTable tbody tr').forEach(function (row) {
        const haystack = row.getAttribute('data-search') || '';
        row.classList.toggle('d-none', term !== '' && !haystack.includes(term));
    });
}

// ── Select all / row checkboxes ───────────────────────────────
document.addEventListener('change', function (e) {
    if (e.target.id === 'selectAll') {
        document.querySelectorAll('.row-check').forEach(cb => cb.checked = e.target.checked);
        updateBulkToolbar();
    } else if (e.target.classList.contains('row-check')) {
        updateBulkToolbar();
    }
});

function resetBulkSelection() {
    // A freshly swapped table has no rows selected; re-apply the live filter
    // so a typed search term still narrows the new page.
    updateBulkToolbar();
    applyLiveFilter(document.getElementById('tableFilter').value);
}

function updateBulkToolbar() {
    const checked = [...document.querySelectorAll('.row-check:checked')];
    const toolbar  = document.getElementById('bulkToolbar');
    document.getElementById('selectedCount').textContent = checked.length;

    if (checked.length > 0) {
        toolbar.classList.remove('d-none');

        // Populate hidden id inputs for both bulk forms
        ['bulkStatusIds', 'bulkDeleteIds'].forEach(function (containerId) {
            const container = document.getElementById(containerId);
            container.innerHTML = '';
            checked.forEach(function (cb) {
                const input = document.createElement('input');
                input.type  = 'hidden';
                input.name  = 'ids[]';
                input.value = cb.value;
                container.appendChild(input);
            });
        });
    } else {
        toolbar.classList.add('d-none');
        const selectAll = document.getElementById('selectAll');
        if (selectAll) selectAll.checked = false;
    }
}

// ── Load templates into compose dropdown ──────────────────────
let cachedTemplates = [];

fetch(leadRoutes.apiTemplates, { headers: { 'Accept': 'application/json' } })
    .then(r => r.json())
    .then(function (templates) {
        cachedTemplates = templates;
        const sel = document.getElementById('compose_template');
        templates.forEach(function (t) {
            const opt    = document.createElement('option');
            opt.value    = t.id;
            opt.textContent = t.name;
            sel.appendChild(opt);
        });
    });

// Current lead context (set when compose button is clicked)
let currentLeadId      = null;
let currentClientName  = '';  // updated by background scrape
let currentLeadWebsite = '';

const senderName    = @json($senderName);
const senderCompany = @json($senderCompany);

function applyPlaceholders(text) {
    return text
        .replace(/\[Client Name\]/gi,       currentClientName)
        .replace(/\[Company Name\]/gi,       currentClientName)
        .replace(/\[Your Company Name\]/gi,  senderCompany)
        .replace(/\[Your Name\]/gi,          senderName)
        .replace(/\[Sender Name\]/gi,        senderName);
}

// When a template is selected — populate subject + body with placeholders replaced
document.getElementById('compose_template').addEventListener('change', function () {
    const tpl = cachedTemplates.find(t => t.id == this.value);
    if (! tpl) return;

    document.getElementById('compose_subject').value = applyPlaceholders(tpl.subject);

    // Render HTML directly into the contenteditable div (preserves bold, lists, etc.)
    document.getElementById('compose_body').innerHTML = applyPlaceholders(tpl.body);

    // Load template attachments into compose modal
    window.setTemplateAttachments(tpl.attachments || []);
});

// Scrape website in background and silently update client name + any open template body
function scrapeContactInBackground(leadId, fallbackName) {
    fetch(leadRoute(leadRoutes.scrapeContact, leadId), { headers: { 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(function (data) {
            if (! data.client_name) return;
            const oldName = currentClientName;
            currentClientName = data.client_name;

            // If a template is already selected and the old placeholder name is still present, update it
            if (oldName && oldName !== currentClientName) {
                const subjectEl = document.getElementById('compose_subject');
                const bodyEl    = document.getElementById('compose_body');
                const re = new RegExp(oldName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g');
                subjectEl.value  = subjectEl.value.replace(re, currentClientName);
                bodyEl.innerHTML = bodyEl.innerHTML.replace(re, currentClientName);
            }
        })
        .catch(function () { /* silent fail */ });
}

// ── Compose Modal (Gmail-style) ───────────────────────────────
// Delegated: row buttons are replaced whenever the table reloads over AJAX.
document.addEventListener('click', function (event) {
    const btn = event.target.closest('.btn-compose');
    if (btn) {
        const id      = btn.dataset.id;
        const to      = btn.dataset.to;
        const name    = btn.dataset.name;
        const website = btn.dataset.website || '';

        currentLeadId      = id;
        currentClientName  = name;
        currentLeadWebsite = website;

        document.getElementById('composeModalTitle').textContent = 'New Message — ' + name;
        const toSelect = document.getElementById('compose_to');
        let   addresses = [];
        try { addresses = JSON.parse(btn.dataset.emails || '[]'); } catch (e) { addresses = []; }
        if (! addresses.length && to) addresses = [to];

        toSelect.innerHTML = '';
        addresses.forEach(function (address, i) {
            toSelect.appendChild(new Option(address + (i === 0 && addresses.length > 1 ? '  (main)' : ''), address, i === 0, i === 0));
        });
        document.getElementById('compose_subject').value         = '';
        document.getElementById('compose_body').innerHTML        = '';
        document.getElementById('compose_body_hidden').value     = '';
        document.getElementById('compose_template').value        = '';
        document.getElementById('composeForm').action            = leadRoute(leadRoutes.sendEmail, id);
        document.getElementById('compose_redirect_back').value   = window.location.search;

        // Scrape website in background to get real company name
        if (website) {
            scrapeContactInBackground(id, name);
        }

        new bootstrap.Modal(document.getElementById('composeModal')).show();
        // Focus subject so user can start typing immediately
        document.getElementById('composeModal').addEventListener('shown.bs.modal', function handler() {
            document.getElementById('compose_subject').focus();
            this.removeEventListener('shown.bs.modal', handler);
        });
    }
});

// ── Show Sent Email Modal ─────────────────────────────────────
document.addEventListener('click', function (event) {
    const btn = event.target.closest('.btn-show-email');
    if (btn) {
        const id    = btn.dataset.id;
        const modal = new bootstrap.Modal(document.getElementById('showEmailModal'));
        document.getElementById('showEmailBody').innerHTML =
            '<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>';
        modal.show();

        fetch(leadRoute(leadRoutes.sentEmail, id), { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(function (data) {
                const email = data.email;
                const lead  = data.lead;
                let attachments = [];
                if (email && email.attachments) {
                    attachments = typeof email.attachments === 'string'
                        ? JSON.parse(email.attachments)
                        : email.attachments;
                    if (!Array.isArray(attachments)) attachments = [];
                }

                if (!email) {
                    document.getElementById('showEmailBody').innerHTML =
                        '<div class="empty-state"><i class="bi bi-inbox empty-state-icon"></i>No sent email found.</div>';
                    return;
                }

                // Build attachment chips
                const FILE_ICONS = {
                    'pdf': '#ea4335', 'doc': '#4285f4', 'docx': '#4285f4',
                    'xls': '#34a853', 'xlsx': '#34a853', 'ppt': '#fbbc05', 'pptx': '#fbbc05',
                    'zip': '#757575', 'rar': '#757575', 'jpg': '#4285f4', 'jpeg': '#4285f4',
                    'png': '#4285f4', 'gif': '#4285f4', 'mp4': '#ea4335', 'mp3': '#ea4335',
                };
                function fmtSize(b) {
                    if (b < 1024) return b + ' B';
                    if (b < 1048576) return (b/1024).toFixed(1) + ' KB';
                    return (b/1048576).toFixed(1) + ' MB';
                }

                let attachHtml = '';
                if (attachments.length > 0) {
                    attachHtml = '<div class="px-3 pb-3 d-flex flex-wrap gap-2">';
                    attachments.forEach(function (att) {
                        const ext      = att.name.split('.').pop().toLowerCase();
                        const color    = FILE_ICONS[ext] || '#5f6368';
                        const dlUrl    = leadRoutes.attachmentDownload
                            + '?path=' + encodeURIComponent(att.path)
                            + '&name=' + encodeURIComponent(att.name);
                        attachHtml += `
                            <a href="${dlUrl}" download="${att.name}" class="attach-chip text-decoration-none"
                               title="Download ${att.name}" style="cursor:pointer;">
                                <i class="bi bi-file-earmark-fill attach-icon" style="color:${color}"></i>
                                <span class="attach-name">${att.name}</span>
                                <span class="attach-size">${fmtSize(att.size)}</span>
                                <i class="bi bi-download ms-1" style="font-size:.7rem;"></i>
                            </a>`;
                    });
                    attachHtml += '</div>';
                }

                document.getElementById('showEmailBody').innerHTML = `
                    <div class="border-bottom px-3 py-2 d-flex gap-2 align-items-center compose-row">
                        <span class="fs-13 compose-label">To</span>
                        <span class="fw-semibold">${lead.email}</span>
                    </div>
                    <div class="border-bottom px-3 py-2 d-flex gap-2 align-items-center">
                        <span class="fs-13 compose-label">Subject</span>
                        <span class="fw-semibold">${email.subject}</span>
                    </div>
                    <div class="border-bottom px-3 py-2 d-flex gap-2 align-items-center">
                        <span class="fs-13 compose-label">Sent</span>
                        <span class="fs-13">${new Date(email.sent_at).toLocaleString()}</span>
                    </div>
                    <div class="px-3 py-3" style="min-height:120px;white-space:pre-wrap;font-size:.92rem;line-height:1.7;">${email.body}</div>
                    ${attachments.length > 0 ? '<div class="border-top px-3 pt-2 pb-1 fs-13"><i class="bi bi-paperclip me-1"></i>' + attachments.length + ' attachment' + (attachments.length > 1 ? 's' : '') + '</div>' + attachHtml : ''}
                `;
            });
    }
});

// ── View Modal ────────────────────────────────────────────────
document.addEventListener('click', function (event) {
    const btn = event.target.closest('.btn-view');
    if (btn) {
        const id    = btn.dataset.id;
        const modal = new bootstrap.Modal(document.getElementById('viewModal'));
        document.getElementById('viewModalBody').innerHTML =
            '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
        modal.show();

        fetch(leadRoute(leadRoutes.show, id), { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(function (lead) {
                const statusBadges = {
                    new:     'badge-secondary',
                    sent:    'badge-primary',
                    failed:  'badge-danger',
                    replied: 'badge-success',
                };
                const badgeClass   = statusBadges[lead.status] || 'badge-secondary';
                const platformName = lead.platform  ? lead.platform.name  : '—';
                const categoryName = lead.category  ? lead.category.name  : '—';
                document.getElementById('viewModalBody').innerHTML = `
                    <div class="row g-3">
                        <div class="col-md-6">
                            <p class="fs-13 mb-1">Platform</p>
                            <h6 class="mb-0">${platformName}</h6>
                        </div>
                        <div class="col-md-6">
                            <p class="fs-13 mb-1">Status</p>
                            <span class="badge ${badgeClass} light">${lead.status.charAt(0).toUpperCase() + lead.status.slice(1)}</span>
                        </div>
                        <div class="col-md-6">
                            <p class="fs-13 mb-1">Company Name</p>
                            <h6 class="mb-0">${lead.company_name}</h6>
                        </div>
                        <div class="col-md-6">
                            <p class="fs-13 mb-1">Website</p>
                            <a href="${lead.website}" target="_blank" rel="noopener" class="text-primary">
                                <i class="bi bi-box-arrow-up-right me-1"></i>${lead.website}
                            </a>
                        </div>
                        <div class="col-md-6">
                            <p class="fs-13 mb-1">LinkedIn</p>
                            ${lead.linkedin
                                ? `<a href="${lead.linkedin}" target="_blank" rel="noopener" class="text-primary">${lead.linkedin}</a>`
                                : '<span class="fst-italic">—</span>'}
                        </div>
                        <div class="col-md-6">
                            <p class="fs-13 mb-1">Email</p>
                            ${emailsOf(lead).length
                                ? emailsOf(lead).map(a => `<a href="mailto:${a}" class="text-primary">${a}</a>`).join(', ')
                                : '<span class="fst-italic">Not found</span>'}
                        </div>
                        <div class="col-md-6">
                            <p class="fs-13 mb-1">Category</p>
                            <h6 class="mb-0">${categoryName}</h6>
                        </div>
                        <div class="col-md-6">
                            <p class="fs-13 mb-1">Created</p>
                            <span>${new Date(lead.created_at).toLocaleString()}</span>
                        </div>
                    </div>`;
            });
    }
});

// ── Edit Modal ────────────────────────────────────────────────
document.addEventListener('click', function (event) {
    const btn = event.target.closest('.btn-edit');
    if (btn) {
        const id    = btn.dataset.id;
        const modal = new bootstrap.Modal(document.getElementById('editModal'));

        fetch(leadRoute(leadRoutes.edit, id), { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(function (lead) {
                document.getElementById('editForm').action = leadRoute(leadRoutes.update, lead.id);
                document.getElementById('edit_company_name').value  = lead.company_name || '';
                document.getElementById('edit_website').value       = lead.website      || '';
                setEmailTags(document.getElementById('edit_emails'), emailsOf(lead));
                document.getElementById('edit_linkedin').value      = lead.linkedin     || '';
                document.getElementById('edit_status').value        = lead.status       || 'new';
                document.getElementById('edit_platform_id').value   = lead.platform_id  || '';
                document.getElementById('edit_category_id').value   = lead.category_id  || '';
                document.getElementById('edit_redirect_back').value = window.location.search;
                modal.show();
            });
    }
});

// ── Gmail-style Attachment Upload ─────────────────────────────
(function () {
    const MAX_SIZE  = 10 * 1024 * 1024; // 10 MB per file
    const MAX_FILES = 10;
    const input     = document.getElementById('attachmentInput');
    const list      = document.getElementById('attachmentList');
    const form      = document.getElementById('composeForm');
    const sendBtn   = form.querySelector('[type="submit"]');
    let   files              = []; // user-uploaded File objects
    let   templateAttachments = []; // [{name, path, size}] from template

    const FILE_ICONS = {
        'pdf':  { icon: 'bi-file-earmark-pdf-fill',  color: '#ea4335' },
        'doc':  { icon: 'bi-file-earmark-word-fill',  color: '#4285f4' },
        'docx': { icon: 'bi-file-earmark-word-fill',  color: '#4285f4' },
        'xls':  { icon: 'bi-file-earmark-excel-fill', color: '#34a853' },
        'xlsx': { icon: 'bi-file-earmark-excel-fill', color: '#34a853' },
        'ppt':  { icon: 'bi-file-earmark-ppt-fill',   color: '#fbbc05' },
        'pptx': { icon: 'bi-file-earmark-ppt-fill',   color: '#fbbc05' },
        'zip':  { icon: 'bi-file-earmark-zip-fill',   color: '#757575' },
        'rar':  { icon: 'bi-file-earmark-zip-fill',   color: '#757575' },
        'jpg':  { icon: 'bi-file-earmark-image-fill', color: '#4285f4' },
        'jpeg': { icon: 'bi-file-earmark-image-fill', color: '#4285f4' },
        'png':  { icon: 'bi-file-earmark-image-fill', color: '#4285f4' },
        'gif':  { icon: 'bi-file-earmark-image-fill', color: '#4285f4' },
        'mp4':  { icon: 'bi-file-earmark-play-fill',  color: '#ea4335' },
        'mp3':  { icon: 'bi-file-earmark-music-fill', color: '#ea4335' },
    };

    function formatSize(bytes) {
        if (bytes < 1024)    return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    function getIcon(name) {
        const ext = name.split('.').pop().toLowerCase();
        return FILE_ICONS[ext] || { icon: 'bi-file-earmark-fill', color: '#5f6368' };
    }

    function renderChips() {
        list.innerHTML = '';

        // Template attachments (server-side, shown with a tpl badge)
        templateAttachments.forEach(function (att, idx) {
            const ic   = getIcon(att.name);
            const chip = document.createElement('div');
            chip.className = 'attach-chip';
            chip.title     = att.name + ' (from template)';
            chip.innerHTML = `
                <i class="bi ${ic.icon} attach-icon" style="color:${ic.color}"></i>
                <span class="attach-name">${att.name}</span>
                <span class="attach-size">${formatSize(att.size)}</span>
                <span class="badge badge-secondary light badge-xs ms-1">tpl</span>
                <span class="attach-remove" data-tpl-idx="${idx}" title="Remove">&#x2715;</span>
            `;
            list.appendChild(chip);
        });

        // User-uploaded files
        files.forEach(function (file, idx) {
            const isTooBig = file.size > MAX_SIZE;
            const ic   = getIcon(file.name);
            const chip = document.createElement('div');
            chip.className = 'attach-chip' + (isTooBig ? ' attach-error' : '');
            chip.title     = file.name + (isTooBig ? ' — Exceeds 10 MB limit' : '');
            chip.innerHTML = `
                <i class="bi ${ic.icon} attach-icon" style="color:${isTooBig ? '#dc3545' : ic.color}"></i>
                <span class="attach-name">${file.name}</span>
                <span class="attach-size">${formatSize(file.size)}</span>
                <span class="attach-remove" data-idx="${idx}" title="Remove">&#x2715;</span>
            `;
            list.appendChild(chip);
        });

        list.querySelectorAll('.attach-remove[data-tpl-idx]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                templateAttachments.splice(parseInt(this.dataset.tplIdx), 1);
                renderChips();
            });
        });

        list.querySelectorAll('.attach-remove[data-idx]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                files.splice(parseInt(this.dataset.idx), 1);
                renderChips();
            });
        });
    }

    // File picker change
    input.addEventListener('change', function () {
        Array.from(this.files).forEach(function (file) {
            const dup = files.some(f => f.name === file.name && f.size === file.size);
            if (!dup && files.length < MAX_FILES) {
                files.push(file);
            }
        });
        this.value = ''; // reset picker so same file can be re-added after removal
        renderChips();
    });

    // Clear on discard / modal close
    function clearAttachments() {
        files              = [];
        templateAttachments = [];
        list.innerHTML     = '';
        input.value        = '';
    }

    // Called from template-change handler (outside IIFE scope via window)
    window.setTemplateAttachments = function (atts) {
        templateAttachments = atts || [];
        renderChips();
    };
    document.getElementById('discardBtn').addEventListener('click', clearAttachments);
    document.getElementById('composeModal').addEventListener('hidden.bs.modal', clearAttachments);

    // Intercept submit — build FormData manually so files array is used
    form.addEventListener('submit', function (e) {
        e.preventDefault();

        // Sync contenteditable body text → hidden textarea before FormData is built
        const bodyText = document.getElementById('compose_body').innerText.trim();
        document.getElementById('compose_body_hidden').value = bodyText;

        if (! bodyText) {
            alert('Please write a message before sending.');
            return;
        }

        const oversized = files.filter(f => f.size > MAX_SIZE);
        if (oversized.length) {
            alert('Please remove files that exceed 10 MB:\n' + oversized.map(f => f.name).join('\n'));
            return;
        }

        const fd = new FormData(form);
        // Remove any stale attachment entries from FormData
        fd.delete('attachments[]');
        // Append user-uploaded files
        files.forEach(function (file) {
            fd.append('attachments[]', file, file.name);
        });
        // Append template attachment paths (server-side files)
        templateAttachments.forEach(function (att) {
            fd.append('template_attachment_paths[]', att.path);
            fd.append('template_attachment_names[]', att.name);
        });

        // Show sending state
        sendBtn.disabled = true;
        sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Sending…';

        fetch(form.action, {
            method:   'POST',
            body:     fd,
            redirect: 'follow',
        })
        .then(function (res) {
            if (res.ok || res.redirected) {
                window.location.href = res.redirected ? res.url : leadRoutes.index;
            } else {
                throw new Error('Server error: ' + res.status);
            }
        })
        .catch(function (err) {
            sendBtn.disabled = false;
            sendBtn.innerHTML = '<i class="bi bi-send-fill me-1"></i>Send';
            alert('Failed to send email. Please try again.');
        });
    });
}());
</script>
@endpush
