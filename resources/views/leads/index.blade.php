@extends('layouts.app')

@section('title', 'AI Client Finder - Leads')

@section('content')

{{-- ===================== SEARCH CARD ===================== --}}
<div class="card search-card mb-4">
    <div class="card-body p-4">
        <h5 class="card-title mb-3">
            <i class="bi bi-search me-2 text-primary"></i>Find New Leads
        </h5>
        <form action="{{ route('leads.search') }}" method="POST" id="searchForm">
            @csrf
            <div class="row g-3 align-items-start">
                <div class="col-12 col-md-8">
                    <input
                        type="text" name="keyword" id="keyword"
                        class="form-control form-control-lg @error('keyword') is-invalid @enderror"
                        placeholder="e.g. web design agency London, plumbing company Manchester"
                        value="{{ old('keyword') }}" required minlength="2" maxlength="200" autofocus
                    >
                    @error('keyword')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">Enter a keyword leads will appear instantly, emails extracted in the background.</div>
                </div>
                <div class="col-12 col-md-4">
                    <button type="submit" class="btn btn-primary btn-lg w-100" id="findBtn">
                        <span class="spinner-border spinner-border-sm d-none me-1" id="spinner"></span>
                        <i class="bi bi-lightning-charge-fill me-1" id="btnIcon"></i>Find Leads
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- ===================== LEADS TABLE ===================== --}}
<div class="card border-0 shadow-sm rounded-3">

    {{-- Table header --}}
    <div class="card-header bg-white py-3">
        <div class="row g-2 align-items-center">

            {{-- Title + count --}}
            <div class="col-auto">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-people-fill me-2 text-secondary"></i>Leads
                    <span class="badge bg-secondary ms-1">{{ $leads->total() }}</span>
                </h6>
            </div>

            {{-- Add Lead button --}}
            <div class="col-auto">
                <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addLeadModal">
                    <i class="bi bi-plus-lg me-1"></i>Add Lead
                </button>
            </div>

            {{-- Live filter --}}
            <div class="col">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0">
                        <i class="bi bi-funnel"></i>
                    </span>
                    <input
                        type="text" id="tableFilter"
                        class="form-control border-start-0 bg-light"
                        placeholder="Filter by company, email, website, status…"
                    >
                </div>
            </div>

            {{-- Status badges (clickable filters — server-side) --}}
            @php $activeStatus = request('status'); @endphp
            <div class="col-auto d-flex gap-1 align-items-center">
                @foreach(['new' => ['cls' => 'badge-new', 'label' => 'New'], 'sent' => ['cls' => 'badge-sent', 'label' => 'Sent'], 'failed' => ['cls' => 'badge-failed', 'label' => 'Failed'], 'replied' => ['cls' => 'badge-replied', 'label' => 'Replied']] as $s => $cfg)
                    @php
                        $isActive = $activeStatus === $s;
                        $params = request()->except(['status', 'page']);
                        $url = $isActive
                            ? url()->current() . (count($params) ? '?' . http_build_query($params) : '')
                            : url()->current() . '?' . http_build_query(array_merge($params, ['status' => $s, 'page' => 1]));
                    @endphp
                    <a href="{{ $url }}"
                       class="badge {{ $cfg['cls'] }} text-white text-decoration-none"
                       style="{{ $isActive ? 'outline:2px solid #fff;outline-offset:2px;' : '' }}"
                       title="{{ $isActive ? 'Clear filter' : 'Filter by '.$cfg['label'] }}">
                        {{ $cfg['label'] }}
                    </a>
                @endforeach

                <a href="{{ url()->current() }}" class="btn btn-sm btn-primary py-0 px-2 ms-1" title="Clear all filters">
                    <i class="bi bi-x-circle me-1"></i>Clear
                </a>
            </div>
        </div>

        {{-- Bulk action toolbar (hidden until rows are selected) --}}
        <div id="bulkToolbar" class="mt-2 d-none">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="text-muted small me-1">
                    <span id="selectedCount">0</span> selected
                </span>

                {{-- Bulk status update --}}
                <form method="POST" action="{{ route('leads.bulk-status') }}" id="bulkStatusForm" class="d-flex gap-1">
                    @csrf
                    <div id="bulkStatusIds"></div>
                    <select name="status" class="form-select form-select-sm" style="width:130px">
                        <option value="new">New</option>
                        <option value="sent">Sent</option>
                        <option value="failed">Failed</option>
                        <option value="replied">Replied</option>
                    </select>
                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-tag me-1"></i>Set Status
                    </button>
                </form>

                {{-- Bulk delete --}}
                <form method="POST" action="{{ route('leads.bulk-delete') }}" id="bulkDeleteForm">
                    @csrf
                    <div id="bulkDeleteIds"></div>
                    <button
                        type="submit" class="btn btn-sm btn-outline-danger"
                        onclick="return confirm('Delete selected leads? This cannot be undone.')"
                    >
                        <i class="bi bi-trash me-1"></i>Delete Selected
                    </button>
                </form>
            </div>
        </div>
    </div>

    @if($leads->isEmpty())
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-inbox display-4 d-block mb-3"></i>
            No leads yet. Use the search above to find your first leads.
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="leadsTable">
                <thead class="table-light">
                    <tr>
                        <th scope="col" style="width:40px">
                            <input type="checkbox" class="form-check-input" id="selectAll" title="Select all">
                        </th>
                        <th scope="col" class="sortable" data-col="0">#
                            <i class="bi bi-chevron-expand sort-icon text-muted ms-1"></i>
                        </th>
                        <th scope="col" class="sortable" data-col="1">Company
                            <i class="bi bi-chevron-expand sort-icon text-muted ms-1"></i>
                        </th>
                        <th scope="col" class="sortable" data-col="2">Website
                            <i class="bi bi-chevron-expand sort-icon text-muted ms-1"></i>
                        </th>
                        <th scope="col" class="sortable" data-col="3">Email
                            <i class="bi bi-chevron-expand sort-icon text-muted ms-1"></i>
                        </th>
                        <th scope="col" class="sortable" data-col="4">Status
                            <i class="bi bi-chevron-expand sort-icon text-muted ms-1"></i>
                        </th>
                        <th scope="col" class="sortable" data-col="5">Platform
                            <i class="bi bi-chevron-expand sort-icon text-muted ms-1"></i>
                        </th>
                        <th scope="col" class="sortable" data-col="6">Found
                            <i class="bi bi-chevron-expand sort-icon text-muted ms-1"></i>
                        </th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($leads as $lead)
                    <tr data-id="{{ $lead->id }}"
                        data-search="{{ strtolower($lead->company_name . ' ' . $lead->email . ' ' . $lead->website . ' ' . $lead->status) }}">

                        {{-- Checkbox --}}
                        <td>
                            <input type="checkbox" class="form-check-input row-check" value="{{ $lead->id }}">
                        </td>

                        <td class="text-muted small" data-val="{{ $lead->id }}">{{ $lead->id }}</td>

                        <td data-val="{{ strtolower($lead->company_name) }}">
                            <span class="fw-semibold">{{ $lead->company_name }}</span>
                        </td>

                        <td data-val="{{ parse_url($lead->website, PHP_URL_HOST) }}" style="max-width:180px;">
                            <a href="{{ $lead->website }}" target="_blank" rel="noopener" class="text-decoration-none small d-block text-truncate" title="{{ $lead->website }}">
                                <i class="bi bi-box-arrow-up-right me-1"></i>
                                {{ parse_url($lead->website, PHP_URL_HOST) }}
                            </a>
                        </td>

                        <td data-val="{{ strtolower($lead->email ?? '') }}">
                            @if($lead->email)
                                <a href="mailto:{{ $lead->email }}" class="text-decoration-none small">{{ $lead->email }}</a>
                            @else
                                <span class="text-muted small fst-italic">Not found</span>
                            @endif
                        </td>

                        <td data-val="{{ $lead->status }}">
                            @php
                                $badgeClass = match($lead->status) {
                                    'sent'    => 'badge-sent',
                                    'failed'  => 'badge-failed',
                                    'replied' => 'badge-replied',
                                    default   => 'badge-new',
                                };
                            @endphp
                            <span class="badge {{ $badgeClass }} text-white">{{ ucfirst($lead->status) }}</span>
                        </td>

                        <td data-val="{{ strtolower($lead->platform?->name ?? '') }}">
                            @php
                                $platformIcons = [
                                    'google'      => ['icon' => 'bi-google',           'color' => '#4285F4'],
                                    'linkedin'    => ['icon' => 'bi-linkedin',         'color' => '#0A66C2'],
                                    'upwork'      => ['icon' => 'bi-briefcase',        'color' => '#6fda44'],
                                    'freelancing' => ['icon' => 'bi-person-workspace', 'color' => '#f26722'],
                                    'facebook'    => ['icon' => 'bi-facebook',         'color' => '#1877F2'],
                                ];
                                $platformName = $lead->platform?->name ?? '—';
                                $key = strtolower($platformName);
                                $p   = $platformIcons[$key] ?? ['icon' => 'bi-globe', 'color' => '#6c757d'];
                            @endphp
                            <span class="small" style="color:{{ $p['color'] }}">
                                <i class="bi {{ $p['icon'] }} me-1"></i>{{ $platformName }}
                            </span>
                        </td>

                        <td data-val="{{ $lead->created_at->timestamp }}" class="text-muted small">
                            {{ $lead->created_at->diffForHumans() }}
                        </td>

                        {{-- 3-dot kebab menu --}}
                        <td class="text-end">
                            <div class="dropdown">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-light border rounded-circle d-flex align-items-center justify-content-center"
                                    style="width:32px;height:32px;"
                                    data-bs-toggle="dropdown"
                                    aria-expanded="false"
                                >
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>

                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="min-width:180px;">

                                    {{-- View Details --}}
                                    <li>
                                        <button type="button"
                                            class="dropdown-item btn-view"
                                            data-id="{{ $lead->id }}">
                                            <i class="bi bi-eye me-2 text-secondary"></i>View Details
                                        </button>
                                    </li>

                                    {{-- Edit --}}
                                    <li>
                                        <button type="button"
                                            class="dropdown-item btn-edit"
                                            data-id="{{ $lead->id }}">
                                            <i class="bi bi-pencil-square me-2 text-warning"></i>Edit
                                        </button>
                                    </li>

                                    {{-- Send Email / Retry --}}
                                    {{-- Send Email → opens Gmail-style compose modal --}}
                                    @if($lead->email && in_array($lead->status, ['new', 'failed']))
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <button type="button"
                                                class="dropdown-item btn-compose"
                                                data-id="{{ $lead->id }}"
                                                data-name="{{ addslashes($lead->company_name) }}"
                                                data-to="{{ $lead->email }}">
                                                @if($lead->status === 'failed')
                                                    <i class="bi bi-arrow-repeat me-2 text-danger"></i>Retry Email
                                                @else
                                                    <i class="bi bi-send me-2 text-primary"></i>Send Email
                                                @endif
                                            </button>
                                        </li>
                                    @endif

                                    {{-- Mark as Sent: only when lead has email but Send Email button is NOT shown --}}
                                    @if($lead->email && $lead->status === 'replied')
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form method="POST"
                                                action="{{ route('leads.mark-sent', $lead->id) }}"
                                                onsubmit="return confirm('Mark \'{{ addslashes($lead->company_name) }}\' as Sent?')">
                                                @csrf
                                                <button type="submit" class="dropdown-item text-success">
                                                    <i class="bi bi-check2-circle me-2"></i>Mark as Sent
                                                </button>
                                            </form>
                                        </li>
                                    @endif

                                    {{-- Delete --}}
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="POST"
                                            action="{{ route('leads.destroy', $lead->id) }}"
                                            onsubmit="return confirm('Delete {{ addslashes($lead->company_name) }}?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="dropdown-item text-danger">
                                                <i class="bi bi-trash me-2"></i>Delete
                                            </button>
                                        </form>
                                    </li>

                                </ul>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Footer: per-page + info (left) | pagination (right) --}}
        <div class="card-footer bg-white py-2 px-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">

                {{-- LEFT: per-page selector + result info --}}
                <div class="d-flex align-items-center gap-3">

                    {{-- Per-page selector --}}
                    <div class="d-flex align-items-center gap-2">
                        <label class="text-muted small mb-0 text-nowrap">Rows per page:</label>
                        <select id="perPageSelect" class="form-select form-select-sm" style="width:75px">
                            @foreach([10, 20, 50, 100] as $size)
                                <option value="{{ $size }}" {{ request('per_page', 10) == $size ? 'selected' : '' }}>
                                    {{ $size }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Showing X to Y of Z --}}
                    <span class="text-muted small">
                        Showing
                        <strong>{{ $leads->firstItem() }}</strong> - <strong>{{ $leads->lastItem() }}</strong>
                        of <strong>{{ $leads->total() }}</strong> results
                    </span>

                </div>

                {{-- RIGHT: pagination links --}}
                @if($leads->hasPages())
                    <div>{{ $leads->appends(request()->query())->links() }}</div>
                @endif

            </div>
        </div>
    @endif
</div>

{{-- ===================== COMPOSE MODAL (Gmail-style) ===================== --}}
<div class="modal fade" id="composeModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:12px;overflow:hidden;">

            {{-- Header bar --}}
            <div class="modal-header text-white py-2 px-3" style="background:#404040;">
                <span class="fw-semibold small" id="composeModalTitle">New Message</span>
                <button type="button" class="btn-close btn-close-white btn-sm" data-bs-dismiss="modal"></button>
            </div>

            <form id="composeForm" method="POST">
                @csrf

                {{-- Template selector --}}
                <div class="border-bottom px-3 py-2 d-flex align-items-center gap-2" style="background:#f8f9fa;">
                    <span class="text-muted small text-nowrap" style="width:50px">Template</span>
                    <select id="compose_template" class="form-select form-select-sm border-0 bg-transparent shadow-none">
                        <option value="">— Select a template (optional) —</option>
                    </select>
                </div>

                {{-- To --}}
                <div class="border-bottom px-3 py-2 d-flex align-items-center gap-2">
                    <span class="text-muted small" style="width:50px">To</span>
                    <input type="email" name="to_display" id="compose_to"
                        class="form-control form-control-sm border-0 shadow-none bg-transparent fw-semibold"
                        readonly>
                </div>

                {{-- Subject --}}
                <div class="border-bottom px-3 py-2 d-flex align-items-center gap-2">
                    <span class="text-muted small" style="width:50px">Subject</span>
                    <input type="text" name="subject" id="compose_subject"
                        class="form-control form-control-sm border-0 shadow-none bg-transparent"
                        placeholder="Subject" required>
                </div>

                {{-- Body --}}
                <div class="px-1">
                    <textarea name="body" id="compose_body"
                        class="form-control border-0 shadow-none"
                        rows="12"
                        style="resize:vertical;font-size:.92rem;line-height:1.6;"
                        placeholder="Write your message here…"
                        required></textarea>
                </div>

                {{-- Footer toolbar --}}
                <div class="px-3 py-2 border-top d-flex align-items-center justify-content-between bg-light">
                    <button type="submit" class="btn btn-primary btn-sm px-4">
                        <i class="bi bi-send-fill me-1"></i>Send
                    </button>
                    <button type="button" class="btn btn-link btn-sm text-danger text-decoration-none" data-bs-dismiss="modal">
                        <i class="bi bi-trash me-1"></i>Discard
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>

{{-- ===================== VIEW MODAL ===================== --}}
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-eye me-2"></i>View Lead</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
            </div>
        </div>
    </div>
</div>

{{-- ===================== EDIT MODAL ===================== --}}
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Lead</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editForm" method="POST">
                    @csrf @method('PUT')
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Platform</label>
                            <select name="platform_id" id="edit_platform_id" class="form-select">
                                <option value="">— Select Platform —</option>
                                @foreach($platforms as $plt)
                                    <option value="{{ $plt->id }}">{{ $plt->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                            <select name="status" id="edit_status" class="form-select">
                                <option value="new">New</option>
                                <option value="sent">Sent</option>
                                <option value="failed">Failed</option>
                                <option value="replied">Replied</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Company Name <span class="text-danger">*</span></label>
                            <input type="text" name="company_name" id="edit_company_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Website <span class="text-danger">*</span></label>
                            <input type="url" name="website" id="edit_website" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">LinkedIn URL</label>
                            <input type="url" name="linkedin" id="edit_linkedin" class="form-control" placeholder="https://linkedin.com/company/...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" name="email" id="edit_email" class="form-control">
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
    #leadsTable tbody tr.d-none { display: none !important; }

    /* Sortable column headers */
    th.sortable {
        cursor: pointer;
        user-select: none;
        white-space: nowrap;
    }
    th.sortable:hover { background-color: #e9ecef; }

    th.sortable .sort-icon { font-size: .7rem; vertical-align: middle; }
    th.sort-asc  .sort-icon::before { content: "\f235"; } /* bi-chevron-up   */
    th.sort-desc .sort-icon::before { content: "\f229"; } /* bi-chevron-down */
    th.sort-asc  .sort-icon,
    th.sort-desc .sort-icon { color: #0d6efd !important; }
</style>
@endpush

{{-- ===================== ADD LEAD MODAL ===================== --}}
<div class="modal fade" id="addLeadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add Lead</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="{{ route('leads.store') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Platform</label>
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
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" class="form-select">
                                <option value="new">New</option>
                                <option value="sent">Sent</option>
                                <option value="failed">Failed</option>
                                <option value="replied">Replied</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Company Name <span class="text-danger">*</span></label>
                            <input type="text" name="company_name" class="form-control" placeholder="e.g. Acme Ltd" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Website <span class="text-danger">*</span></label>
                            <input type="url" name="website" class="form-control" placeholder="https://example.com" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">LinkedIn URL</label>
                            <input type="url" name="linkedin" class="form-control" placeholder="https://linkedin.com/company/...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" name="email" class="form-control" placeholder="contact@example.com">
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add Lead</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
// ── Per-page selector ─────────────────────────────────────────
const perPageEl = document.getElementById('perPageSelect');
if (perPageEl) {
    perPageEl.addEventListener('change', function () {
        const url = new URL(window.location.href);
        url.searchParams.set('per_page', this.value);
        url.searchParams.delete('page'); // reset to page 1
        window.location.href = url.toString();
    });
}

// ── Column sorting ───────────────────────────────────────────
(function () {
    // col index mapping: th data-col maps to the Nth <td> in each row
    // checkbox col is index 0 (skip), then id=0, company=1 … found=5
    const CELL_OFFSET = 1; // skip the checkbox td

    let sortCol = null;
    let sortDir = 'asc';

    document.querySelectorAll('th.sortable').forEach(function (th) {
        th.addEventListener('click', function () {
            const col = parseInt(this.dataset.col);

            if (sortCol === col) {
                sortDir = sortDir === 'asc' ? 'desc' : 'asc';
            } else {
                sortCol = col;
                sortDir = 'asc';
            }

            // Update header icons
            document.querySelectorAll('th.sortable').forEach(function (h) {
                h.classList.remove('sort-asc', 'sort-desc');
                h.querySelector('.sort-icon').className = 'bi bi-chevron-expand sort-icon text-muted ms-1';
            });
            this.classList.add(sortDir === 'asc' ? 'sort-asc' : 'sort-desc');

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
    });
}());

// ── Spinner on search submit ──────────────────────────────────
document.getElementById('searchForm').addEventListener('submit', function () {
    document.getElementById('spinner').classList.remove('d-none');
    document.getElementById('btnIcon').classList.add('d-none');
    document.getElementById('findBtn').disabled = true;
});

// ── Live table filter (keyup) ─────────────────────────────────
document.getElementById('tableFilter').addEventListener('keyup', function () {
    const term = this.value.toLowerCase().trim();
    document.querySelectorAll('#leadsTable tbody tr').forEach(function (row) {
        const haystack = row.getAttribute('data-search') || '';
        row.classList.toggle('d-none', term !== '' && !haystack.includes(term));
    });
});

// ── Select all checkbox ───────────────────────────────────────
document.getElementById('selectAll').addEventListener('change', function () {
    document.querySelectorAll('.row-check').forEach(cb => cb.checked = this.checked);
    updateBulkToolbar();
});

document.querySelectorAll('.row-check').forEach(cb => {
    cb.addEventListener('change', updateBulkToolbar);
});

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
        document.getElementById('selectAll').checked = false;
    }
}

// ── Load templates into compose dropdown ──────────────────────
let cachedTemplates = [];

fetch('/api/templates', { headers: { 'Accept': 'application/json' } })
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

// When a template is selected — populate subject + body
document.getElementById('compose_template').addEventListener('change', function () {
    const tpl = cachedTemplates.find(t => t.id == this.value);
    if (! tpl) return;
    document.getElementById('compose_subject').value = tpl.subject;
    // Strip HTML tags for the plain textarea body
    const div = document.createElement('div');
    div.innerHTML = tpl.body;
    document.getElementById('compose_body').value = div.innerText || div.textContent;
});

// ── Compose Modal (Gmail-style) ───────────────────────────────
document.querySelectorAll('.btn-compose').forEach(function (btn) {
    btn.addEventListener('click', function () {
        const id   = this.dataset.id;
        const to   = this.dataset.to;
        const name = this.dataset.name;

        document.getElementById('composeModalTitle').textContent = 'New Message — ' + name;
        document.getElementById('compose_to').value              = to;
        document.getElementById('compose_subject').value         = '';
        document.getElementById('compose_body').value            = '';
        document.getElementById('compose_template').value        = '';
        document.getElementById('composeForm').action            = '/send-email/' + id;

        new bootstrap.Modal(document.getElementById('composeModal')).show();
        // Focus subject so user can start typing immediately
        document.getElementById('composeModal').addEventListener('shown.bs.modal', function handler() {
            document.getElementById('compose_subject').focus();
            this.removeEventListener('shown.bs.modal', handler);
        });
    });
});

// ── View Modal ────────────────────────────────────────────────
document.querySelectorAll('.btn-view').forEach(function (btn) {
    btn.addEventListener('click', function () {
        const id    = this.dataset.id;
        const modal = new bootstrap.Modal(document.getElementById('viewModal'));
        document.getElementById('viewModalBody').innerHTML =
            '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
        modal.show();

        fetch('/leads/' + id, { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(function (lead) {
                const statusColors = { new: 'secondary', sent: 'primary', failed: 'danger', replied: 'success' };
                const color        = statusColors[lead.status] || 'secondary';
                const platformName = lead.platform ? lead.platform.name : '—';
                document.getElementById('viewModalBody').innerHTML = `
                    <div class="row g-3">
                        <div class="col-md-6">
                            <p class="text-muted small mb-1">Platform</p>
                            <p class="fw-semibold">${platformName}</p>
                        </div>
                        <div class="col-md-6">
                            <p class="text-muted small mb-1">Status</p>
                            <span class="badge bg-${color} text-white">${lead.status.charAt(0).toUpperCase() + lead.status.slice(1)}</span>
                        </div>
                        <div class="col-md-6">
                            <p class="text-muted small mb-1">Company Name</p>
                            <p class="fw-semibold">${lead.company_name}</p>
                        </div>
                        <div class="col-md-6">
                            <p class="text-muted small mb-1">Website</p>
                            <a href="${lead.website}" target="_blank" class="text-decoration-none">
                                <i class="bi bi-box-arrow-up-right me-1"></i>${lead.website}
                            </a>
                        </div>
                        <div class="col-md-6">
                            <p class="text-muted small mb-1">LinkedIn</p>
                            ${lead.linkedin
                                ? `<a href="${lead.linkedin}" target="_blank" class="text-decoration-none">${lead.linkedin}</a>`
                                : '<span class="text-muted fst-italic">—</span>'}
                        </div>
                        <div class="col-md-6">
                            <p class="text-muted small mb-1">Email</p>
                            ${lead.email
                                ? `<a href="mailto:${lead.email}" class="text-decoration-none">${lead.email}</a>`
                                : '<span class="text-muted fst-italic">Not found</span>'}
                        </div>
                        <div class="col-md-6">
                            <p class="text-muted small mb-1">Created</p>
                            <p>${new Date(lead.created_at).toLocaleString()}</p>
                        </div>
                    </div>`;
            });
    });
});

// ── Edit Modal ────────────────────────────────────────────────
document.querySelectorAll('.btn-edit').forEach(function (btn) {
    btn.addEventListener('click', function () {
        const id    = this.dataset.id;
        const modal = new bootstrap.Modal(document.getElementById('editModal'));

        fetch('/leads/' + id + '/edit', { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(function (lead) {
                document.getElementById('editForm').action = '/leads/' + lead.id;
                document.getElementById('edit_company_name').value = lead.company_name || '';
                document.getElementById('edit_website').value      = lead.website      || '';
                document.getElementById('edit_email').value        = lead.email        || '';
                document.getElementById('edit_linkedin').value     = lead.linkedin     || '';
                document.getElementById('edit_status').value       = lead.status       || 'new';
                document.getElementById('edit_platform_id').value  = lead.platform_id  || '';
                modal.show();
            });
    });
});
</script>
@endpush
