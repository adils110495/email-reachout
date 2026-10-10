{{--
    Leads table + footer. Swapped in via AJAX on every filter / per-page /
    pagination change, so it must stay self-contained: no <script> here, and
    all row interactions are bound by delegation in leads/index.blade.php.
--}}
<div id="leadsContent" data-total="{{ $activeCategory ? $leads->total() : 0 }}">
@if(!$activeCategory)
    <div class="card-body">
        <div class="empty-state">
            <i class="bi bi-tag empty-state-icon"></i>
            <p class="mb-1 fw-semibold">Select a category to view leads</p>
            <p class="fs-13 mb-0">Use the <strong>Category</strong> filter above to load leads for a specific category.</p>
        </div>
    </div>
@elseif($leads->isEmpty())
    <div class="card-body">
        <div class="empty-state">
            <i class="bi bi-inbox empty-state-icon"></i>
            No leads found for <strong>{{ $activeCatObj->name ?? '' }}</strong>.
        </div>
    </div>
@else
    <div class="card-body table-card-body px-0 pt-0 pb-2">
        <div class="table-responsive">
            <table class="table" id="leadsTable">
                <thead class="table-light">
                    <tr>
                        <th scope="col" style="width:40px">
                            <input type="checkbox" class="form-check-input" id="selectAll" title="Select all">
                        </th>
                        <th scope="col" class="sortable d-none d-md-table-cell" data-col="0">S.No
                            <i class="bi bi-chevron-expand sort-icon text-muted ms-1"></i>
                        </th>
                        <th scope="col" class="sortable mw-150" data-col="1">Company
                            <i class="bi bi-chevron-expand sort-icon text-muted ms-1"></i>
                        </th>
                        <th scope="col" class="sortable mw-150" data-col="2">Website
                            <i class="bi bi-chevron-expand sort-icon text-muted ms-1"></i>
                        </th>
                        <th scope="col" class="sortable mw-150" data-col="3">Email
                            <i class="bi bi-chevron-expand sort-icon text-muted ms-1"></i>
                        </th>
                        <th scope="col" class="sortable mw-100" data-col="4">Status
                            <i class="bi bi-chevron-expand sort-icon text-muted ms-1"></i>
                        </th>
                        <th scope="col" class="sortable mw-100 d-none d-lg-table-cell" data-col="5">Platform
                            <i class="bi bi-chevron-expand sort-icon text-muted ms-1"></i>
                        </th>
                        <th scope="col" class="sortable mw-100 d-none d-lg-table-cell" data-col="6">Found
                            <i class="bi bi-chevron-expand sort-icon text-muted ms-1"></i>
                        </th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($leads as $lead)
                    <tr data-id="{{ $lead->id }}"
                        data-search="{{ strtolower($lead->company_name . ' ' . implode(' ', $lead->email_list) . ' ' . $lead->website . ' ' . $lead->status) }}">

                        {{-- Checkbox --}}
                        <td>
                            <input type="checkbox" class="form-check-input row-check" value="{{ $lead->id }}">
                        </td>

                        <td data-val="{{ $lead->id }}" class="d-none d-md-table-cell"><span>{{ ($leads->currentPage() - 1) * $leads->perPage() + $loop->iteration }}</span></td>

                        <td data-val="{{ strtolower($lead->company_name) }}">
                            <h6 class="mb-0 cell-wrap">{{ $lead->company_name }}</h6>
                            {{-- Platform and Found are hidden below lg - carry them here. --}}
                            <div class="d-lg-none fs-13 text-muted">
                                @if($lead->platform){{ $lead->platform->name }} · @endif
                                {{ $lead->created_at->diffForHumans() }}
                            </div>
                        </td>

                        <td data-val="{{ parse_url($lead->website, PHP_URL_HOST) }}" style="max-width:180px;">
                            <a href="{{ $lead->website }}" target="_blank" rel="noopener" class="text-primary d-block text-truncate" title="{{ $lead->website }}">
                                <i class="bi bi-box-arrow-up-right me-1"></i>
                                {{ parse_url($lead->website, PHP_URL_HOST) }}
                            </a>
                        </td>

                        <td data-val="{{ strtolower(implode(',', $lead->email_list)) }}">
                            @if($lead->email_list)
                                {{-- Every address, comma separated; the first is the one emails are sent to. --}}
                                @foreach($lead->email_list as $address)
                                    <a href="mailto:{{ $address }}" class="text-primary">{{ $address }}</a>@if(! $loop->last), @endif
                                @endforeach
                            @else
                                <span class="fst-italic">Not found</span>
                            @endif
                        </td>

                        <td data-val="{{ $lead->status }}">
                            @php
                                $badgeClass = match($lead->status) {
                                    'sent'    => 'badge-primary',
                                    'failed'  => 'badge-danger',
                                    'replied' => 'badge-success',
                                    default   => 'badge-secondary',
                                };
                            @endphp
                            <span class="badge {{ $badgeClass }} light">{{ ucfirst($lead->status) }}</span>
                        </td>

                        <td data-val="{{ strtolower($lead->platform?->name ?? '') }}" class="d-none d-lg-table-cell">
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
                            <span style="color:{{ $p['color'] }}">
                                <i class="bi {{ $p['icon'] }} me-1"></i>{{ $platformName }}
                            </span>
                        </td>

                        <td data-val="{{ $lead->created_at->timestamp }}" class="d-none d-lg-table-cell">
                            <span class="text-nowrap">{{ $lead->created_at->diffForHumans() }}</span>
                        </td>

                        {{-- 3-dot kebab menu --}}
                        <td class="text-end">
                            <div class="dropdown">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-light btn-square"
                                    data-bs-toggle="dropdown"
                                   
                                    aria-expanded="false"
                                    aria-label="Actions"
                                >
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>

                                <ul class="dropdown-menu dropdown-menu-end" style="min-width:180px;">

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

                                    {{-- Show Sent Email --}}
                                    @if($lead->status === 'sent')
                                        <li>
                                            <button type="button"
                                                class="dropdown-item btn-show-email"
                                                data-id="{{ $lead->id }}">
                                                <i class="bi bi-envelope-open me-2 text-success"></i>Show Email
                                            </button>
                                        </li>
                                    @endif

                                    {{-- Send Email / Retry → opens Gmail-style compose modal --}}
                                    @if($lead->email && in_array($lead->status, ['new', 'failed']))
                                        <li>
                                            <button type="button"
                                                class="dropdown-item btn-compose"
                                                data-id="{{ $lead->id }}"
                                                data-name="{{ addslashes($lead->company_name) }}"
                                                data-website="{{ $lead->website }}"
                                                data-to="{{ $lead->email }}"
                                                data-emails="{{ json_encode($lead->email_list) }}">
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

                                    {{-- Cash Lead: we expect this one to turn into a paying customer --}}
                                    <li>
                                        <form method="POST" action="{{ route('cash-leads.from-lead', $lead->id) }}">
                                            @csrf
                                            <button type="submit" class="dropdown-item">
                                                <i class="bi bi-cash-coin me-2 text-success"></i>Mark as Deal
                                            </button>
                                        </form>
                                    </li>

                                    {{-- Delete --}}
                                    <li>
                                        <form method="POST"
                                            action="{{ route('leads.destroy', $lead->id) }}"
                                            onsubmit="return confirm('Delete {{ addslashes($lead->company_name) }}?')">
                                            @csrf @method('DELETE')
                                            <input type="hidden" name="_redirect_back" value="{{ request()->getQueryString() ? '?'.request()->getQueryString() : '' }}">
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
    </div>

    {{-- Footer: per-page + info (left) | pagination (right) --}}
    <div class="card-footer py-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">

            {{-- LEFT: per-page selector + result info --}}
            <div class="d-flex align-items-center gap-3 flex-wrap">

                {{-- Per-page selector --}}
                <div class="d-flex align-items-center gap-2">
                    <label class="form-label mb-0 text-nowrap">Rows per page:</label>
                    <select id="perPageSelect" class="form-select form-select-sm per-page-select">
                        @foreach([10, 25, 50, 100] as $size)
                            <option value="{{ $size }}" {{ request('per_page', 25) == $size ? 'selected' : '' }}>
                                {{ $size }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Showing X to Y of Z --}}
                <span class="fs-13">
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
