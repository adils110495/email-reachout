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
                    <a href="{{ route('gmb-leads.export') }}" class="btn btn-light btn-sm m-1">
                        <i class="bi bi-download me-1"></i>Export CSV
                    </a>
                </div>
            </div>

            <div class="card-header d-block pb-2">
                <div class="row filter-bar align-items-start">
                    <div class="col-12 col-md-6 col-xl-3 mb-3">
                        <label class="form-label" for="gmbSearch">Search</label>
                        <input type="text" id="gmbSearch" class="form-control" data-search-param="q"
                               value="{{ $search }}" placeholder="Name, phone, address or keyword…" autocomplete="off">
                    </div>
                    <div class="col-6 col-md-4 col-xl-3 mb-3">
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
                    <div class="col-6 col-md-4 col-xl-2 mb-3">
                        <label class="form-label">Reviews</label>
                        <div class="input-group">
                            <input type="number" class="form-control" data-search-param="reviews_min" value="{{ $reviewsMin }}"
                                   placeholder="Min" min="0" step="1" aria-label="Minimum reviews">
                            <input type="number" class="form-control" data-search-param="reviews_max" value="{{ $reviewsMax }}"
                                   placeholder="Max" min="0" step="1" aria-label="Maximum reviews">
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-xl-1 mb-3">
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
</script>
@endpush
