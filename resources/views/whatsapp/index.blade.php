@extends('layouts.app')

@section('title', 'WhatsApp')
@section('page-title', 'WhatsApp')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">WhatsApp</li>
@endsection

@section('content')

@if(! $setting?->canSend())
    <div class="alert alert-info d-flex align-items-start gap-2">
        <i class="bi bi-info-circle-fill mt-1"></i>
        <div>
            <strong>WhatsApp Cloud API is not connected.</strong>
            You can still message contacts with <em>Open in WhatsApp</em> (your own app or WhatsApp Web).
            <a href="{{ route('whatsapp-settings.index') }}">Connect the API</a> to send from here and track delivered, read and replied.
        </div>
    </div>
@endif

<div class="row">
    <div class="col-xl-12">
        {{-- data-ajax-root wires up assets/js/ajax-filters.js: the server-side
             search, the Select2 filters and the AJAX swap of .ajax-content. --}}
        <div class="card" data-ajax-root>

            <div class="card-header py-3 d-sm-flex d-block align-items-center justify-content-between">
                <div class="clearfix">
                    <h4 class="card-title">
                        <i class="bi bi-whatsapp me-2 text-success"></i>WhatsApp Messages
                        <span class="badge badge-primary light ms-1" data-ajax-total>{{ $messages->total() }}</span>
                    </h4>
                    <p class="mb-0 fs-13">Every WhatsApp message sent from here: delivered, read, replied - or failed, and why.</p>
                </div>
                <div class="clearfix">
                    <a href="{{ route('whatsapp.export') }}" class="btn btn-light btn-sm m-1">
                        <i class="bi bi-download me-1"></i>Export CSV
                    </a>
                    <button type="button" class="btn btn-success btn-sm m-1 btn-wa-compose" data-source="direct">
                        <i class="bi bi-plus-lg me-1"></i>New Message
                    </button>
                </div>
            </div>

            {{-- Filter bar --}}
            <div class="card-header d-block pb-2">
                <div class="row filter-bar align-items-start">
                    <div class="col-12 col-md-6 col-xl-4 mb-3">
                        <label class="form-label" for="waSearch">Search</label>
                        <input type="text" id="waSearch" class="form-control" data-search-param="q"
                               value="{{ $search }}" placeholder="Name, number, message or template…" autocomplete="off">
                    </div>
                    <div class="col-6 col-md-3 col-xl-3 mb-3">
                        <label class="form-label" for="waActivityFilter">Status</label>
                        <select id="waActivityFilter" class="form-select select2" data-param="activity" data-placeholder="All Statuses">
                            <option value="">All Statuses ({{ $counts['total'] }})</option>
                            @foreach($activities as $value => $label)
                                <option value="{{ $value }}" {{ $activity === $value ? 'selected' : '' }}>
                                    {{ $label }} ({{ $counts[$value] }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- User filter: super admin only (every user's messages) --}}
                    @if($isSuperAdmin)
                        <div class="col-6 col-md-3 col-xl-3 mb-3">
                            <label class="form-label" for="waUserFilter">User</label>
                            <select id="waUserFilter" class="form-select select2" data-param="user" data-placeholder="All Users">
                                <option value="">All Users</option>
                                @foreach($users as $u)
                                    <option value="{{ $u->id }}" {{ $owner === $u->id ? 'selected' : '' }}>
                                        {{ $u->name }} ({{ $u->username }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="col-6 col-md-2 col-xl-2 mb-3">
                        <label class="form-label" aria-hidden="true">&nbsp;</label>
                        <a href="{{ route('whatsapp.index') }}" class="btn btn-danger light" title="Clear all filters">
                            <i class="bi bi-x-circle me-1"></i>Clear
                        </a>
                    </div>
                </div>
            </div>

            <div class="ajax-region">
                @include('whatsapp._table')
            </div>

        </div>
    </div>
</div>

@include('whatsapp._compose')

@endsection

@push('scripts')
{{-- Polls a running bulk send's progress panel (whatsapp/_table) and swaps the list in. --}}
<script src="{{ asset('assets/js/bulk-progress.js') }}?v={{ filemtime(public_path('assets/js/bulk-progress.js')) }}"></script>
@endpush
