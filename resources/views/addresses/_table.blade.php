{{--
    Addresses table + empty state. Swapped in via AJAX on every status-filter
    change by assets/js/ajax-filters.js, so it must stay self-contained: no
    <script> here, and row actions are bound by delegation in index.blade.php.
--}}
<div class="ajax-content">
@if($addresses->isEmpty())
    <div class="card-body">
        <div class="empty-state">
            <i class="bi bi-geo-alt empty-state-icon"></i>
            @if($activeStatus)
                No <strong>{{ $statusOptions[$activeStatus] ?? $activeStatus }}</strong> addresses.
                <a href="{{ route('addresses.index') }}">Clear the filter</a>.
            @else
                No addresses yet. Add one to get started.
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
                        <th class="mw-200">Address</th>
                        <th class="mw-150">Email</th>
                        <th class="mw-120 d-none d-md-table-cell">Phone</th>
                        <th class="mw-120 d-none d-xl-table-cell">Alt. Phone</th>
                        <th class="mw-150 d-none d-lg-table-cell">Website</th>
                        <th style="width:120px">Status</th>
                        <th style="width:80px" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($addresses as $i => $addr)
                    <tr data-search="{{ strtolower(implode(' ', [$addr->address, $addr->email, $addr->phone, $addr->alternate_phone, $addr->website, $addr->status])) }}">
                        <td class="d-none d-md-table-cell"><span>{{ $i + 1 }}</span></td>
                        <td><span class="cell-wrap">{{ $addr->address }}</span><div class="d-md-none fs-13 text-muted">{{ $addr->phone }}</div></td>
                        <td>
                            <a href="mailto:{{ $addr->email }}" class="text-primary">{{ $addr->email }}</a>
                        </td>
                        <td class="d-none d-md-table-cell"><span>{{ $addr->phone }}</span></td>
                        <td class="d-none d-xl-table-cell"><span>{{ $addr->alternate_phone ?: '—' }}</span></td>
                        <td class="d-none d-lg-table-cell">
                            @if($addr->website)
                                <a href="{{ $addr->website }}" target="_blank" rel="noopener"
                                   class="text-primary text-truncate d-inline-block" style="max-width:150px"
                                   title="{{ $addr->website }}">
                                    <i class="bi bi-box-arrow-up-right me-1"></i>{{ $addr->website }}
                                </a>
                            @else
                                <span>—</span>
                            @endif
                        </td>
                        <td>
                            @if($addr->status === 'active')
                                <span class="badge badge-success light">Active</span>
                            @else
                                <span class="badge badge-secondary light">Inactive</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light btn-square"
                                        data-bs-toggle="dropdown" data-bs-strategy="fixed" aria-expanded="false" aria-label="Actions">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <button class="dropdown-item btn-edit-address"
                                                data-id="{{ $addr->id }}"
                                                data-address="{{ $addr->address }}"
                                                data-email="{{ $addr->email }}"
                                                data-phone="{{ $addr->phone }}"
                                                data-alternate_phone="{{ $addr->alternate_phone }}"
                                                data-website="{{ $addr->website }}"
                                                data-status="{{ $addr->status }}">
                                            <i class="bi bi-pencil me-2 text-warning"></i>Edit
                                        </button>
                                    </li>
                                    <li>
                                        <form method="POST" action="{{ route('addresses.destroy', $addr->id) }}"
                                              onsubmit="return confirm('Delete this address?')">
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
@endif
</div>
