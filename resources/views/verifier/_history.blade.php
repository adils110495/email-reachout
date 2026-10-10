{{--
    Verification history table + footer. Swapped in via AJAX on every filter,
    search, per-page and pagination change by assets/js/ajax-filters.js, so it
    must stay self-contained: no <script> here, one single .ajax-content root.
--}}
<div class="ajax-content">
@if($history->isEmpty())
    <div class="card-body">
        <div class="empty-state">
            <i class="bi bi-patch-question empty-state-icon"></i>
            @if(array_filter($filters))
                <p class="mb-1 fw-semibold">Nothing matches these filters</p>
                <p class="fs-13 mb-0"><a href="{{ route('verifier.index') }}">Clear the filters</a> to see everything.</p>
            @else
                <p class="mb-1 fw-semibold">No verifications yet</p>
                <p class="fs-13 mb-0">Check an address above, or upload a list from
                    <a href="{{ route('bulks.index') }}">Bulks</a>.</p>
            @endif
        </div>
    </div>
@else
    @php
        // Carried by each delete form so the row action returns to this filtered page.
        $redirectBack = request()->getQueryString() ? '?'.request()->getQueryString() : '';
    @endphp

    <div class="card-body table-card-body px-0 pt-0 pb-2">
        <div class="table-responsive">
            <table class="table">
                <thead class="table-light">
                    <tr>
                        {{-- Secondary columns drop out on narrow screens; their values
                             are carried into the Email cell below so nothing is lost. --}}
                        <th style="width:60px" class="d-none d-md-table-cell">#</th>
                        <th class="mw-150">Email</th>
                        <th style="width:120px">Result</th>
                        <th style="width:150px" class="d-none d-md-table-cell">Confidence</th>
                        <th class="mw-150 d-none d-xl-table-cell">Reason</th>
                        <th style="width:150px" class="d-none d-xl-table-cell">Signals</th>
                        <th style="width:110px" class="d-none d-lg-table-cell">Source</th>
                        <th style="width:130px" class="d-none d-lg-table-cell">Checked</th>
                        <th style="width:70px" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($history as $row)
                        <tr>
                            <td class="d-none d-md-table-cell">
                                <span>{{ ($history->currentPage() - 1) * $history->perPage() + $loop->iteration }}</span>
                            </td>

                            <td class="cell-wrap mw-220">
                                <h6 class="mb-0 cell-wrap">{{ $row->email }}</h6>
                                <span class="fs-13 text-muted">{{ $row->domain }}</span>

                                {{-- Carries the columns hidden at this width. --}}
                                <div class="d-lg-none fs-13 text-muted">
                                    {{ ucfirst($row->source) }} · {{ $row->created_at?->format('j M Y, H:i') ?? '—' }}
                                </div>
                                <div class="d-md-none fs-13 text-muted">{{ $row->score }}% confidence</div>
                            </td>

                            <td>
                                <span class="badge badge-{{ $row->status_colour }} light">
                                    {{ \App\Models\EmailVerification::STATUSES[$row->status] ?? ucfirst($row->status) }}
                                </span>
                            </td>

                            <td class="d-none d-md-table-cell">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="score-meter flex-grow-1">
                                        <span class="bg-{{ $row->status_colour }}" style="width: {{ $row->score }}%"></span>
                                    </div>
                                    <span class="fs-13 text-muted">{{ $row->score }}%</span>
                                </div>
                            </td>

                            <td class="fs-13 cell-wrap d-none d-xl-table-cell">{{ $row->reason }}</td>

                            <td class="d-none d-xl-table-cell">
                                {{-- Only the signals that fired, so a clean address
                                     shows nothing rather than a row of "no"s. --}}
                                <div class="d-flex flex-wrap gap-1">
                                    @if(! empty($row->checks['mx']))
                                        <span class="badge badge-success light" title="Domain publishes mail exchangers">MX</span>
                                    @else
                                        <span class="badge badge-danger light" title="No mail exchangers published">No MX</span>
                                    @endif

                                    @if(! empty($row->checks['disposable']))
                                        <span class="badge badge-warning light" title="Throwaway inbox provider">Disposable</span>
                                    @endif

                                    @if(! empty($row->checks['role']))
                                        <span class="badge badge-dark light" title="Shared mailbox, not an individual">Role</span>
                                    @endif

                                    @if(! empty($row->checks['free']))
                                        <span class="badge badge-secondary light" title="Free consumer provider">Free</span>
                                    @endif

                                    @if(! empty($row->checks['catch_all']))
                                        <span class="badge badge-warning light" title="Domain accepts every address">Catch-all</span>
                                    @endif
                                </div>
                            </td>

                            <td class="d-none d-lg-table-cell">
                                <span class="badge badge-primary light">{{ ucfirst($row->source) }}</span>
                            </td>

                            <td class="fs-13 text-muted text-nowrap d-none d-lg-table-cell">
                                {{ $row->created_at?->format('j M Y, H:i') ?? '—' }}
                            </td>

                            <td class="text-center">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-light btn-square"
                                            data-bs-toggle="dropdown"
                                            aria-expanded="false" aria-label="Actions">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        @if($row->lead_id)
                                            <li>
                                                <a class="dropdown-item"
                                                   href="{{ route('finder.index', ['q' => $row->email]) }}">
                                                    <i class="bi bi-search me-2 text-primary"></i>View lead
                                                </a>
                                            </li>
                                        @endif
                                        @if($row->bulk_id)
                                            <li>
                                                <a class="dropdown-item" href="{{ route('bulks.show', $row->bulk_id) }}">
                                                    <i class="bi bi-stack me-2 text-info"></i>Open bulk run
                                                </a>
                                            </li>
                                        @endif
                                        <li>
                                            <form method="POST" action="{{ route('verifier.destroy', $row->id) }}"
                                                  onsubmit="return confirm('Delete this verification record?')">
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
                    <label class="form-label mb-0 text-nowrap" for="historyPerPage">Rows per page:</label>
                    <select id="historyPerPage" class="form-select form-select-sm per-page-select" data-param="per_page">
                        @foreach([10, 25, 50, 100] as $size)
                            <option value="{{ $size }}" {{ (int) request('per_page', 25) === $size ? 'selected' : '' }}>
                                {{ $size }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <span class="fs-13">
                    Showing <strong>{{ $history->firstItem() }}</strong> - <strong>{{ $history->lastItem() }}</strong>
                    of <strong>{{ number_format($history->total()) }}</strong> results
                </span>
            </div>

            @if($history->hasPages())
                <div>{{ $history->links() }}</div>
            @endif

        </div>
    </div>
@endif
</div>
