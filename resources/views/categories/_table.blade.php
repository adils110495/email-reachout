{{--
    Categories table + empty state. Swapped in via AJAX on every status-filter
    change by assets/js/ajax-filters.js, so it must stay self-contained: no
    <script> here, and row actions are bound by delegation in index.blade.php.
--}}
<div class="ajax-content">
@if($categories->isEmpty())
    <div class="card-body">
        <div class="empty-state">
            <i class="bi bi-tag empty-state-icon"></i>
            @if($activeStatus)
                No <strong>{{ $statusOptions[$activeStatus] ?? $activeStatus }}</strong> categories.
                <a href="{{ route('categories.index') }}">Clear the filter</a>.
            @else
                No categories yet. Add one to get started.
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
                        <th class="mw-150">Name</th>
                        <th style="width:130px">Status</th>
                        <th style="width:80px" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($categories as $i => $category)
                    <tr data-search="{{ strtolower($category->name . ' ' . $category->status) }}">
                        <td><span>{{ $i + 1 }}</span></td>
                        <td><h6 class="mb-0">{{ $category->name }}</h6></td>
                        <td>
                            @if($category->status === 'active')
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
                                        <button class="dropdown-item btn-edit-category"
                                                data-id="{{ $category->id }}"
                                                data-name="{{ $category->name }}"
                                                data-status="{{ $category->status }}">
                                            <i class="bi bi-pencil me-2 text-warning"></i>Edit
                                        </button>
                                    </li>
                                    <li>
                                        <form method="POST" action="{{ route('categories.destroy', $category->id) }}"
                                              onsubmit="return confirm('Delete \'{{ addslashes($category->name) }}\'?')">
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
