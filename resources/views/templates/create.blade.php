@extends('layouts.app')

@section('title', 'New Template')

@section('content')

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="{{ route('templates.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i>
    </a>
    <h4 class="mb-0 fw-bold"><i class="bi bi-plus-circle me-2 text-primary"></i>New Email Template</h4>
</div>

<div class="card border-0 shadow-sm rounded-3 d-flex flex-column" style="height:calc(100vh - 160px);">
    <div class="card-body p-4 overflow-auto flex-grow-1">
        <form method="POST" action="{{ route('templates.store') }}" id="templateForm">
            @csrf
            @include('templates._form')
        </form>
    </div>
    <div class="card-footer bg-white border-top d-flex gap-2 py-3 px-4 flex-shrink-0">
        <button type="submit" form="templateForm" class="btn btn-primary px-4">
            <i class="bi bi-save me-1"></i>Save Template
        </button>
        <a href="{{ route('templates.index') }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</div>

@endsection

@push('scripts')
@include('templates._quill-init')
@endpush
