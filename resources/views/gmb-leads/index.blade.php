@extends('layouts.app')

@section('title', 'GMB Leads')
@section('page-title', 'GMB Leads')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">GMB Leads</li>
@endsection

@section('content')

{{-- ===================== SEARCH CARD ===================== --}}
<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header py-3">
                <h4 class="card-title"><i class="bi bi-geo-alt me-2 text-primary"></i>Find Businesses Without a Website</h4>
            </div>
            <div class="card-body">
                <form action="{{ route('gmb-leads.search') }}" method="POST" id="gmbSearchForm">
                    @csrf
                    <div class="row g-3 align-items-start">
                        <div class="col-12 col-md-3">
                            <label class="form-label">Category</label>
                            <select name="search_category" class="form-select" required>
                                <option value="">-- Select Category --</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}" {{ (string) old('search_category') === (string) $cat->id ? 'selected' : '' }}>
                                        {{ $cat->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('search_category')<div class="text-danger fs-13">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Keyword</label>
                            <input type="text" name="keyword" class="form-control @error('keyword') is-invalid @enderror"
                                   placeholder="e.g. plumber in Manchester, bakery Meerut"
                                   value="{{ old('keyword') }}" required minlength="2" maxlength="200">
                            @error('keyword')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Include the city. Only businesses with a Google Business Profile and <strong>no website</strong> are saved.</div>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label d-none d-md-block">&nbsp;</label>
                            <button type="submit" class="btn btn-primary w-100" id="gmbFindBtn">
                                <span class="spinner-border spinner-border-sm d-none me-1" id="gmbSpinner"></span>
                                <i class="bi bi-lightning-charge-fill me-1" id="gmbIcon"></i>Find
                            </button>
                        </div>
                    </div>
                    <div class="text-danger fs-13 mt-2 d-none" id="gmbSearchError"></div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- ===================== LIST ===================== --}}
<div class="row">
    <div class="col-xl-12">
        {{-- data-ajax-root wires up assets/js/ajax-filters.js: the server-side
             search, the Select2 category filter and the AJAX swap of .ajax-content. --}}
        <div class="card" data-ajax-root>

            <div class="card-header py-3 d-sm-flex d-block align-items-center justify-content-between">
                <div class="clearfix">
                    <h4 class="card-title">
                        <i class="bi bi-shop me-2 text-primary"></i>GMB Leads
                        <span class="badge badge-primary light ms-1" data-ajax-total>{{ $gmbLeads->total() }}</span>
                    </h4>
                    <p class="mb-0 fs-13">Businesses with a Google Business Profile but no website.</p>
                </div>
                <div class="clearfix">
                    @include('whatsapp._bulk-bar', ['bulkUrl' => route('gmb-leads.whatsapp'), 'source' => 'gmb', 'label' => 'GMB leads'])
                    <a href="{{ route('gmb-leads.export') }}" class="btn btn-light btn-sm m-1">
                        <i class="bi bi-download me-1"></i>Export CSV
                    </a>
                    <button type="button" class="btn btn-primary btn-sm m-1 btn-add-gmb">
                        <i class="bi bi-plus-lg me-1"></i>Add GMB Lead
                    </button>
                </div>
            </div>

            <div class="card-header d-block pb-2">
                {{-- Columns add up to 12 per row at every width (xl: 3+2+2+3+2, md: 6+6 / 4+4+4),
                     so Clear gets a full-width column like on the other lists. --}}
                <div class="row filter-bar align-items-start">
                    <div class="col-12 col-md-6 col-xl-3 mb-3">
                        <label class="form-label" for="gmbSearch">Search</label>
                        <input type="text" id="gmbSearch" class="form-control" data-search-param="q"
                               value="{{ $search }}" placeholder="Name, phone, address or keyword…" autocomplete="off">
                    </div>
                    <div class="col-6 col-md-6 col-xl-2 mb-3">
                        <label class="form-label" for="gmbCategoryFilter">Category</label>
                        <select id="gmbCategoryFilter" class="form-select select2" data-param="category" data-placeholder="All Categories">
                            <option value="">All Categories</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" {{ (int) $activeCategory === $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-4 col-xl-2 mb-3">
                        <label class="form-label" for="gmbRatingFilter">Rating</label>
                        <select id="gmbRatingFilter" class="form-select select2" data-param="rating" data-placeholder="Any Rating">
                            <option value="">Any Rating</option>
                            @foreach($ratingOptions as $value => $label)
                                <option value="{{ $value }}" {{ $activeRating === (string) $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    {{-- Min / max: each box is its own data-search-param input, debounced by ajax-filters.js --}}
                    <div class="col-6 col-md-4 col-xl-3 mb-3">
                        <label class="form-label">Reviews</label>
                        <div class="input-group">
                            <input type="number" class="form-control" data-search-param="reviews_min" value="{{ $reviewsMin }}"
                                   placeholder="Min" min="0" step="1" aria-label="Minimum reviews">
                            <input type="number" class="form-control" data-search-param="reviews_max" value="{{ $reviewsMax }}"
                                   placeholder="Max" min="0" step="1" aria-label="Maximum reviews">
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-xl-2 mb-3">
                        <label class="form-label" aria-hidden="true">&nbsp;</label>
                        <a href="{{ route('gmb-leads.index') }}" class="btn btn-danger light" title="Clear all filters">
                            <i class="bi bi-x-circle me-1"></i>Clear
                        </a>
                    </div>
                </div>
            </div>

            <div class="ajax-region">
                @include('gmb-leads._table')
            </div>

        </div>
    </div>
</div>

{{-- ===================== ADD / EDIT MODAL ===================== --}}
{{-- One form for both: the script below sets add or edit mode. A failed save
     comes back with old() values and the script reopens it in the same mode. --}}
<div class="modal fade" id="gmbLeadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <form method="POST" action="{{ route('gmb-leads.store') }}" id="gmbLeadForm"
                  data-store-url="{{ route('gmb-leads.store') }}"
                  data-update-url="{{ route('gmb-leads.update', ['id' => '__ID__']) }}">
                @csrf
                <input type="hidden" name="_method" value="{{ old('_gmb_form') === 'edit' ? 'PUT' : 'POST' }}" id="gmb_form_method">
                <input type="hidden" name="_gmb_form" value="{{ old('_gmb_form', 'add') }}" id="gmb_form_mode">
                <input type="hidden" name="_gmb_id" value="{{ old('_gmb_id') }}" id="gmb_form_id">
                <input type="hidden" name="_redirect_back" value="{{ request()->getQueryString() ? '?'.request()->getQueryString() : '' }}">

                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle text-primary me-2" id="gmb_form_icon"></i><span id="gmb_form_title">Add GMB Lead</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="gmb_name">Business name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="gmb_name" class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name') }}" maxlength="255" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="gmb_category">Category</label>
                            <select name="category_id" id="gmb_category" class="form-select @error('category_id') is-invalid @enderror">
                                <option value="">— None —</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}" @selected((string) old('category_id') === (string) $cat->id)>{{ $cat->name }}</option>
                                @endforeach
                            </select>
                            @error('category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="gmb_type">Type</label>
                            <input type="text" name="type" id="gmb_type" class="form-control @error('type') is-invalid @enderror"
                                   value="{{ old('type') }}" maxlength="255" placeholder="e.g. Construction company">
                            @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="gmb_phone">Phone</label>
                            <input type="text" name="phone" id="gmb_phone" class="form-control @error('phone') is-invalid @enderror"
                                   value="{{ old('phone') }}" maxlength="50" placeholder="+91 98765 43210">
                            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Needed for WhatsApp.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="gmb_address">Address</label>
                            <input type="text" name="address" id="gmb_address" class="form-control @error('address') is-invalid @enderror"
                                   value="{{ old('address') }}" maxlength="255">
                            @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label" for="gmb_rating">Rating</label>
                            <input type="number" name="rating" id="gmb_rating" class="form-control @error('rating') is-invalid @enderror"
                                   value="{{ old('rating') }}" min="0" max="5" step="0.1" placeholder="0 – 5">
                            @error('rating')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label" for="gmb_reviews">Reviews</label>
                            <input type="number" name="reviews" id="gmb_reviews" class="form-control @error('reviews') is-invalid @enderror"
                                   value="{{ old('reviews') }}" min="0" step="1">
                            @error('reviews')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="gmb_keyword">Keyword</label>
                            <input type="text" name="lead_keyword" id="gmb_keyword" class="form-control @error('lead_keyword') is-invalid @enderror"
                                   value="{{ old('lead_keyword') }}" maxlength="255" placeholder="How you found it, e.g. contractor meerut">
                            @error('lead_keyword')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="gmb_maps">Google Maps link</label>
                            <input type="url" name="maps_url" id="gmb_maps" class="form-control @error('maps_url') is-invalid @enderror"
                                   value="{{ old('maps_url') }}" maxlength="2000" placeholder="https://maps.google.com/…">
                            @error('maps_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i><span id="gmb_form_submit">Add</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@include('whatsapp._compose')

@endsection

@push('scripts')
<script>
    // Submit by fetch so the page does not reload. The Find button stays
    // disabled while the job runs; the notification poller announces the end
    // ('app:notification'), which re-enables it and refreshes the list.
    (function () {
        const form    = document.getElementById('gmbSearchForm');
        const btn     = document.getElementById('gmbFindBtn');
        const spinner = document.getElementById('gmbSpinner');
        const icon    = document.getElementById('gmbIcon');
        const errBox  = document.getElementById('gmbSearchError');
        let failsafe  = null;

        function setBusy(on) {
            btn.disabled = on;
            spinner.classList.toggle('d-none', ! on);
            icon.classList.toggle('d-none', on);
            window.clearTimeout(failsafe);
            // Never leave the button locked forever if the notification is missed.
            if (on) failsafe = window.setTimeout(function () { setBusy(false); }, 180000);
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            errBox.classList.add('d-none');
            setBusy(true);

            fetch(form.action, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form),
            })
                .then(function (r) {
                    if (r.ok) {
                        form.querySelector('[name=keyword]').value = '';
                        return; // stay busy until the job's notification arrives
                    }
                    return r.json().catch(function () { return {}; }).then(function (data) {
                        const first = data.errors ? Object.values(data.errors)[0][0] : (data.message || 'Could not start the search.');
                        errBox.textContent = first;
                        errBox.classList.remove('d-none');
                        setBusy(false);
                    });
                })
                .catch(function () {
                    errBox.textContent = 'Could not start the search. Please try again.';
                    errBox.classList.remove('d-none');
                    setBusy(false);
                });
        });

        document.addEventListener('app:notification', function (e) {
            if (! /^GMB search/.test(e.detail.title || '')) return;

            setBusy(false);
            document.querySelector('[data-ajax-root]').dispatchEvent(new CustomEvent('ajax-filters:reload'));
        });
    })();

    // Add / Edit a GMB lead. Delegated: the rows (and their Edit buttons) are
    // swapped in by ajax-filters.js on every filter change.
    (function () {
        const modalEl = document.getElementById('gmbLeadModal');
        const form    = document.getElementById('gmbLeadForm');
        const field   = (name) => form.querySelector('[name="' + name + '"]');
        // Edit button data-* attribute => form field
        const FIELDS  = { name: 'name', type: 'type', category: 'category_id', phone: 'phone', address: 'address',
                          rating: 'rating', reviews: 'reviews', maps: 'maps_url', keyword: 'lead_keyword' };

        function setMode(mode, id) {
            const edit = mode === 'edit';
            form.action = edit ? form.dataset.updateUrl.replace('__ID__', id) : form.dataset.storeUrl;
            document.getElementById('gmb_form_method').value = edit ? 'PUT' : 'POST';
            document.getElementById('gmb_form_mode').value   = mode;
            document.getElementById('gmb_form_id').value     = edit ? id : '';
            document.getElementById('gmb_form_title').textContent  = edit ? 'Edit GMB Lead' : 'Add GMB Lead';
            document.getElementById('gmb_form_submit').textContent = edit ? 'Update' : 'Add';
            document.getElementById('gmb_form_icon').className = 'bi me-2 ' + (edit ? 'bi-pencil-square text-warning' : 'bi-plus-circle text-primary');
        }

        function clearErrors() {
            form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
            form.querySelectorAll('.invalid-feedback').forEach(function (el) { el.remove(); });
        }

        document.addEventListener('click', function (e) {
            const add  = e.target.closest('.btn-add-gmb');
            const edit = e.target.closest('.btn-edit-gmb');
            if (! add && ! edit) return;
            e.preventDefault();

            clearErrors();
            Object.keys(FIELDS).forEach(function (key) {
                field(FIELDS[key]).value = edit ? (edit.dataset[key] || '') : '';
            });
            setMode(edit ? 'edit' : 'add', edit ? edit.dataset.id : null);
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        });

        // A failed save comes back with its errors and old() values: reopen it as it was.
        @if($errors->any() && old('_gmb_form'))
            setMode(@json(old('_gmb_form')), @json(old('_gmb_id')));
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        @endif
    })();
</script>
@endpush
