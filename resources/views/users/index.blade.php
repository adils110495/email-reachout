@extends('layouts.app')

@section('title', 'Users — Settings')
@section('page-title', 'Users')

@section('breadcrumb')
    <li class="breadcrumb-item">Settings</li>
    <li class="breadcrumb-item active" aria-current="page">Users</li>
@endsection

@section('content')

@php
    // Carried by every form so a row action returns to the same filtered list.
    $redirectBack = request()->getQueryString() ? '?'.request()->getQueryString() : '';
@endphp

<div class="row">
    <div class="col-xl-12">
        {{-- data-ajax-root wires up assets/js/ajax-filters.js: the live search,
             the Select2 status filter and the AJAX swap of .ajax-content. --}}
        <div class="card" data-ajax-root>

            <div class="card-header py-3 d-sm-flex d-block align-items-center justify-content-between">
                <div class="clearfix">
                    <h4 class="card-title"><i class="bi bi-people me-2 text-primary"></i>Users</h4>
                    <p class="mb-0 fs-13">Every user works in their own workspace. Choose which modules each one can use.</p>
                </div>
                <div class="clearfix">
                    <button class="btn btn-primary btn-sm m-1" id="btnAddUser">
                        <i class="bi bi-plus-lg me-1"></i>Add User
                    </button>
                </div>
            </div>

            {{-- Filter bar --}}
            <div class="card-header d-block pb-2">
                <div class="row filter-bar align-items-start">

                    <div class="col-12 col-md-6 col-xl-4 mb-3">
                        <label class="form-label" for="userSearch">Search</label>
                        <input type="text" id="userSearch" class="form-control" data-live-filter
                               placeholder="Search by name, username or email…" autocomplete="off">
                    </div>

                    <div class="col-6 col-md-4 col-xl-3 mb-3">
                        <label class="form-label" for="userStatusFilter">Status</label>
                        <select id="userStatusFilter" class="form-select select2" data-param="status" data-placeholder="All Statuses">
                            <option value="">All Statuses</option>
                            @foreach($statusOptions as $value => $label)
                                <option value="{{ $value }}" {{ $activeStatus === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-6 col-md-2 col-xl-2 mb-3">
                        <label class="form-label" aria-hidden="true">&nbsp;</label>
                        <a href="{{ route('users.index') }}" class="btn btn-danger light" title="Clear all filters">
                            <i class="bi bi-x-circle me-1"></i>Clear
                        </a>
                    </div>
                </div>
            </div>

            <div class="ajax-region">
                @include('users._table')
            </div>

        </div>
    </div>
</div>

{{-- Add / Edit User Modal - one form, switched between the two by the script below --}}
<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <form method="POST" id="userForm" action="{{ route('users.store') }}">
                @csrf
                <input type="hidden" name="_method" id="userFormMethod" value="POST">
                <input type="hidden" name="_redirect_back" value="{{ $redirectBack }}">
                <div class="modal-header">
                    <h5 class="modal-title" id="userModalTitle"><i class="bi bi-person-plus me-2 text-primary"></i>Add User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="user_name" class="form-control" required maxlength="255">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" id="user_username" class="form-control" required minlength="3" maxlength="50">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" id="user_email" class="form-control" required maxlength="255">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password <span class="text-danger" id="userPasswordRequired">*</span></label>
                            <input type="password" name="password" id="user_password" class="form-control" minlength="6" autocomplete="new-password">
                            <small class="text-muted d-none" id="userPasswordHint">Leave blank to keep the current password.</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role</label>
                            <select name="role" id="user_role" class="form-select">
                                @foreach($roles as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">A super admin sees and manages every user's data.</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label d-block">Status</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="user_active" checked>
                                <label class="form-check-label" for="user_active">Active — can sign in</label>
                            </div>
                        </div>
                    </div>

                    <div id="userModulesBlock">
                        <label class="form-label d-block mb-2">Modules</label>
                        <div class="row">
                            @foreach($modules as $key => $label)
                                <div class="col-6 col-md-4 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input user-module" type="checkbox" name="modules[]"
                                               value="{{ $key }}" id="module_{{ $key }}" checked>
                                        <label class="form-check-label" for="module_{{ $key }}">{{ $label }}</label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <small class="text-muted">The dashboard is always available.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="userSubmit"><i class="bi bi-save me-1"></i>Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
    // Route templates resolved server-side so the URL always follows routes/web.php.
    const storeUrl  = @json(route('users.store'));
    const updateUrl = @json(route('users.update', ['id' => '__ID__']));

    const modalEl = document.getElementById('userModal');
    const form    = document.getElementById('userForm');
    const $       = (id) => document.getElementById(id);

    // A super admin has every module, so the checkboxes only matter for users.
    function syncModules() {
        $('userModulesBlock').classList.toggle('d-none', $('user_role').value === 'super_admin');
    }

    function open(user) {
        const editing = !! user;

        form.action                 = editing ? updateUrl.replace('__ID__', user.id) : storeUrl;
        $('userFormMethod').value   = editing ? 'PUT' : 'POST';
        $('userModalTitle').innerHTML = editing
            ? '<i class="bi bi-pencil-square me-2 text-warning"></i>Edit User'
            : '<i class="bi bi-person-plus me-2 text-primary"></i>Add User';

        $('user_name').value     = editing ? user.name : '';
        $('user_username').value = editing ? user.username : '';
        $('user_email').value    = editing ? (user.email || '') : '';
        $('user_password').value = '';
        $('user_password').required = ! editing;
        $('userPasswordRequired').classList.toggle('d-none', editing);
        $('userPasswordHint').classList.toggle('d-none', ! editing);
        $('user_role').value     = editing ? user.role : 'user';
        $('user_active').checked = editing ? user.active === '1' : true;

        // Your own role and status are locked - the server refuses to change them too.
        const self = editing && user.self === '1';
        $('user_role').disabled   = self;
        $('user_active').disabled = self;

        const enabled = editing ? JSON.parse(user.modules || '[]') : null;
        document.querySelectorAll('.user-module').forEach(function (box) {
            box.checked = enabled === null || enabled.includes(box.value);
        });

        syncModules();
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    // Disabled fields are not submitted; send the locked values as hidden ones.
    form.addEventListener('submit', function () {
        form.querySelectorAll('.locked-copy').forEach((el) => el.remove());
        ['user_role', 'user_active'].forEach(function (id) {
            const el = $(id);
            if (! el.disabled || (el.type === 'checkbox' && ! el.checked)) return;
            const copy = document.createElement('input');
            copy.type = 'hidden'; copy.className = 'locked-copy';
            copy.name = el.name; copy.value = el.value;
            form.appendChild(copy);
        });
    });

    $('user_role').addEventListener('change', syncModules);
    $('btnAddUser').addEventListener('click', () => open(null));

    // Delegated: the rows are replaced wholesale on every filter change.
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-edit-user');
        if (btn) open(btn.dataset);
    });
}());
</script>
@endpush
