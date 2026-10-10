<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subjectLine }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 15px;
            line-height: 1.7;
            color: #222222;
            background-color: #f4f4f4;
        }
        .email-wrapper {
            max-width: 620px;
            margin: 30px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        /* ── HEADER ── */
        .email-header {
            background-color: #ffffff;
            padding: 24px 36px;
            text-align: center;
            border-bottom: 1px solid #eeeeee;
        }
        .email-header img {
            max-height: 60px;
            width: auto;
        }
        .header-divider {
            height: 4px;
            background: linear-gradient(90deg, #4361ee, #3a0ca3, #7209b7);
        }

        /* ── BODY ── */
        .email-body {
            padding: 36px 40px;
            color: #333333;
        }
        .email-body p {
            margin-bottom: 14px;
            font-size: 15px;
            line-height: 1.8;
        }
        .email-body p:last-child {
            margin-bottom: 0;
        }

        /* ── FOOTER ── */
        .email-footer {
            background-color: #f7f7f7;
            padding: 32px 40px 24px;
            text-align: center;
            border-top: 1px solid #e8e8e8;
        }
        .footer-logo img {
            max-height: 48px;
            width: auto;
            margin-bottom: 16px;
        }
        .footer-tagline {
            font-size: 12px;
            color: #666666;
            max-width: 420px;
            margin: 0 auto 20px;
            line-height: 1.7;
        }
        .footer-social {
            margin-bottom: 20px;
        }
        .footer-social a {
            display: inline-block;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: #3d3d5c;
            text-decoration: none;
            margin: 0 4px;
            vertical-align: middle;
            text-align: center;
            line-height: 36px;
            font-size: 13px;
            font-weight: bold;
            color: #ffffff !important;
            font-family: Arial, sans-serif;
        }
        .footer-social a:hover, .footer-social a:visited, .footer-social a:active {
            color: #ffffff !important;
            text-decoration: none;
        }
        .footer-links {
            font-size: 12px;
            margin-bottom: 16px;
        }
        .footer-links a {
            color: #4361ee;
            text-decoration: underline;
            margin: 0 6px;
        }
        .footer-copy {
            font-size: 11px;
            color: #222222;
            margin-top: 8px;
        }
        .footer-contact-info {
            margin-bottom: 16px;
            font-size: 12px;
            color: #222222;
            line-height: 1.9;
            text-align: center;
        }
        .footer-contact-info a {
            color: #222222;
            text-decoration: none;
        }
        .footer-divider-line {
            border: none;
            border-top: 1px solid #e0e0e0;
            margin: 16px 0;
        }
    </style>
</head>
<body>

<div class="email-wrapper">

    {{-- ── HEADER ── --}}
    <div class="email-header">
        {{-- Logo from Settings > Branding, embedded inline (cid:) so it shows even when APP_URL
             is not publicly reachable (e.g. localhost). render() - used for the IMAP Sent-folder
             copy - turns the cid into a data: URI. The URL is only a fallback without $message. --}}
        <img src="{{ isset($message) ? $message->embed($logoPath) : $logoUrl }}" alt="{{ $senderCompany }}">
    </div>
    <div class="header-divider"></div>

    {{-- ── BODY ── --}}
    <div class="email-body">
        @foreach(explode("\n", $emailBody) as $line)
            @if(trim($line))
                <p>{{ $line }}</p>
            @endif
        @endforeach
    </div>

    {{-- ── FOOTER ── --}}
    <div class="email-footer">

        <!-- <div class="footer-tagline">
            You have received this email because you expressed interest in engineering services
            or were identified as a potential partner for {{ $senderCompany }}.
        </div> -->

        

        {{-- Address picked in the compose modal, else the first active one (Settings > Addresses) --}}
        @if($footerAddress)
            <div class="footer-contact-info">
                <div>{{ $footerAddress->address }}</div>
                @php
                    $contacts = array_filter([
                        $footerAddress->email           ? '<a href="mailto:'.e($footerAddress->email).'">'.e($footerAddress->email).'</a>' : null,
                        $footerAddress->phone           ? '<a href="tel:'.e($footerAddress->phone).'">'.e($footerAddress->phone).'</a>' : null,
                        $footerAddress->alternate_phone ? '<a href="tel:'.e($footerAddress->alternate_phone).'">'.e($footerAddress->alternate_phone).'</a>' : null,
                    ]);
                @endphp
                @if($contacts)
                    <div>{!! implode('&nbsp;|&nbsp;', $contacts) !!}</div>
                @endif
                @if($footerAddress->website)
                    <div>
                        <a href="{{ $footerAddress->website }}" style="color:#4361ee; text-decoration:underline;">
                            {{ parse_url($footerAddress->website, PHP_URL_HOST) ?: $footerAddress->website }}
                        </a>
                    </div>
                @endif
            </div>
        @endif

        {{-- Social links from Settings > Branding; an icon is shown only when its URL is set --}}
        @if($socialLinks)
            @if($footerAddress)
                <hr class="footer-divider-line">
            @endif

            <div class="footer-social">
                @foreach($socialLinks as $social)
                    <a href="{{ $social['url'] }}" title="{{ $social['label'] }}"
                       @if($social['label'] === 'YouTube') style="font-size:16px;" @endif>{!! $social['glyph'] !!}</a>
                @endforeach
            </div>
        @endif

        <div class="footer-copy">
            &copy; {{ date('Y') }} {{ $senderCompany }}. All rights reserved.
        </div>

    </div>

</div>

@if(! empty($trackingToken))
    {{-- Open tracking pixel --}}
    <img src="{{ route('track.open', $trackingToken) }}" width="1" height="1" alt="" style="border:0;">
@endif

</body>
</html>
