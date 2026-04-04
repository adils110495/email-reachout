<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subjectLine }}</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 15px;
            line-height: 1.7;
            color: #222222;
            background-color: #ffffff;
            margin: 0;
            padding: 0;
        }
        .wrapper {
            max-width: 560px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .footer {
            margin-top: 40px;
            padding-top: 16px;
            border-top: 1px solid #e5e5e5;
            font-size: 12px;
            color: #888888;
        }
    </style>
</head>
<body>
<div class="wrapper">

    {{-- AI-generated email body (plain paragraphs) --}}
    @foreach(explode("\n", $emailBody) as $line)
        @if(trim($line))
            <p>{{ $line }}</p>
        @endif
    @endforeach

    <!-- <div class="footer">
        <p>
            This email was sent to {{ $lead->email }} on behalf of
            <strong>{{ $senderName }}</strong> at <strong>{{ $senderCompany }}</strong>.<br>
            If you would prefer not to receive further emails, please reply with "unsubscribe".
        </p>
    </div> -->

</div>
</body>
</html>
