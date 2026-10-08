<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">

    <title>@yield('title', 'Sign in') — AI Client Finder</title>

    @php $logo = asset('images/sabright-logo.png').'?v='.filemtime(public_path('images/sabright-logo.png')); @endphp
    <link rel="icon" type="image/png" href="{{ $logo }}">

    <link class="main-plugins" href="{{ asset('assets/css/plugins.css') }}" rel="stylesheet">
    <link class="main-css" href="{{ asset('assets/css/style.css') }}" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('assets/css/app-custom.css') }}?v={{ filemtime(public_path('assets/css/app-custom.css')) }}" rel="stylesheet">

    <style>
        body { background: var(--bs-light, #f5f6fa); }
        .auth-wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1.5rem 1rem; }
        .auth-card { width: 100%; max-width: 26rem; }
        .auth-logo { display: block; margin: 0 auto 1.25rem; max-width: 13rem; height: auto; mix-blend-mode: multiply; /* logo has a white backdrop; let the page colour show through */ }
        /* Mobile: tighter gutters so the form is not squeezed. */
        @media (max-width: 575.98px) {
            .auth-wrap { padding: 1rem 0.75rem; }
            .auth-card .card-body { padding: 1.25rem !important; }
        }
    </style>
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card">
        <img class="auth-logo" src="{{ $logo }}" alt="SabRight">

        <div class="card">
            <div class="card-body p-4">
                <h4 class="mb-1">@yield('heading')</h4>
                <p class="text-muted fs-13 mb-4">@yield('subheading')</p>

                @if (session('success'))
                    <div class="alert alert-success py-2 fs-13">{{ session('success') }}</div>
                @endif

                @yield('content')
            </div>
        </div>

        <p class="text-center text-muted fs-13 mt-3 mb-0">&copy; {{ date('Y') }} AI Client Finder</p>
    </div>
</div>
</body>
</html>
