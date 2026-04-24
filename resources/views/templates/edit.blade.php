@extends('layouts.app')

@section('title', 'Edit Template')

@section('content')

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="{{ route('templates.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i>
    </a>
    <h4 class="mb-0 fw-bold"><i class="bi bi-pencil-square me-2 text-warning"></i>Edit Template</h4>
</div>

<div class="card border-0 shadow-sm rounded-3 d-flex flex-column" style="height:calc(100vh - 160px);">
    <div class="card-body p-4 overflow-auto flex-grow-1">
        <form method="POST" action="{{ route('templates.update', $template->id) }}" id="templateForm" enctype="multipart/form-data">
            @csrf @method('PUT')
            @include('templates._form', ['template' => $template])
        </form>
    </div>
    <div class="card-footer bg-white border-top d-flex gap-2 py-3 px-4 flex-shrink-0">
        <button type="submit" form="templateForm" class="btn btn-primary px-4">
            <i class="bi bi-save me-1"></i>Update Template
        </button>
        <a href="{{ route('templates.index') }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</div>

@endsection

@push('scripts')
@include('templates._quill-init', ['existingBody' => $template->body])
@endpush
