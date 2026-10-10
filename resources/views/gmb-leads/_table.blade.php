{{--
    GMB leads table + empty state. Swapped in via AJAX on every filter, search
    or page change by assets/js/ajax-filters.js, so it must stay self-contained:
    no <script> here.
--}}
<div class="ajax-content" data-total="{{ $gmbLeads->total() }}">
@if($gmbLeads->isEmpty())
    <div class="card-body">
        <div class="empty-state">
            <i class="bi bi-geo-alt empty-state-icon"></i>
            @if($activeCategory || $search || $activeRating || $reviewsMin !== null || $reviewsMax !== null)
                No GMB leads match. <a href="{{ route('gmb-leads.index') }}">Clear the filters</a>.
            @else
                No GMB leads yet. Search above to find businesses without a website.
            @endif
        </div>
    </div>
@else
    <div class="card-body table-card-body px-0 pt-0 pb-2">
        <div class="table-responsive">
            <table class="table">
                <thead class="table-light">
                    <tr>
                        <th style="width:60px">#</th>
                        <th class="mw-200">Business</th>
                        <th>Category</th>
                        <th>Phone</th>
                        <th class="mw-200">Address</th>
                        <th>Rating</th>
                        <th style="width:80px" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($gmbLeads as $lead)
                        <tr>
                            <td>{{ $gmbLeads->firstItem() + $loop->index }}</td>
                            <td>
                                <h6 class="mb-0">{{ $lead->name }}</h6>
                                <small class="text-muted">{{ $lead->type }}</small>
                            </td>
                            <td>{{ $lead->category?->name ?? '—' }}</td>
                            <td>
                                @if($lead->phone)
                                    <a href="tel:{{ $lead->phone }}">{{ $lead->phone }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $lead->address ?? '—' }}</td>
                            <td>
                                @if($lead->rating)
                                    <i class="bi bi-star-fill text-warning me-1"></i>{{ $lead->rating }}
                                    <small class="text-muted">({{ $lead->reviews ?? 0 }})</small>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-light btn-square"
                                            data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        @if($lead->maps_url)
                                            <li>
                                                <a class="dropdown-item" href="{{ $lead->maps_url }}" target="_blank" rel="noopener">
                                                    <i class="bi bi-geo-alt me-2 text-primary"></i>View on Maps
                                                </a>
                                            </li>
                                        @endif
                                        <li>
                                            <form method="POST" action="{{ route('cash-leads.from-gmb', $lead->id) }}">
                                                @csrf
                                                <button type="submit" class="dropdown-item">
                                                    <i class="bi bi-cash-coin me-2 text-success"></i>Mark as Deal
                                                </button>
                                            </form>
                                        </li>
                                        <li>
                                            <form method="POST" action="{{ route('gmb-leads.destroy', $lead->id) }}"
                                                  onsubmit="return confirm('Delete \'{{ addslashes($lead->name) }}\'?')">
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

    @if($gmbLeads->hasPages())
        <div class="card-footer">{{ $gmbLeads->links() }}</div>
    @endif
@endif
</div>
