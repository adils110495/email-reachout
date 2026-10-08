@extends('layouts.app')

@section('title', 'Cash Leads')
@section('page-title', 'Cash Leads')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Cash Leads</li>
@endsection

@section('content')

@php
    // Carried by every form so a row action returns to the same filtered list.
    $redirectBack = request()->getQueryString() ? '?'.request()->getQueryString() : '';
@endphp

<div class="row">
    <div class="col-xl-12">
        {{-- data-ajax-root wires up assets/js/ajax-filters.js: the server-side
             search, the Select2 filters and the AJAX swap of .ajax-content. --}}
        <div class="card" data-ajax-root>

            <div class="card-header py-3 d-sm-flex d-block align-items-center justify-content-between">
                <div class="clearfix">
                    <h4 class="card-title">
                        <i class="bi bi-cash-coin me-2 text-primary"></i>Cash Leads
                        <span class="badge badge-primary light ms-1" data-ajax-total>{{ $cashLeads->total() }}</span>
                    </h4>
                    <p class="mb-0 fs-13">Leads you have talked to and expect to turn into paying customers.</p>
                </div>
                <div class="clearfix">
                    <a href="{{ route('cash-leads.export') }}" class="btn btn-light btn-sm m-1">
                        <i class="bi bi-download me-1"></i>Export CSV
                    </a>
                    <button class="btn btn-primary btn-sm m-1" data-bs-toggle="modal" data-bs-target="#addCashModal">
                        <i class="bi bi-plus-lg me-1"></i>Add Cash Lead
                    </button>
                </div>
            </div>

            {{-- Filter bar --}}
            <div class="card-header d-block pb-2">
                <div class="row filter-bar align-items-start">
                    <div class="col-12 col-md-6 col-xl-4 mb-3">
                        <label class="form-label" for="cashSearch">Search</label>
                        <input type="text" id="cashSearch" class="form-control" data-search-param="q"
                               value="{{ $search }}" placeholder="Company, email, phone, website or notes…" autocomplete="off">
                    </div>
                    <div class="col-6 col-md-3 col-xl-3 mb-3">
                        <label class="form-label" for="cashCategoryFilter">Category</label>
                        <select id="cashCategoryFilter" class="form-select select2" data-param="category" data-placeholder="All Categories">
                            <option value="">All Categories</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" {{ (int) $activeCategory === $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-3 col-xl-3 mb-3">
                        <label class="form-label" for="cashSourceFilter">Source</label>
                        <select id="cashSourceFilter" class="form-select select2" data-param="source" data-placeholder="All Sources">
                            <option value="">All Sources</option>
                            @foreach($sources as $value => $label)
                                <option value="{{ $value }}" {{ $activeSource === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-2 col-xl-2 mb-3">
                        <label class="form-label" aria-hidden="true">&nbsp;</label>
                        <a href="{{ route('cash-leads.index') }}" class="btn btn-danger light" title="Clear all filters">
                            <i class="bi bi-x-circle me-1"></i>Clear
                        </a>
                    </div>
                </div>
            </div>

            <div class="ajax-region">
                @include('cash-leads._table')
            </div>

        </div>
    </div>
</div>

{{-- Add / Edit modals share one field set --}}
@foreach(['add' => ['Add Cash Lead', 'bi-plus-circle text-primary', route('cash-leads.store'), false],
          'edit' => ['Edit Cash Lead', 'bi-pencil-square text-warning', '#', true]] as $mode => [$title, $iconClass, $action, $isEdit])
<div class="modal fade" id="{{ $mode }}CashModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <form method="POST" action="{{ $action }}" id="{{ $mode }}CashForm">
                @csrf
                @if($isEdit) @method('PUT') @endif
                <input type="hidden" name="_redirect_back" value="{{ $redirectBack }}">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi {{ $iconClass }} me-2"></i>{{ $title }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Company / Business name <span class="text-danger">*</span></label>
                            <input type="text" name="company_name" id="{{ $mode }}_cash_company"
                                   class="form-control @if(! $isEdit) @error('company_name') is-invalid @enderror @endif"
                                   value="{{ $isEdit ? '' : old('company_name') }}" required maxlength="255">
                            @if(! $isEdit) @error('company_name')<div class="invalid-feedback">{{ $message }}</div>@enderror @endif
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Category</label>
                            <select name="category_id" id="{{ $mode }}_cash_category" class="form-select">
                                <option value="">— None —</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}" {{ ! $isEdit && (string) old('category_id') === (string) $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" id="{{ $mode }}_cash_email" class="form-control"
                                   value="{{ $isEdit ? '' : old('email') }}" maxlength="255">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" id="{{ $mode }}_cash_phone" class="form-control"
                                   value="{{ $isEdit ? '' : old('phone') }}" maxlength="50">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Website</label>
                            <input type="text" name="website" id="{{ $mode }}_cash_website" class="form-control"
                                   value="{{ $isEdit ? '' : old('website') }}" maxlength="255">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Address</label>
                            <input type="text" name="address" id="{{ $mode }}_cash_address" class="form-control"
                                   value="{{ $isEdit ? '' : old('address') }}" maxlength="255">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" id="{{ $mode }}_cash_notes" class="form-control" rows="3"
                                      placeholder="What was discussed, expected deal, next step…" maxlength="5000">{{ $isEdit ? '' : old('notes') }}</textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn {{ $isEdit ? 'btn-warning' : 'btn-primary' }}">
                        <i class="bi {{ $isEdit ? 'bi-save' : 'bi-plus-lg' }} me-1"></i>{{ $isEdit ? 'Update' : 'Add' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach

@endsection

@push('scripts')
<script>
    // Route template resolved server-side so the URL always follows routes/web.php.
    const cashUpdateUrl = @json(route('cash-leads.update', ['id' => '__ID__']));

    // Delegated: the rows are replaced wholesale on every filter change, so a
    // listener bound to each button at load time would not survive the swap.
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-edit-cash');
        if (! btn) return;

        document.getElementById('edit_cash_company').value  = btn.dataset.company  || '';
        document.getElementById('edit_cash_category').value = btn.dataset.category || '';
        document.getElementById('edit_cash_email').value    = btn.dataset.email    || '';
        document.getElementById('edit_cash_phone').value    = btn.dataset.phone    || '';
        document.getElementById('edit_cash_website').value  = btn.dataset.website  || '';
        document.getElementById('edit_cash_address').value  = btn.dataset.address  || '';
        document.getElementById('edit_cash_notes').value    = btn.dataset.notes    || '';
        document.getElementById('editCashForm').action      = cashUpdateUrl.replace('__ID__', btn.dataset.id);

        new bootstrap.Modal(document.getElementById('editCashModal')).show();
    });

    {{-- Re-open the add modal when a manual add failed validation --}}
    @if($errors->any())
        new bootstrap.Modal(document.getElementById('addCashModal')).show();
    @endif
</script>
@endpush
