{{--
    One bulk run's per-record results. Swapped in via AJAX on every filter,
    search, per-page and pagination change by assets/js/ajax-filters.js, so it
    must stay self-contained: no <script> here, one single .ajax-content root.
--}}
@php $isFind = $bulk->type === 'find'; @endphp

<div class="ajax-content">
@if($items->isEmpty())
    <div class="card-body">
        <div class="empty-state">
            <i class="bi bi-inbox empty-state-icon"></i>
            @if(array_filter($filters))
                <p class="mb-1 fw-semibold">No records match these filters</p>
                <p class="fs-13 mb-0">
                    <a href="{{ route('bulks.show', $bulk->id) }}">Clear the filters</a> to see every record.
                </p>
            @elseif($bulk->isRunning())
                <p class="mb-1 fw-semibold">Waiting for the first results</p>
                <p class="fs-13 mb-0">Records appear here as the queue works through them.</p>
            @else
                <p class="mb-1 fw-semibold">This run has no records</p>
                <p class="fs-13 mb-0">Nothing usable was found in the uploaded file.</p>
            @endif
        </div>
    </div>
@else
    <div class="card-body table-card-body px-0 pt-0 pb-2">
        <div class="table-responsive">
            <table class="table">
                <thead class="table-light">
                    <tr>
                        <th style="width:60px" class="d-none d-md-table-cell">#</th>
                        <th class="mw-150">{{ $isFind ? 'Domain' : 'Email' }}</th>
                        @if($isFind)
                            <th class="mw-150">Email found</th>
                        @endif
                        <th style="width:120px">Result</th>
                        <th style="width:150px" class="d-none d-md-table-cell">Confidence</th>
                        <th class="mw-150 d-none d-lg-table-cell">Notes</th>
                        <th style="width:70px" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                        <tr>
                            <td class="d-none d-md-table-cell">
                                <span>{{ ($items->currentPage() - 1) * $items->perPage() + $loop->iteration }}</span>
                            </td>

                            <td class="cell-wrap mw-220">
                                <h6 class="mb-0 cell-wrap">{{ $item->input }}</h6>
                                @if($item->extra)
                                    <span class="fs-13 text-muted">{{ $item->extra }}</span>
                                @endif

                                {{-- Carries the columns hidden at this width. --}}
                                @if($item->message)
                                    <div class="d-lg-none fs-13 text-muted cell-wrap">{{ $item->message }}</div>
                                @endif
                                @if($item->score !== null)
                                    <div class="d-md-none fs-13 text-muted">{{ $item->score }}% confidence</div>
                                @endif
                            </td>

                            @if($isFind)
                                <td class="cell-wrap mw-220">
                                    @if($item->result_value)
                                        <a href="mailto:{{ $item->result_value }}">{{ $item->result_value }}</a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            @endif

                            <td>
                                @if($item->status === 'pending')
                                    <span class="badge badge-info light">
                                        <i class="bi bi-hourglass me-1"></i>Queued
                                    </span>
                                @elseif($item->status === 'processing')
                                    <span class="badge badge-primary light">
                                        <span class="spinner-border spinner-border-sm me-1" style="width:.6rem;height:.6rem"></span>Running
                                    </span>
                                @else
                                    <span class="badge badge-{{ $item->result_colour }} light">
                                        {{ str_replace('_', ' ', ucfirst($item->result_status ?? 'unknown')) }}
                                    </span>
                                @endif
                            </td>

                            <td class="d-none d-md-table-cell">
                                @if($item->score !== null)
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="score-meter flex-grow-1">
                                            <span class="bg-{{ $item->result_colour }}" style="width: {{ $item->score }}%"></span>
                                        </div>
                                        <span class="fs-13 text-muted">{{ $item->score }}%</span>
                                    </div>
                                @else
                                    <span class="fs-13 text-muted">—</span>
                                @endif
                            </td>

                            <td class="fs-13 cell-wrap d-none d-lg-table-cell">{{ $item->message ?: '—' }}</td>

                            <td class="text-center">
                                @php
                                    // The address worth acting on: the one that was
                                    // verified, or the one the finder discovered.
                                    $address = $isFind ? $item->result_value : $item->input;
                                @endphp

                                @if($address)
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light btn-square"
                                                data-bs-toggle="dropdown" data-bs-strategy="fixed"
                                                aria-expanded="false" aria-label="Actions">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <a class="dropdown-item" href="{{ route('verifier.index', ['q' => $address]) }}">
                                                    <i class="bi bi-patch-check me-2 text-primary"></i>Verification history
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="{{ route('finder.index', ['q' => $address]) }}">
                                                    <i class="bi bi-search me-2 text-info"></i>Find in leads
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="mailto:{{ $address }}">
                                                    <i class="bi bi-envelope me-2 text-secondary"></i>Compose email
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
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
                    <label class="form-label mb-0 text-nowrap" for="itemsPerPage">Rows per page:</label>
                    <select id="itemsPerPage" class="form-select form-select-sm per-page-select" data-param="per_page">
                        @foreach([10, 25, 50, 100] as $size)
                            <option value="{{ $size }}" {{ (int) request('per_page', 25) === $size ? 'selected' : '' }}>
                                {{ $size }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <span class="fs-13">
                    Showing <strong>{{ $items->firstItem() }}</strong> - <strong>{{ $items->lastItem() }}</strong>
                    of <strong>{{ number_format($items->total()) }}</strong> results
                </span>
            </div>

            @if($items->hasPages())
                <div>{{ $items->links() }}</div>
            @endif

        </div>
    </div>
@endif
</div>
