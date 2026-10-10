@extends('layouts.app')

@section('title', 'Preview step '.$step->step_number)
@section('page-title', 'Preview')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('outreach.sequences.show', $step->sequence_id) }}">{{ $step->sequence->name }}</a></li>
    <li class="breadcrumb-item active">Preview step {{ $step->step_number }}</li>
@endsection

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between flex-wrap gap-2">
        <div><div class="text-muted fs-13">Subject</div><h4 class="mb-0">{{ $preview['subject'] }}</h4></div>
        <div class="fs-13 text-muted align-self-center">{{ $lead ? 'Rendered for '.$lead->email : 'Rendered with sample data' }}</div>
    </div>
    @if ($preview['unknown'])
        <div class="alert alert-warning m-3 mb-0">Unknown variable(s) will render empty: @foreach ($preview['unknown'] as $u)<code>{{ '{'.'{'.$u.'}'.'}' }}</code> @endforeach</div>
    @endif
    <div class="card-body">
        {{-- Sandboxed iframe: user-authored HTML can never run script or reach this page. --}}
        <iframe sandbox title="Email preview" style="width:100%;height:420px;border:1px solid var(--bs-border-color,#ddd);border-radius:6px;background:#fff" srcdoc="{{ $preview['html'] }}"></iframe>
        <a href="{{ route('outreach.steps.edit', $step) }}" class="btn btn-light mt-3">Back to editor</a>
    </div>
</div>
@endsection
