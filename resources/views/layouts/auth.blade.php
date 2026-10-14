<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">

    <title>@yield('title', 'Sign in') — AI Client Finder</title>

    @php
        // Uploaded under Settings > Branding (falls back to the shipped artwork).
        $logo      = \App\Models\AppSetting::adminLogoUrl();
        $icon      = \App\Models\AppSetting::adminIconUrl();
        $brandName = \App\Models\AppSetting::companyName();
    @endphp
    <link rel="icon" type="image/png" href="{{ $icon }}">

    <link class="main-plugins" href="{{ asset('assets/css/plugins.css') }}" rel="stylesheet">
    <link class="main-css" href="{{ asset('assets/css/style.css') }}" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    {{-- Auth page styles live in app-custom.css, section "Auth pages". --}}
    <link href="{{ asset('assets/css/app-custom.css') }}?v={{ filemtime(public_path('assets/css/app-custom.css')) }}" rel="stylesheet">
</head>
<body class="auth-body">
<main class="auth-shell">

    {{-- Brand panel - large screens only. The logo is artwork for a light
         background (dark lettering), so on this dark panel it sits on a white
         chip - whole, whatever logo is uploaded under Settings > Branding. --}}
    <aside class="auth-aside">
        <div class="auth-aside-inner">
            <div class="auth-brand">
                <img src="{{ $logo }}" alt="{{ $brandName }}">
            </div>

            <div class="auth-pitch">
                <span class="auth-eyebrow"><i class="bi bi-stars" aria-hidden="true"></i>AI Client Finder</span>
                <h1 class="auth-headline">Find the right clients.<br><span class="auth-accent">Reach them first.</span></h1>
                <p class="auth-lead">Discover businesses, uncover their contact emails and send personalised outreach — all from one focused workspace.</p>

                <ul class="auth-features">
                    <li>
                        <span class="auth-feature-icon"><i class="bi bi-search" aria-hidden="true"></i></span>
                        <div class="auth-feature-text">
                            <strong>Find leads &amp; emails</strong>
                            <span>Search by keyword or domain and pull published addresses straight from company websites.</span>
                        </div>
                    </li>
                    <li>
                        <span class="auth-feature-icon"><i class="bi bi-patch-check" aria-hidden="true"></i></span>
                        <div class="auth-feature-text">
                            <strong>Verify before you send</strong>
                            <span>Syntax, domain and MX checks flag risky addresses before they bounce.</span>
                        </div>
                    </li>
                    <li>
                        <span class="auth-feature-icon"><i class="bi bi-send" aria-hidden="true"></i></span>
                        <div class="auth-feature-text">
                            <strong>Write, send &amp; track</strong>
                            <span>AI-drafted emails with open and reply tracking on every message.</span>
                        </div>
                    </li>
                </ul>
            </div>

            <div>
                <div class="auth-trust">
                    <i class="bi bi-shield-lock" aria-hidden="true"></i>
                    <span>Every account gets its own private workspace — leads, emails and mail settings stay separate.</span>
                </div>
                <p class="auth-aside-foot">&copy; {{ date('Y') }} {{ $brandName }}</p>
            </div>
        </div>
    </aside>

    {{-- Form side --}}
    <section class="auth-main">
        <div class="auth-panel">
            {{-- Small screens only: the brand panel carries the logo on large ones. --}}
            <img class="auth-logo" src="{{ $logo }}" alt="{{ $brandName }}">

            <div class="auth-header">
                <h2 class="auth-title">@yield('heading')</h2>
                <p class="auth-subtitle">@yield('subheading')</p>
            </div>

            @if (session('success'))
                <div class="alert alert-success auth-alert" role="status">
                    <i class="bi bi-check-circle-fill" aria-hidden="true"></i>{{ session('success') }}
                </div>
            @endif

            @yield('content')

            <p class="auth-panel-foot">&copy; {{ date('Y') }} {{ $brandName }}</p>
        </div>
    </section>

</main>

<script>
(function () {
    // ── Show / hide password ─────────────────────────────────────────────
    document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
        var input = document.getElementById(btn.getAttribute('data-toggle-password'));
        if (! input) return;

        btn.addEventListener('click', function () {
            var show = input.type === 'password';

            input.type = show ? 'text' : 'password';
            btn.setAttribute('aria-pressed', show ? 'true' : 'false');
            btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            btn.querySelector('i').className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
            input.focus();
        });
    });

    // ── Caps Lock warning on password fields ─────────────────────────────
    document.querySelectorAll('[data-capslock]').forEach(function (input) {
        var warning = document.getElementById(input.getAttribute('data-capslock'));
        if (! warning) return;

        function check(e) {
            if (e.getModifierState) warning.hidden = ! e.getModifierState('CapsLock');
        }
        input.addEventListener('keydown', check);
        input.addEventListener('keyup', check);
        input.addEventListener('blur', function () { warning.hidden = true; });
    });

    // ── Password strength + confirmation match (sign-up) ─────────────────
    var password = document.querySelector('[data-strength-source]');
    var meter    = document.querySelector('[data-strength-meter]');
    var label    = document.querySelector('[data-strength-label]');
    var confirm  = document.querySelector('[data-match-source]');
    var match    = document.querySelector('[data-match-message]');

    // 0 = shorter than the 6-character minimum the server enforces; 1-4 = weak..strong.
    function score(value) {
        if (value.length < 6) return 0;

        var variety = [/[a-z]/, /[A-Z]/, /\d/, /[^A-Za-z0-9]/].filter(function (re) { return re.test(value); }).length;
        var points  = 1;

        if (value.length >= 10) points++;
        if (variety >= 3) points++;
        if (variety === 4 || value.length >= 14) points++;

        return Math.min(points, 4);
    }

    function updateStrength() {
        if (! password || ! meter) return;

        var value  = password.value;
        var points = score(value);
        var names  = ['Too short', 'Weak', 'Fair', 'Good', 'Strong'];

        meter.setAttribute('data-score', value === '' ? '' : String(points));
        label.textContent = value === '' ? 'Use at least 6 characters.' : 'Strength: ' + names[points];
    }

    function updateMatch() {
        if (! password || ! confirm || ! match) return;

        if (confirm.value === '') {
            match.hidden = true;
            return;
        }

        var ok = confirm.value === password.value;
        match.hidden    = false;
        match.className = 'auth-hint auth-match ' + (ok ? 'is-ok' : 'is-bad');
        match.innerHTML = ok
            ? '<i class="bi bi-check-circle-fill" aria-hidden="true"></i>Passwords match'
            : '<i class="bi bi-x-circle-fill" aria-hidden="true"></i>Passwords do not match yet';
    }

    if (password) {
        password.addEventListener('input', function () { updateStrength(); updateMatch(); });
        updateStrength();
    }
    if (confirm) confirm.addEventListener('input', updateMatch);

    // ── Submit: one click, with feedback ─────────────────────────────────
    document.querySelectorAll('form[data-auth-form]').forEach(function (form) {
        form.addEventListener('submit', function () {
            var btn = form.querySelector('[data-loading-text]');
            if (! btn || btn.disabled) return;

            btn.setAttribute('data-original', btn.innerHTML);
            btn.disabled = true;

            var spinner = document.createElement('span');
            spinner.className = 'spinner-border spinner-border-sm me-2';
            spinner.setAttribute('aria-hidden', 'true');

            btn.textContent = btn.getAttribute('data-loading-text');
            btn.prepend(spinner);
        });
    });

    // Coming back with the browser's Back button restores the page from
    // cache with the button still disabled - put it back.
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('[data-loading-text][data-original]').forEach(function (btn) {
            btn.innerHTML = btn.getAttribute('data-original');
            btn.disabled  = false;
        });
    });
}());
</script>
</body>
</html>
