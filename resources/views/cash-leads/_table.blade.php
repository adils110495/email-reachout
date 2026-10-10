{{--
    Cash leads table + empty state. Swapped in via AJAX on every filter, search
    or page change by assets/js/ajax-filters.js, so it must stay self-contained:
    no <script> here; row actions are bound by delegation in index.blade.php.
--}}
<div class="ajax-content" data-total="{{ $cashLeads->total() }}">
@if($cashLeads->isEmpty())
    <div class="card-body">
        <div class="empty-state">
            <i class="bi bi-cash-coin empty-state-icon"></i>
            @if($activeCategory || $activeSource || $search)
                No deals match. <a href="{{ route('cash-leads.index') }}">Clear the filters</a>.
            @else
                No deals yet. Mark a lead as a Deal, or add one with the button above.
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
                        <th class="mw-200">Company</th>
                        <th>Category</th>
                        <th class="mw-150">Contact</th>
                        <th>Source</th>
                        <th class="mw-200">Notes</th>
                        <th>Added</th>
                        <th style="width:80px" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($cashLeads as $cash)
                        <tr>
                            <td>{{ $cashLeads->firstItem() + $loop->index }}</td>
                            <td>
                                <h6 class="mb-0">{{ $cash->company_name }}</h6>
                                @if($cash->website)
                                    <small class="text-muted">{{ $cash->website }}</small>
                                @endif
                            </td>
                            <td>{{ $cash->category?->name ?? '—' }}</td>
                            <td>
                                @if($cash->email)<div>{{ $cash->email }}</div>@endif
                                @if($cash->phone)<div><a href="tel:{{ $cash->phone }}">{{ $cash->phone }}</a></div>@endif
                                @if(! $cash->email && ! $cash->phone)—@endif
                            </td>
                            <td><span class="badge badge-success light">{{ $sources[$cash->source] ?? $cash->source }}</span></td>
                            <td><span class="fs-13">{{ \Illuminate\Support\Str::limit($cash->notes, 80) ?: '—' }}</span></td>
                            <td>{{ $cash->created_at->format('d M Y') }}</td>
                            <td class="text-center">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-light btn-square"
                                            data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <button type="button" class="dropdown-item btn-edit-cash"
                                                    data-id="{{ $cash->id }}"
                                                    data-company="{{ $cash->company_name }}"
                                                    data-category="{{ $cash->category_id }}"
                                                    data-email="{{ $cash->email }}"
                                                    data-phone="{{ $cash->phone }}"
                                                    data-website="{{ $cash->website }}"
                                                    data-address="{{ $cash->address }}"
                                                    data-notes="{{ $cash->notes }}">
                                                <i class="bi bi-pencil me-2 text-warning"></i>Edit
                                            </button>
                                        </li>
                                        <li>
                                            <form method="POST" action="{{ route('cash-leads.destroy', $cash->id) }}"
                                                  onsubmit="return confirm('Remove \'{{ addslashes($cash->company_name) }}\' from Deals?')">
                                                @csrf @method('DELETE')
                                                <input type="hidden" name="_redirect_back" value="{{ request()->getQueryString() ? '?'.request()->getQueryString() : '' }}">
                                                <button type="submit" class="dropdown-item text-danger">
                                                    <i class="bi bi-trash me-2"></i>Remove
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

    @if($cashLeads->hasPages())
        <div class="card-footer">{{ $cashLeads->links() }}</div>
    @endif
@endif
</div>
