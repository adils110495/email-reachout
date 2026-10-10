<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">

    <title>@yield('title', 'AI Client Finder')</title>

    {{-- Favicon: the admin icon from Settings > Branding (falls back to the logo).
         The ?v= filemtime busts the browser's (very sticky) favicon cache on replacement. --}}
    @php $favicon = \App\Models\AppSetting::adminIconUrl(); @endphp
    <link rel="icon" type="image/png" href="{{ $favicon }}">
    <link rel="shortcut icon" type="image/png" href="{{ $favicon }}">
    <link rel="apple-touch-icon" href="{{ $favicon }}">

    {{-- Theme plugins stylesheet --}}
    <link href="{{ asset('assets/vendor/metismenu/dist/metisMenu.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendor/bootstrap-select/dist/css/bootstrap-select.min.css') }}" rel="stylesheet">
    <link class="main-switcher" href="{{ asset('assets/css/switcher.css') }}" rel="stylesheet">

    {{-- Theme core stylesheet --}}
    <link class="main-plugins" href="{{ asset('assets/css/plugins.css') }}" rel="stylesheet">
    <link class="main-css" href="{{ asset('assets/css/style.css') }}" rel="stylesheet">

    {{-- Bootstrap Icons 1.11.3 — the copy bundled with the theme is older and is
         missing glyphs the app uses (bi-send, bi-plus-lg, bi-envelope-paper,
         bi-file-earmark-pdf-fill, bi-person-workspace). Loaded after style.css so
         its @font-face and class rules win. To go fully local, drop 1.11.3 into
         public/assets/icons/bootstrap-icons/ and point this link at it. --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    {{-- Select2. The theme skins it in plugins.css but does not bundle the library. --}}
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">

    {{-- App overrides. filemtime busts the browser cache whenever the file changes. --}}
    <link href="{{ asset('assets/css/app-custom.css') }}?v={{ filemtime(public_path('assets/css/app-custom.css')) }}" rel="stylesheet">

    @stack('styles')
</head>
<body>

{{-- Start - Preloader --}}
<div id="preloader">
    <div class="lds-ripple">
        <div></div>
        <div></div>
    </div>
</div>
{{-- End - Preloader --}}

{{-- Start - Main Wrapper --}}
<div id="main-wrapper">

    @include('layouts.partials.nav-header')

    @include('layouts.partials.header')

    @include('layouts.partials.sidebar')

    {{-- Start - Content Body --}}
    <main class="content-body">

        @include('layouts.partials.page-title')

        <div class="container-fluid">

            {{-- Flash messages --}}
            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @yield('content')
        </div>
    </main>
    {{-- End - Content Body --}}

    @include('layouts.partials.footer')

</div>
{{-- End - Main Wrapper --}}

{{-- Start - Page Scripts --}}
<script>
    // Consumed by deznav-init.js so theme stylesheets resolve from any URL depth.
    window.THEME_ASSET_BASE = "{{ asset('assets') }}/";
</script>
<script src="{{ asset('assets/vendor/jquery/dist/jquery.min.js') }}"></script>
<script src="{{ asset('assets/vendor/bootstrap/dist/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('assets/vendor/bootstrap-select/dist/js/bootstrap-select.min.js') }}"></script>
<script src="{{ asset('assets/vendor/metismenu/dist/metisMenu.min.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="{{ asset('assets/vendor/i18n/i18n.js') }}"></script>
<script src="{{ asset('assets/js/translator.js') }}"></script>
<script src="{{ asset('assets/js/deznav-init.js') }}"></script>
<script>
    // deznav-init.js defaults the header bar to color_12 (#2c2c2c); run it light
    // so the hamburger lines render dark. The nav header's white (the logo's
    // dark "SA" and tagline need a light background) is owned by
    // app-custom.css (.nav-header).
    // Mutating the shared options object keeps this applied on the theme's resize re-init.
    Object.assign(dzSettingsOptions, { headerBg: 'color_1' });
    new dzSettings(dzSettingsOptions);
</script>
<script src="{{ asset('assets/js/custom.js') }}"></script>
{{-- Loaded after custom.js: it reacts to the .menu-toggle class that custom.js
     sets, so it has to bind after the theme's own hamburger handler. --}}
<script src="{{ asset('assets/js/mobile-nav.js') }}?v={{ filemtime(public_path('assets/js/mobile-nav.js')) }}"></script>
<script src="{{ asset('assets/js/ajax-filters.js') }}"></script>

{{-- Row-action menus live inside .table-responsive, whose overflow clips an
     absolutely positioned dropdown (worst with one or two rows). Popper's
     "fixed" strategy lets the menu escape the scroller. Bootstrap ignores a
     data-bs-strategy attribute, so the instance is created here - in the
     capture phase, before Bootstrap's own click handler would create it
     without this option. --}}
<script>
    document.addEventListener('click', function (e) {
        const toggle = e.target.closest('.table-responsive [data-bs-toggle="dropdown"]');
        if (! toggle || bootstrap.Dropdown.getInstance(toggle)) return;

        bootstrap.Dropdown.getOrCreateInstance(toggle, { popperConfig: { strategy: 'fixed' } });
    }, true);
</script>

@include('layouts.partials.notifications')

@stack('scripts')
{{-- End - Page Scripts --}}
</body>
</html>
