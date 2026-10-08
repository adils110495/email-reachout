@extends('layouts.app')

@section('title', 'Email Activity')
@section('page-title', 'Email Activity')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Email Activity</li>
@endsection

@section('content')

<div class="row">
    <div class="col-xl-12">
        {{-- data-ajax-root wires up assets/js/ajax-filters.js: the server-side
             search, the Select2 activity filter and the AJAX swap of .ajax-content. --}}
        <div class="card" data-ajax-root>

            <div class="card-header py-3 d-sm-flex d-block align-items-center justify-content-between">
                <div class="clearfix">
                    <h4 class="card-title"><i class="bi bi-activity me-2 text-primary"></i>Email Activity</h4>
                    <p class="mb-0 fs-13">What happened to every email you sent: opened, replied, or not opened yet.</p>
                </div>
                <div class="clearfix">
                    <form method="POST" action="{{ route('email-activity.check-replies') }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-sm m-1">
                            <i class="bi bi-arrow-repeat me-1"></i>Check Replies
                        </button>
                    </form>
                </div>
            </div>

            {{-- Filter bar --}}
            <div class="card-header d-block pb-2">
                <div class="row filter-bar align-items-start">

                    {{-- Server-side search: matches across every page --}}
                    <div class="col-12 col-md-6 col-xl-4 mb-3">
                        <label class="form-label" for="activitySearch">Search</label>
                        <input type="text" id="activitySearch" class="form-control" data-search-param="q"
                               value="{{ $search }}" placeholder="Company, email or subject…" autocomplete="off">
                    </div>

                    {{-- Activity filter --}}
                    <div class="col-6 col-md-4 col-xl-3 mb-3">
                        <label class="form-label" for="activityFilter">Activity</label>
                        <select id="activityFilter" class="form-select select2" data-param="activity" data-placeholder="All Activity">
                            <option value="">All Activity ({{ $counts['total'] }})</option>
                            @foreach($activityOptions as $value => $label)
                                <option value="{{ $value }}" {{ $activity === $value ? 'selected' : '' }}>
                                    {{ $label }} ({{ $counts[$value] }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Clear --}}
                    <div class="col-6 col-md-2 col-xl-2 mb-3">
                        {{-- Spacer keeps the button on the same baseline as the labelled controls. --}}
                        <label class="form-label" aria-hidden="true">&nbsp;</label>
                        <a href="{{ route('email-activity.index') }}" class="btn btn-danger light" title="Clear all filters">
                            <i class="bi bi-x-circle me-1"></i>Clear
                        </a>
                    </div>
                </div>
            </div>

            {{-- Persistent wrapper: survives the AJAX swap so the loader can sit
                 over the table area while .ajax-content is being replaced. --}}
            <div class="ajax-region">
                @include('email-activity._table')
            </div>

        </div>
    </div>
</div>

@endsection
