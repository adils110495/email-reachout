{{--
    Finder results table + footer. Swapped in via AJAX on every filter, search,
    per-page and pagination change by assets/js/ajax-filters.js, so it must stay
    self-contained: no <script> here, and one single .ajax-content root.
--}}
<div class="ajax-content" data-total="{{ $results->total() }}">
@if($results->isEmpty())
    <div class="card-body">
        <div class="empty-state">
            <i class="bi bi-inbox empty-state-icon"></i>
            @if(array_filter($filters))
                <p class="mb-1 fw-semibold">No results match these filters</p>
                <p class="fs-13 mb-0">
                    <a href="{{ route('finder.index') }}">Clear the filters</a> to see everything found so far.
                </p>
            @else
                <p class="mb-1 fw-semibold">Nothing found yet</p>
                <p class="fs-13 mb-0">Run a domain search above — every address it turns up is kept here.</p>
            @endif
        </div>
    </div>
@else
    <div class="card-body table-card-body px-0 pt-0 pb-2">
        <div class="table-responsive">
            <table class="table">
                <thead class="table-light">
                    <tr>
                        {{-- Secondary columns drop out on narrow screens; their values
                             are carried into the Email cell below so nothing is lost. --}}
                        <th style="width:60px" class="d-none d-md-table-cell">#</th>
                        <th class="mw-150">Email</th>
                        <th class="mw-150 d-none d-lg-table-cell">Company</th>
                        <th style="width:120px">Result</th>
                        <th style="width:140px" class="d-none d-md-table-cell">Confidence</th>
                        <th style="width:120px" class="d-none d-xl-table-cell">Source</th>
                        <th style="width:110px">Saved</th>
                        <th style="width:110px" class="d-none d-lg-table-cell">Found</th>
                        <th style="width:70px" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($results as $row)
                        <tr>
                            <td class="d-none d-md-table-cell">
                                <span>{{ ($results->currentPage() - 1) * $results->perPage() + $loop->iteration }}</span>
                            </td>

                            <td class="cell-wrap mw-220">
                                <h6 class="mb-0 cell-wrap">
                                    <a href="mailto:{{ $row->email }}">{{ $row->email }}</a>
                                </h6>
                                <span class="fs-13 text-muted">{{ $row->domain }}</span>

                                {{-- Carries the columns hidden at this width. --}}
                                <div class="d-lg-none fs-13 text-muted cell-wrap">
                                    @if($row->company){{ $row->company }} · @endif
                                    {{ $row->created_at?->format('j M Y') ?? '—' }}
                                </div>
                                <div class="d-md-none fs-13 text-muted">{{ $row->score }}% confidence</div>
                            </td>

                            <td class="cell-wrap d-none d-lg-table-cell">
                                @if($row->company)
                                    {{ $row->company }}
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                                @if($row->person)
                                    <div class="fs-13 text-muted">{{ $row->person }}</div>
                                @endif
                            </td>

                            <td>
                                <span class="badge badge-{{ $row->status_colour }} light">
                                    {{ ucfirst($row->status) }}
                                </span>
                                @if($row->guessed)
                                    {{-- Never let a generated address read as a confirmed one. --}}
                                    <div class="mt-1">
                                        <span class="badge badge-secondary light" title="Generated from a name pattern, not published on the site">
                                            guessed
                                        </span>
                                    </div>
                                @endif
                            </td>

                            <td class="d-none d-md-table-cell">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="score-meter flex-grow-1">
                                        <span class="bg-{{ $row->status_colour }}" style="width: {{ $row->score }}%"></span>
                                    </div>
                                    <span class="fs-13 text-muted">{{ $row->score }}%</span>
                                </div>
                            </td>

                            <td class="d-none d-xl-table-cell">
                                @if($row->source === \App\Models\FinderResult::SOURCE_WEBSITE)
                                    <span class="badge badge-info light" title="Published on the company website">Website</span>
                                @else
                                    <span class="badge badge-dark light" title="Generated pattern: {{ $row->pattern }}">
                                        {{ $row->pattern ?: 'Pattern' }}
                                    </span>
                                @endif
                            </td>

                            <td>
                                @if($row->isSaved())
                                    <span class="badge badge-success light">
                                        <i class="bi bi-check-lg me-1"></i>Lead
                                    </span>
                                @else
                                    <button type="button" class="btn btn-sm btn-primary js-save-row"
                                            data-email="{{ $row->email }}"
                                            data-domain="{{ $row->domain }}"
                                            data-company="{{ $row->company }}">
                                        <i class="bi bi-plus-lg me-1"></i>Save
                                    </button>
                                @endif
                            </td>

                            <td class="fs-13 text-muted text-nowrap d-none d-lg-table-cell">
                                {{ $row->created_at?->format('j M Y') ?? '—' }}
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
                                            <a class="dropdown-item" href="{{ route('verifier.index', ['q' => $row->email]) }}">
                                                <i class="bi bi-patch-check me-2 text-primary"></i>Verify this address
                                            </a>
                                        </li>
                                        @if($row->lead_id)
                                            <li>
                                                <a class="dropdown-item"
                                                   href="{{ route('leads.index', ['category' => $row->lead?->category_id]) }}">
                                                    <i class="bi bi-people me-2 text-info"></i>Open in Leads
                                                </a>
                                            </li>
                                        @endif
                                        <li>
                                            <a class="dropdown-item" href="https://{{ $row->domain }}"
                                               target="_blank" rel="noopener noreferrer">
                                                <i class="bi bi-box-arrow-up-right me-2 text-secondary"></i>Visit website
                                            </a>
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
                    <label class="form-label mb-0 text-nowrap" for="finderPerPage">Rows per page:</label>
                    {{-- data-param is picked up by the delegated handler in
                         ajax-filters.js, since this select is inside the region
                         that gets replaced. --}}
                    <select id="finderPerPage" class="form-select form-select-sm per-page-select" data-param="per_page">
                        @foreach([10, 25, 50, 100] as $size)
                            <option value="{{ $size }}" {{ (int) request('per_page', 25) === $size ? 'selected' : '' }}>
                                {{ $size }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <span class="fs-13">
                    Showing <strong>{{ $results->firstItem() }}</strong> - <strong>{{ $results->lastItem() }}</strong>
                    of <strong>{{ number_format($results->total()) }}</strong> results
                </span>
            </div>

            @if($results->hasPages())
                <div>{{ $results->links() }}</div>
            @endif

        </div>
    </div>
@endif
</div>
