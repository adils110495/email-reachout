{{--
    Users table + empty state. Swapped in via AJAX on every status-filter
    change by assets/js/ajax-filters.js, so it must stay self-contained: no
    <script> here, and row actions are bound by delegation in index.blade.php.
--}}
<div class="ajax-content">
@if($users->isEmpty())
    <div class="card-body">
        <div class="empty-state">
            <i class="bi bi-people empty-state-icon"></i>
            No <strong>{{ $statusOptions[$activeStatus] ?? '' }}</strong> users.
            <a href="{{ route('users.index') }}">Clear the filter</a>.
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
                        <th>Role</th>
                        <th class="mw-200">Modules</th>
                        <th class="text-center">Leads</th>
                        <th>Status</th>
                        <th style="width:80px" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $i => $user)
                    @php
                        $enabled = $user->enabledModules();
                        $isSelf  = $user->is(auth()->user());
                    @endphp
                    <tr data-search="{{ strtolower($user->name . ' ' . $user->username . ' ' . $user->email) }}">
                        <td><span>{{ $i + 1 }}</span></td>
                        <td>
                            <h6 class="mb-0">{{ $user->name }} @if($isSelf)<small class="text-muted">(you)</small>@endif</h6>
                            <small class="text-muted">{{ $user->username }}{{ $user->email ? ' · ' . $user->email : '' }}</small>
                        </td>
                        <td>
                            @if($user->isSuperAdmin())
                                <span class="badge badge-primary light">Super Admin</span>
                            @else
                                <span class="badge badge-secondary light">User</span>
                            @endif
                        </td>
                        <td class="fs-13">
                            @if($user->isSuperAdmin() || count($enabled) === count($modules))
                                All modules
                            @elseif($enabled === [])
                                <span class="text-muted">None (dashboard only)</span>
                            @else
                                {{ collect($enabled)->map(fn ($m) => $modules[$m])->implode(', ') }}
                            @endif
                        </td>
                        <td class="text-center">{{ $user->leads_count }}</td>
                        <td>
                            @if($user->is_active)
                                <span class="badge badge-success light">Active</span>
                            @else
                                <span class="badge badge-secondary light">Inactive</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light btn-square"
                                        data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <button class="dropdown-item btn-edit-user"
                                                data-id="{{ $user->id }}"
                                                data-name="{{ $user->name }}"
                                                data-username="{{ $user->username }}"
                                                data-email="{{ $user->email }}"
                                                data-role="{{ $user->role }}"
                                                data-active="{{ $user->is_active ? '1' : '0' }}"
                                                data-modules='@json($enabled)'
                                                data-self="{{ $isSelf ? '1' : '0' }}">
                                            <i class="bi bi-pencil me-2 text-warning"></i>Edit
                                        </button>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="{{ route('email-activity.index', ['user' => $user->id]) }}">
                                            <i class="bi bi-activity me-2 text-primary"></i>Email activity
                                        </a>
                                    </li>
                                    @unless($isSelf)
                                        <li>
                                            <form method="POST" action="{{ route('users.destroy', $user->id) }}"
                                                  onsubmit="return confirm('Delete \'{{ addslashes($user->name) }}\' and ALL of their leads, emails and settings? This cannot be undone.')">
                                                @csrf @method('DELETE')
                                                <input type="hidden" name="_redirect_back" value="{{ request()->getQueryString() ? '?'.request()->getQueryString() : '' }}">
                                                <button type="submit" class="dropdown-item text-danger">
                                                    <i class="bi bi-trash me-2"></i>Delete
                                                </button>
                                            </form>
                                        </li>
                                    @endunless
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
