@extends('layouts.app')

@section('title', 'Edit Template')
@section('page-title', 'Edit Template')

@section('breadcrumb')
    <li class="breadcrumb-item">Settings</li>
    <li class="breadcrumb-item"><a href="{{ route('templates.index') }}">Email Templates</a></li>
    <li class="breadcrumb-item active" aria-current="page">Edit</li>
@endsection

@section('page-title-action')
    <a class="text-primary fs-13" href="{{ route('templates.index') }}">&larr; Back to Templates</a>
@endsection

@section('content')

<div class="row">
    <div class="col-xl-12">
        <div class="card">

            <div class="card-header py-3 d-sm-flex d-block align-items-center justify-content-between">
                <div class="clearfix">
                    <h4 class="card-title"><i class="bi bi-pencil-square me-2 text-warning"></i>Edit Template</h4>
                    <p class="mb-0 fs-13">{{ $template->name }}</p>
                </div>
                <a href="{{ route('templates.index') }}" class="btn btn-light btn-sm m-1">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
            </div>

            <div class="card-body">
                <form method="POST" action="{{ route('templates.update', $template->id) }}" id="templateForm" enctype="multipart/form-data">
                    @csrf @method('PUT')
                    @include('templates._form', ['template' => $template])
                </form>
            </div>

            <div class="card-footer border-top d-flex gap-2 py-3">
                <button type="submit" form="templateForm" class="btn btn-primary px-4">
                    <i class="bi bi-save me-1"></i>Update Template
                </button>
                <a href="{{ route('templates.index') }}" class="btn btn-light">Cancel</a>
            </div>

        </div>
    </div>
</div>

@endsection

@push('scripts')
@include('templates._quill-init', ['existingBody' => $template->body])
@endpush
