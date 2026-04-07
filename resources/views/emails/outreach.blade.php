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
        <img src="{{ config('app.url') }}/images/hes-email-logo.png" alt="{{ $senderCompany }}">
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

        

        <div class="footer-contact-info">
            <div>Shop No. 25, Modipuram, Meerut, Uttar Pradesh - 250110, India</div>
            <div>
                <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a>
                &nbsp;|&nbsp;
                <a href="tel:+918864939301">+91 88649 39301</a>
                &nbsp;|&nbsp;
                <a href="tel:+918439913891">+91 84399 13891</a>
            </div>
            <div>
                <a href="http://heservices.in/" style="color:#4361ee; text-decoration:underline;">heservices.in</a>
            </div>
        </div>

        <hr class="footer-divider-line">

        <div class="footer-social">
            <a href="#" title="LinkedIn">in</a>
            <a href="#" title="Facebook">f</a>
            <a href="#" title="X">X</a>
            <a href="#" title="YouTube" style="font-size:16px;">&#9654;</a>
        </div>

        <div class="footer-copy">
            &copy; {{ date('Y') }} {{ $senderCompany }}. All rights reserved.
        </div>

    </div>

</div>

</body>
</html>
