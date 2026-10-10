{{--
    Bulk runs table + footer. Swapped in via AJAX on every filter, per-page and
    pagination change by assets/js/ajax-filters.js, so it must stay
    self-contained: no <script> here, one single .ajax-content root.
--}}
<div class="ajax-content">
@if($bulks->isEmpty())
    <div class="card-body">
        <div class="empty-state">
            <i class="bi bi-file-earmark-arrow-up empty-state-icon"></i>
            @if(array_filter($filters))
                <p class="mb-1 fw-semibold">No runs match these filters</p>
                <p class="fs-13 mb-0"><a href="{{ route('bulks.index') }}">Clear the filters</a> to see everything.</p>
            @else
                <p class="mb-1 fw-semibold">No bulk runs yet</p>
                <p class="fs-13 mb-0">Upload a CSV of email addresses to verify, or of domains to find addresses for.</p>
            @endif
        </div>
    </div>
@else
    @php
        $redirectBack = request()->getQueryString() ? '?'.request()->getQueryString() : '';
    @endphp

    <div class="card-body table-card-body px-0 pt-0 pb-2">
        <div class="table-responsive">
            <table class="table">
                <thead class="table-light">
                    <tr>
                        {{-- Secondary columns drop out on narrow screens; their values
                             are carried into the Run cell below so nothing is lost. --}}
                        <th style="width:60px" class="d-none d-md-table-cell">#</th>
                        <th class="mw-150">Run</th>
                        <th style="width:130px" class="d-none d-lg-table-cell">Type</th>
                        <th style="width:120px">Status</th>
                        <th style="width:220px">Progress</th>
                        <th style="width:110px" class="text-end d-none d-lg-table-cell">Success</th>
                        <th style="width:110px" class="text-end d-none d-lg-table-cell">Failed</th>
                        <th style="width:130px" class="d-none d-xl-table-cell">Started</th>
                        <th style="width:70px" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($bulks as $bulk)
                        {{-- data-bulk-running marks a row the index page polls
                             for live progress (see index.blade.php). --}}
                        <tr data-search="{{ strtolower($bulk->name.' '.$bulk->original_filename.' '.$bulk->type.' '.$bulk->status) }}"
                            @if($bulk->isRunning()) data-bulk-running="{{ $bulk->id }}" @endif>

                            <td class="d-none d-md-table-cell">
                                <span>{{ ($bulks->currentPage() - 1) * $bulks->perPage() + $loop->iteration }}</span>
                            </td>

                            <td>
                                <h6 class="mb-0 cell-wrap">
                                    <a href="{{ route('bulks.show', $bulk->id) }}">{{ $bulk->name }}</a>
                                </h6>
                                @if($bulk->original_filename)
                                    <span class="fs-13 text-muted cell-wrap">{{ $bulk->original_filename }}</span>
                                @endif

                                {{-- Carries the columns hidden at this width. The counts
                                     carry their own data-cell markers so the live poller
                                     updates them too - the wide columns it normally
                                     writes to are display:none here. --}}
                                <div class="d-lg-none fs-13 text-muted">
                                    {{ match($bulk->type) { 'find' => 'Finder', 'verify' => 'Verify', default => $bulk->type_label } }}
                                    · <span data-cell="successful-sm">{{ number_format($bulk->successful_records) }}</span> ok
                                    · <span data-cell="failed-sm">{{ number_format($bulk->failed_records) }}</span> failed
                                </div>
                            </td>

                            <td class="d-none d-lg-table-cell">
                                @if($bulk->type === 'find')
                                    <span class="badge badge-info light"><i class="bi bi-search me-1"></i>Finder</span>
                                @elseif($bulk->type !== 'verify')
                                    <span class="badge badge-success light"><i class="bi {{ $bulk->isImport() ? 'bi-person-plus' : 'bi-diagram-3' }} me-1"></i>{{ $bulk->type_label }}</span>
                                @else
                                    <span class="badge badge-primary light"><i class="bi bi-patch-check me-1"></i>Verify</span>
                                @endif
                            </td>

                            <td>
                                <span class="badge badge-{{ $bulk->status_colour }} light" data-cell="status">
                                    {{ ucfirst($bulk->status) }}
                                </span>
                            </td>

                            <td>
                                <div class="progress bulk-progress mb-1">
                                    <div class="progress-bar bg-{{ $bulk->status_colour }} {{ $bulk->isRunning() ? 'progress-bar-striped progress-bar-animated' : '' }}"
                                         role="progressbar" data-cell="bar"
                                         style="width: {{ $bulk->progress }}%"
                                         aria-valuenow="{{ $bulk->progress }}" aria-valuemin="0" aria-valuemax="100"
                                         aria-label="{{ $bulk->name }} progress"></div>
                                </div>
                                <span class="fs-13 text-muted" data-cell="counts">
                                    {{ number_format($bulk->processed_records) }} / {{ number_format($bulk->total_records) }}
                                    ({{ $bulk->progress }}%)
                                </span>
                            </td>

                            <td class="text-end text-success fw-medium d-none d-lg-table-cell" data-cell="successful">{{ number_format($bulk->successful_records) }}</td>
                            <td class="text-end text-danger fw-medium d-none d-lg-table-cell" data-cell="failed">{{ number_format($bulk->failed_records) }}</td>

                            <td class="fs-13 text-muted text-nowrap d-none d-xl-table-cell">
                                {{ ($bulk->started_at ?? $bulk->created_at)?->format('j M Y, H:i') ?? '—' }}
                            </td>

                            <td class="text-center">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-light btn-square"
                                            data-bs-toggle="dropdown" data-bs-strategy="fixed"
                                            aria-expanded="false" aria-label="Actions">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item" href="{{ route('bulks.show', $bulk->id) }}">
                                                <i class="bi bi-eye me-2 text-primary"></i>View results
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="{{ route('bulks.export', $bulk->id) }}">
                                                <i class="bi bi-download me-2 text-info"></i>Download CSV
                                            </a>
                                        </li>

                                        @if($bulk->isRunning())
                                            <li>
                                                <form method="POST" action="{{ route('bulks.cancel', $bulk->id) }}"
                                                      onsubmit="return confirm('Cancel this run? Results collected so far are kept.')">
                                                    @csrf
                                                    <button type="submit" class="dropdown-item text-warning">
                                                        <i class="bi bi-stop-circle me-2"></i>Cancel
                                                    </button>
                                                </form>
                                            </li>
                                        @elseif($bulk->failed_records > 0 || $bulk->status === 'failed' || $bulk->status === 'cancelled')
                                            <li>
                                                <form method="POST" action="{{ route('bulks.retry', $bulk->id) }}">
                                                    @csrf
                                                    <button type="submit" class="dropdown-item text-success">
                                                        <i class="bi bi-arrow-clockwise me-2"></i>Retry unfinished
                                                    </button>
                                                </form>
                                            </li>
                                        @endif

                                        <li>
                                            <form method="POST" action="{{ route('bulks.destroy', $bulk->id) }}"
                                                  onsubmit="return confirm('Delete &quot;{{ addslashes($bulk->name) }}&quot; and all of its results?')">
                                                @csrf @method('DELETE')
                                                <input type="hidden" name="_redirect_back" value="{{ $redirectBack }}">
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

            <div class="d-flex align-items-center gap-3 flex-wrap">
                <div class="d-flex align-items-center gap-2">
                    <label class="form-label mb-0 text-nowrap" for="bulksPerPage">Rows per page:</label>
                    <select id="bulksPerPage" class="form-select form-select-sm per-page-select" data-param="per_page">
                        @foreach([10, 25, 50, 100] as $size)
                            <option value="{{ $size }}" {{ (int) request('per_page', 25) === $size ? 'selected' : '' }}>
                                {{ $size }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <span class="fs-13">
                    Showing <strong>{{ $bulks->firstItem() }}</strong> - <strong>{{ $bulks->lastItem() }}</strong>
                    of <strong>{{ number_format($bulks->total()) }}</strong> results
                </span>
            </div>

            @if($bulks->hasPages())
                <div>{{ $bulks->links() }}</div>
            @endif

        </div>
    </div>
@endif
</div>
