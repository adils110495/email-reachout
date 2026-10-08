@extends('layouts.app')

@section('title', 'Addresses — Settings')
@section('page-title', 'Addresses')

@section('breadcrumb')
    <li class="breadcrumb-item">Settings</li>
    <li class="breadcrumb-item active" aria-current="page">Addresses</li>
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
                    <h4 class="card-title"><i class="bi bi-geo-alt me-2 text-primary"></i>Addresses</h4>
                    <p class="mb-0 fs-13">Manage contact addresses used in your outreach.</p>
                </div>
                <div class="clearfix">
                    <a href="{{ route('addresses.export') }}" class="btn btn-light btn-sm m-1">
                        <i class="bi bi-download me-1"></i>Export CSV
                    </a>
                    <button class="btn btn-primary btn-sm m-1" data-bs-toggle="modal" data-bs-target="#addAddressModal">
                        <i class="bi bi-plus-lg me-1"></i>Add Address
                    </button>
                </div>
            </div>

            {{-- Filter bar --}}
            <div class="card-header d-block pb-2">
                <div class="row filter-bar align-items-start">

                    {{-- Live filter --}}
                    <div class="col-12 col-md-6 col-xl-4 mb-3">
                        <label class="form-label" for="addressSearch">Search</label>
                        <input type="text" id="addressSearch" class="form-control" data-live-filter
                               placeholder="Search by address, email, phone, website…" autocomplete="off">
                    </div>

                    {{-- Status filter --}}
                    <div class="col-6 col-md-4 col-xl-3 mb-3">
                        <label class="form-label" for="addressStatusFilter">Status</label>
                        {{-- URL-driven like the Leads page, so the filter is shareable
                             and survives a row action's redirect. --}}
                        <select id="addressStatusFilter" class="form-select select2" data-param="status" data-placeholder="All Statuses">
                            <option value="">All Statuses</option>
                            @foreach($statusOptions as $value => $label)
                                <option value="{{ $value }}" {{ $activeStatus === $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Clear --}}
                    <div class="col-6 col-md-2 col-xl-2 mb-3">
                        {{-- Spacer keeps the button on the same baseline as the labelled controls. --}}
                        <label class="form-label" aria-hidden="true">&nbsp;</label>
                        <a href="{{ route('addresses.index') }}" class="btn btn-danger light" title="Clear all filters">
                            <i class="bi bi-x-circle me-1"></i>Clear
                        </a>
                    </div>
                </div>
            </div>

            {{-- Persistent wrapper: survives the AJAX swap so the loader can sit
                 over the table area while .ajax-content is being replaced. --}}
            <div class="ajax-region">
                @include('addresses._table')
            </div>

        </div>
    </div>
</div>

{{-- ===================== ADD MODAL ===================== --}}
<div class="modal fade" id="addAddressModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <form method="POST" action="{{ route('addresses.store') }}">
                @csrf
                <input type="hidden" name="_redirect_back" value="{{ $redirectBack }}">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2 text-primary"></i>Add Address</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Address <span class="text-danger">*</span></label>
                            <textarea name="address" class="form-control @error('address') is-invalid @enderror"
                                      rows="2" placeholder="e.g. 123 Main St, City, Country" required>{{ old('address') }}</textarea>
                            @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                                   placeholder="contact@example.com" value="{{ old('email') }}" required>
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" {{ old('status') === 'inactive' ? '' : 'selected' }}>Active</option>
                                <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone <span class="text-danger">*</span></label>
                            <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror"
                                   placeholder="+1 234 567 8900" value="{{ old('phone') }}" required>
                            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Alternate Phone</label>
                            <input type="text" name="alternate_phone" class="form-control"
                                   placeholder="+1 234 567 8901" value="{{ old('alternate_phone') }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Website</label>
                            <input type="url" name="website" class="form-control @error('website') is-invalid @enderror"
                                   placeholder="https://example.com" value="{{ old('website') }}">
                            @error('website')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ===================== EDIT MODAL ===================== --}}
<div class="modal fade" id="editAddressModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <form method="POST" id="editAddressForm">
                @csrf @method('PUT')
                <input type="hidden" name="_redirect_back" value="{{ $redirectBack }}">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2 text-warning"></i>Edit Address</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Address <span class="text-danger">*</span></label>
                            <textarea name="address" id="edit_address" class="form-control" rows="2" required></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" id="edit_addr_email" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" id="edit_addr_status" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone <span class="text-danger">*</span></label>
                            <input type="text" name="phone" id="edit_phone" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Alternate Phone</label>
                            <input type="text" name="alternate_phone" id="edit_alternate_phone" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Website</label>
                            <input type="url" name="website" id="edit_website_addr" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="bi bi-save me-1"></i>Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    // Route template resolved server-side so the URL always follows routes/web.php.
    const addressUpdateUrl = @json(route('addresses.update', ['id' => '__ID__']));

    // Delegated: the rows are replaced wholesale on every filter change, so a
    // listener bound to each button at load time would not survive the swap.
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-edit-address');
        if (! btn) return;

        const d = btn.dataset;
        document.getElementById('edit_address').value          = d.address         || '';
        document.getElementById('edit_addr_email').value       = d.email           || '';
        document.getElementById('edit_phone').value            = d.phone           || '';
        document.getElementById('edit_alternate_phone').value  = d.alternate_phone || '';
        document.getElementById('edit_website_addr').value     = d.website         || '';
        document.getElementById('edit_addr_status').value      = d.status          || 'active';
        document.getElementById('editAddressForm').action      = addressUpdateUrl.replace('__ID__', d.id);
        new bootstrap.Modal(document.getElementById('editAddressModal')).show();
    });

    @if($errors->any())
        new bootstrap.Modal(document.getElementById('addAddressModal')).show();
    @endif
</script>
@endpush
