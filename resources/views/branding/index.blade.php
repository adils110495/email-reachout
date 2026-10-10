@extends('layouts.app')

@section('title', 'Branding — Settings')
@section('page-title', 'Branding')

@section('breadcrumb')
    <li class="breadcrumb-item">Settings</li>
    <li class="breadcrumb-item active" aria-current="page">Branding</li>
@endsection

@use('App\Models\AppSetting')

@php
    $items = [
        [
            'key'     => AppSetting::ADMIN_LOGO,
            'title'   => 'Logo',
            'help'    => 'Shown in the sidebar header, on the login page and at the top of every outreach email. A wide logo works best (about 4:1). PNG, JPG or GIF, max 2 MB.',
            'accept'  => '.png,.jpg,.jpeg,.gif',
            'url'     => AppSetting::adminLogoUrl(),
            'default' => 'SabRight logo',
        ],
        [
            'key'     => AppSetting::ADMIN_ICON,
            'title'   => 'Icon',
            'help'    => 'Square mark used for the collapsed sidebar, mobile header and browser tab (favicon). If not set, the logo is used. PNG, JPG or WEBP, max 1 MB.',
            'accept'  => '.png,.jpg,.jpeg,.webp',
            'url'     => AppSetting::adminIconUrl(),
            'default' => 'logo',
        ],
    ];
@endphp

@section('content')

<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header py-3 d-block">
                <h4 class="card-title"><i class="bi bi-image me-2 text-primary"></i>Branding</h4>
                <p class="mb-0 fs-13">Brand name and logo used in the admin panel and in outgoing emails.</p>
            </div>

            <div class="card-body">
                <form method="POST" action="{{ route('branding.update') }}" enctype="multipart/form-data">
                    @csrf @method('PUT')

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="form-label" for="company_name">Brand Name <span class="text-danger">*</span></label>
                            <input type="text" name="{{ AppSetting::COMPANY_NAME }}" id="company_name" maxlength="255" required
                                   class="form-control @error(AppSetting::COMPANY_NAME) is-invalid @enderror"
                                   value="{{ old(AppSetting::COMPANY_NAME, AppSetting::companyName()) }}">
                            @error(AppSetting::COMPANY_NAME)<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Used in the email footer ("© {{ date('Y') }} … All rights reserved."), in AI-written emails and as the logo's alt text.</div>
                        </div>
                    </div>

                    <div class="row">
                        @foreach ($items as $item)
                            @php $custom = AppSetting::isCustom($item['key']); @endphp
                            <div class="col-md-6 mb-4">
                                <div class="border rounded p-3 h-100 d-flex flex-column">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <label class="form-label mb-0 fw-semibold" for="{{ $item['key'] }}">{{ $item['title'] }}</label>
                                        <span class="badge {{ $custom ? 'badge-success light' : 'badge-light' }}">{{ $custom ? 'Custom' : 'Default' }}</span>
                                    </div>

                                    <div class="branding-preview mb-3">
                                        <img src="{{ $item['url'] }}" alt="{{ $item['title'] }}" data-preview="{{ $item['key'] }}">
                                    </div>

                                    <input type="file" name="{{ $item['key'] }}" id="{{ $item['key'] }}" accept="{{ $item['accept'] }}"
                                           class="form-control @error($item['key']) is-invalid @enderror">
                                    @error($item['key'])<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    <div class="form-text mb-2">{{ $item['help'] }}</div>

                                    @if ($custom)
                                        <div class="mt-auto">
                                            <button type="submit" class="btn btn-sm btn-light text-danger"
                                                    form="reset-{{ $item['key'] }}">
                                                <i class="bi bi-arrow-counterclockwise me-1"></i>Reset to {{ $item['default'] }}
                                            </button>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Branding</button>
                </form>

                {{-- Reset forms live outside the upload form (forms cannot nest). --}}
                @foreach ($items as $item)
                    <form method="POST" action="{{ route('branding.reset', $item['key']) }}" id="reset-{{ $item['key'] }}"
                          onsubmit="return confirm('Remove this image and use the default?');">
                        @csrf @method('DELETE')
                    </form>
                @endforeach
            </div>
        </div>

        {{-- Email footer --}}
        <div class="card">
            <div class="card-header py-3 d-block">
                <h4 class="card-title"><i class="bi bi-layout-text-window-reverse me-2 text-primary"></i>Email Footer</h4>
                <p class="mb-0 fs-13">
                    Shown at the bottom of every outreach email. The address, email, phone and website come from
                    <a href="{{ route('addresses.index') }}">Settings &rsaquo; Addresses</a> — the one picked while composing, otherwise the first active address.
                </p>
            </div>

            <div class="card-body">
                <form method="POST" action="{{ route('branding.footer') }}">
                    @csrf @method('PUT')

                    <h6 class="mb-1">Social Links</h6>
                    <p class="fs-13 text-muted mb-3">Only the icons with a URL are shown in the email.</p>
                    <div class="row">
                        @foreach (AppSetting::SOCIALS as $key => $social)
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="{{ $key }}">{{ $social['label'] }}</label>
                                <input type="url" name="{{ $key }}" id="{{ $key }}" maxlength="255"
                                       class="form-control @error($key) is-invalid @enderror"
                                       value="{{ old($key, AppSetting::read($key)) }}" placeholder="https://">
                                @error($key)<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        @endforeach
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Footer</button>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
    .branding-preview {
        height: 7rem;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: .75rem;
        border-radius: .375rem;
        /* checkerboard so transparent logos are visible */
        background: repeating-conic-gradient(#f1f1f4 0% 25%, #ffffff 0% 50%) 50% / 16px 16px;
    }
    .branding-preview img { max-width: 100%; max-height: 100%; object-fit: contain; }
</style>
@endpush

@push('scripts')
<script>
    // Preview the chosen file before saving.
    document.querySelectorAll('input[type=file]').forEach(function (input) {
        input.addEventListener('change', function () {
            const img = document.querySelector('[data-preview="' + input.name + '"]');
            if (img && input.files[0]) {
                img.src = URL.createObjectURL(input.files[0]);
            }
        });
    });
</script>
@endpush
