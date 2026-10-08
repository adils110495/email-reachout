@extends('layouts.app')

@section('title', 'New Template')
@section('page-title', 'New Template')

@section('breadcrumb')
    <li class="breadcrumb-item">Settings</li>
    <li class="breadcrumb-item"><a href="{{ route('templates.index') }}">Email Templates</a></li>
    <li class="breadcrumb-item active" aria-current="page">New Template</li>
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
                    <h4 class="card-title"><i class="bi bi-plus-circle me-2 text-primary"></i>New Email Template</h4>
                    <p class="mb-0 fs-13">Compose a reusable template for your outreach emails.</p>
                </div>
                <a href="{{ route('templates.index') }}" class="btn btn-light btn-sm m-1">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
            </div>

            <div class="card-body">
                <form method="POST" action="{{ route('templates.store') }}" id="templateForm" enctype="multipart/form-data">
                    @csrf
                    @include('templates._form')
                </form>
            </div>

            <div class="card-footer border-top d-flex gap-2 py-3">
                <button type="submit" form="templateForm" class="btn btn-primary px-4">
                    <i class="bi bi-save me-1"></i>Save Template
                </button>
                <a href="{{ route('templates.index') }}" class="btn btn-light">Cancel</a>
            </div>

        </div>
    </div>
</div>

@endsection

@push('scripts')
@include('templates._quill-init')
@endpush
