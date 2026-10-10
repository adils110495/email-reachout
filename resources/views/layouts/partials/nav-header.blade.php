@php
    // Uploaded under Settings > Branding (falls back to the shipped logo).
    $brandLogo = \App\Models\AppSetting::adminLogoUrl();
    $brandIcon = \App\Models\AppSetting::adminIconUrl();
    $hasIcon   = \App\Models\AppSetting::isCustom(\App\Models\AppSetting::ADMIN_ICON);
    $brandName = \App\Models\AppSetting::companyName();
@endphp

{{-- Start - Nav Header --}}
<div class="nav-header">
    <a href="{{ route('dashboard') }}" class="brand-logo" aria-label="{{ $brandName }}">
        {{-- Collapsed sidebar: the uploaded icon, or the logo cropped to its mark (see app-custom.css) --}}
        <img class="logo-abbr {{ $hasIcon ? 'is-icon' : '' }}" src="{{ $brandIcon }}" alt="">
        <img class="brand-title" src="{{ $brandLogo }}" alt="{{ $brandName }}">
    </a>
    <div class="nav-control">
        <div class="hamburger">
            <span class="line"></span>
            <span class="line"></span>
            <span class="line"></span>
        </div>
    </div>
</div>
{{-- End - Nav Header --}}
