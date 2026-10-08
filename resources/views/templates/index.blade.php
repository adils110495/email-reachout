@extends('layouts.app')

@section('title', 'Email Templates - Settings')
@section('page-title', 'Email Templates')

@section('breadcrumb')
    <li class="breadcrumb-item">Settings</li>
    <li class="breadcrumb-item active" aria-current="page">Email Templates</li>
@endsection

@section('page-title-action')
    <a class="text-primary fs-13" href="{{ route('templates.create') }}">+ New Template</a>
@endsection

@section('content')

<div class="row">
    <div class="col-xl-12">
        {{-- data-ajax-root wires up assets/js/ajax-filters.js: the live search,
             the Select2 status filter and the AJAX swap of .ajax-content. --}}
        <div class="card" data-ajax-root>

            <div class="card-header py-3 d-sm-flex d-block align-items-center justify-content-between">
                <div class="clearfix">
                    <h4 class="card-title"><i class="bi bi-envelope-paper me-2 text-primary"></i>Email Templates</h4>
                    <p class="mb-0 fs-13">Create reusable templates to load into the compose window.</p>
                </div>
                <div class="clearfix">
                    <a href="{{ route('templates.export') }}" class="btn btn-light btn-sm m-1">
                        <i class="bi bi-download me-1"></i>Export CSV
                    </a>
                    <a href="{{ route('templates.create') }}" class="btn btn-primary btn-sm m-1">
                        <i class="bi bi-plus-lg me-1"></i>New Template
                    </a>
                </div>
            </div>

            {{-- Filter bar --}}
            <div class="card-header d-block pb-2">
                <div class="row filter-bar align-items-start">

                    {{-- Live filter --}}
                    <div class="col-12 col-md-6 col-xl-4 mb-3">
                        <label class="form-label" for="templateSearch">Search</label>
                        <input type="text" id="templateSearch" class="form-control" data-live-filter
                               placeholder="Search by name or subject…" autocomplete="off">
                    </div>

                    {{-- Status filter --}}
                    <div class="col-6 col-md-4 col-xl-3 mb-3">
                        <label class="form-label" for="templateStatusFilter">Status</label>
                        {{-- URL-driven like the Leads page, so the filter is shareable
                             and survives a row action's redirect. --}}
                        <select id="templateStatusFilter" class="form-select select2" data-param="status" data-placeholder="All Statuses">
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
                        <a href="{{ route('templates.index') }}" class="btn btn-danger light" title="Clear all filters">
                            <i class="bi bi-x-circle me-1"></i>Clear
                        </a>
                    </div>
                </div>
            </div>

            {{-- Persistent wrapper: survives the AJAX swap so the loader can sit
                 over the table area while .ajax-content is being replaced. --}}
            <div class="ajax-region">
                @include('templates._table')
            </div>

        </div>
    </div>
</div>

@endsection
