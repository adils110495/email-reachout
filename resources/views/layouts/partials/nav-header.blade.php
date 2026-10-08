@php
    // filemtime busts the browser cache whenever the logo file is replaced.
    $brandLogo = asset('images/sabright-logo.png').'?v='.filemtime(public_path('images/sabright-logo.png'));
@endphp

{{-- Start - Nav Header --}}
<div class="nav-header">
    <a href="{{ route('dashboard') }}" class="brand-logo" aria-label="SabRight">
        {{-- Collapsed sidebar: the same artwork, cropped to the "B" mark (see app-custom.css) --}}
        <img class="logo-abbr" src="{{ $brandLogo }}" alt="">
        <img class="brand-title" src="{{ $brandLogo }}" alt="SabRight">
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
