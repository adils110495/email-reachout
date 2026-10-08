{{--
    Templates table + empty state. Swapped in via AJAX on every status-filter
    change by assets/js/ajax-filters.js, so it must stay self-contained: no
    <script> here, and row actions are bound by delegation in index.blade.php.
--}}
<div class="ajax-content">
@if($templates->isEmpty())
    <div class="card-body">
        <div class="empty-state">
            <i class="bi bi-envelope-paper empty-state-icon"></i>
            @if($activeStatus)
                No <strong>{{ $statusOptions[$activeStatus] ?? $activeStatus }}</strong> templates.
                <a href="{{ route('templates.index') }}">Clear the filter</a>.
            @else
                No templates found. <a href="{{ route('templates.create') }}">Create your first template</a>.
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
                        <th class="mw-150">Template Name</th>
                        <th class="mw-200">Subject</th>
                        <th style="width:120px">Status</th>
                        <th class="mw-100 d-none d-lg-table-cell">Created</th>
                        <th style="width:80px" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($templates as $i => $template)
                    <tr data-search="{{ strtolower($template->name . ' ' . $template->subject) }}">
                        <td class="d-none d-md-table-cell"><span>{{ $i + 1 }}</span></td>
                        <td><h6 class="mb-0 cell-wrap">{{ $template->name }}</h6><div class="d-lg-none fs-13 text-muted">{{ $template->created_at->diffForHumans() }}</div></td>
                        <td><span>{{ Str::limit($template->subject, 60) }}</span></td>
                        <td>
                            @if($template->status === 'active')
                                <span class="badge badge-success light">Active</span>
                            @elseif($template->status === 'inactive')
                                <span class="badge badge-secondary light">Inactive</span>
                            @else
                                <span class="badge badge-danger light">Deleted</span>
                            @endif
                        </td>
                        <td class="d-none d-lg-table-cell"><span class="text-nowrap">{{ $template->created_at->diffForHumans() }}</span></td>
                        <td class="text-center">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light btn-square"
                                        data-bs-toggle="dropdown" data-bs-strategy="fixed" aria-expanded="false"
                                        aria-label="Actions" title="Actions">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="{{ route('templates.edit', $template->id) }}">
                                            <i class="bi bi-pencil me-2 text-warning"></i>Edit
                                        </a>
                                    </li>
                                    @if($template->status !== 'active')
                                    <li>
                                        <form method="POST" action="{{ route('templates.toggle', $template->id) }}">
                                            @csrf
                                            <input type="hidden" name="_redirect_back" value="{{ request()->getQueryString() ? '?'.request()->getQueryString() : '' }}">
                                            <input type="hidden" name="status" value="active">
                                            <button type="submit" class="dropdown-item">
                                                <i class="bi bi-toggle-on me-2 text-success"></i>Set Active
                                            </button>
                                        </form>
                                    </li>
                                    @endif
                                    @if($template->status !== 'inactive')
                                    <li>
                                        <form method="POST" action="{{ route('templates.toggle', $template->id) }}">
                                            @csrf
                                            <input type="hidden" name="_redirect_back" value="{{ request()->getQueryString() ? '?'.request()->getQueryString() : '' }}">
                                            <input type="hidden" name="status" value="inactive">
                                            <button type="submit" class="dropdown-item">
                                                <i class="bi bi-toggle-off me-2 text-secondary"></i>Set Inactive
                                            </button>
                                        </form>
                                    </li>
                                    @endif
                                    <li>
                                        <form method="POST" action="{{ route('templates.destroy', $template->id) }}"
                                              onsubmit="return confirm('Delete \'{{ addslashes($template->name) }}\'?')">
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
