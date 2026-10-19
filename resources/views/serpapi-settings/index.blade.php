@extends('layouts.app')

@section('title', 'SerpAPI — Settings')
@section('page-title', 'SerpAPI')

@section('breadcrumb')
    <li class="breadcrumb-item">Settings</li>
    <li class="breadcrumb-item active" aria-current="page">SerpAPI</li>
@endsection

@section('content')

@php
    $hasOwnKey = (bool) $setting?->hasKey();
    $isActive  = (bool) old('is_active', $setting->is_active ?? true);
@endphp

<div class="row">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header py-3 d-block">
                <h4 class="card-title"><i class="bi bi-google me-2 text-primary"></i>SerpAPI</h4>
                <p class="mb-0 fs-13">
                    Google search for <strong>Find Leads</strong>, the <strong>GMB Leads</strong> search and the AI writer's company research.
                    Every search spends one SerpAPI credit of the key used.
                </p>
            </div>

            <div class="card-body">
                {{-- Which key searches use right now --}}
                @if($source === \App\Services\SerpApiConfigService::SOURCE_SETTINGS)
                    <div class="alert alert-success fs-13 d-flex gap-2 align-items-start">
                        <i class="bi bi-check-circle-fill mt-1"></i>
                        <div>Searches use <strong>your own key</strong>, saved below.</div>
                    </div>
                @elseif($source === \App\Services\SerpApiConfigService::SOURCE_ENV)
                    <div class="alert alert-info fs-13 d-flex gap-2 align-items-start">
                        <i class="bi bi-info-circle-fill mt-1"></i>
                        <div>
                            Searches use the <strong>server's key</strong> (<code>SERPAPI_KEY</code> in <code>.env</code>)
                            @if($hasOwnKey && ! $isActive) - your saved key is switched off @endif.
                            Save your own key to search on your own SerpAPI account.
                        </div>
                    </div>
                @else
                    <div class="alert alert-warning fs-13 d-flex gap-2 align-items-start">
                        <i class="bi bi-exclamation-triangle-fill mt-1"></i>
                        <div><strong>No SerpAPI key.</strong> Find Leads and the GMB search cannot run until you add one.</div>
                    </div>
                @endif

                <form method="POST" action="{{ route('serpapi-settings.update') }}" id="serpapiForm" data-test-url="{{ route('serpapi-settings.test') }}">
                    @csrf @method('PUT')

                    <div class="mb-3">
                        <label class="form-label" for="serpApiKey">API Key</label>
                        <input type="password" name="api_key" id="serpApiKey" class="form-control @error('api_key') is-invalid @enderror"
                               autocomplete="new-password" spellcheck="false"
                               placeholder="{{ $hasOwnKey ? '•••••••• (leave blank to keep the saved key)' : 'Paste your SerpAPI key' }}">
                        @error('api_key')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">
                            Find it at <a href="https://serpapi.com/manage-api-key" target="_blank" rel="noopener">serpapi.com/manage-api-key</a>.
                            The free plan has 100 searches a month. Stored encrypted.
                        </div>
                        @if($hasOwnKey)
                            <div class="form-check mt-2">
                                <input type="checkbox" class="form-check-input" name="remove_key" value="1" id="serpRemoveKey">
                                <label class="form-check-label fs-13" for="serpRemoveKey">
                                    Remove my saved key{{ $hasEnvKey ? ' (searches fall back to the server\'s key)' : '' }}
                                </label>
                            </div>
                        @endif
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="serpCountry">Country for Find Leads</label>
                            <input type="text" name="country" id="serpCountry" maxlength="2"
                                   class="form-control @error('country') is-invalid @enderror"
                                   value="{{ old('country', $setting->country ?? '') }}" placeholder="e.g. in, us, uk - blank = worldwide">
                            @error('country')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Google's <code>gl</code> - <a href="https://serpapi.com/google-countries" target="_blank" rel="noopener">country codes</a>.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="serpLanguage">Language for Find Leads</label>
                            <input type="text" name="language" id="serpLanguage" maxlength="10"
                                   class="form-control @error('language') is-invalid @enderror"
                                   value="{{ old('language', $setting->language ?? '') }}" placeholder="e.g. en, hi - blank = any">
                            @error('language')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Google's <code>hl</code> - <a href="https://serpapi.com/google-languages" target="_blank" rel="noopener">language codes</a>.</div>
                        </div>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input type="checkbox" class="form-check-input" name="is_active" value="1" id="serpActive" @checked($isActive)>
                        <label class="form-check-label" for="serpActive">Active (use my key and these defaults for searches)</label>
                    </div>

                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save</button>
                        <button type="button" class="btn btn-light" id="serpTest"><i class="bi bi-plug me-1"></i>Test Key</button>
                        <span class="fs-13" id="serpTestResult" role="status"></span>
                    </div>
                    <div class="form-text mt-2">Test Key checks the key typed above, or the one in use - it costs no search credit.</div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card">
            <div class="card-header py-3">
                <h5 class="card-title mb-0"><i class="bi bi-diagram-3 me-2 text-primary"></i>Where it is used</h5>
            </div>
            <div class="card-body fs-13">
                <ol class="numbered-steps">
                    <li><strong>Leads › Find Leads</strong> - Google results for the keyword, up to 25 leads per search (several credits).</li>
                    <li><strong>GMB Leads › Search</strong> - Google Maps, up to 3 pages per search.</li>
                    <li><strong>AI email writer</strong> - one search about the company before writing.</li>
                </ol>
                <p class="mb-0 mt-3 text-muted">
                    Each user has their own key here. Without one, the server's <code>SERPAPI_KEY</code> is used.
                </p>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    // Test Key: SerpAPI's Account API, through the server (no search credit).
    document.getElementById('serpTest').addEventListener('click', async function () {
        const btn    = this;
        const form   = document.getElementById('serpapiForm');
        const result = document.getElementById('serpTestResult');
        const body   = new FormData();

        body.append('_token', form.querySelector('[name=_token]').value);
        body.append('api_key', document.getElementById('serpApiKey').value.trim());

        btn.disabled       = true;
        result.className   = 'fs-13 text-muted';
        result.textContent = 'Testing…';

        try {
            const res  = await fetch(form.dataset.testUrl, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: body,
            });
            const json = await res.json();
            result.className   = 'fs-13 ' + (json.ok ? 'text-success' : 'text-danger');
            result.textContent = json.message || (res.ok ? 'OK' : 'Request failed.');
        } catch (e) {
            result.className   = 'fs-13 text-danger';
            result.textContent = 'Could not reach the server. Please try again.';
        } finally {
            btn.disabled = false;
        }
    });
</script>
@endpush
