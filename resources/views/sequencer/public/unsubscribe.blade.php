<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>Unsubscribe</title>
    <style>
        :root { --bg:#f5f6fa; --card:#fff; --text:#1c1d21; --muted:#60636b; --accent:#2a78d6; --border:#e3e5ea; }
        @media (prefers-color-scheme: dark) { :root { --bg:#141517; --card:#1d1f22; --text:#f2f3f5; --muted:#a3a6ae; --accent:#5aa0f0; --border:#2e3136; } }
        body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; background:var(--bg); color:var(--text); font:16px/1.5 system-ui,-apple-system,Segoe UI,Roboto,sans-serif; padding:16px; }
        main { width:100%; max-width:26rem; background:var(--card); border:1px solid var(--border); border-radius:12px; padding:28px; box-sizing:border-box; }
        h1 { font-size:1.25rem; margin:0 0 .5rem; } p { color:var(--muted); margin:.25rem 0 1.25rem; }
        button { width:100%; padding:.7rem 1rem; font:inherit; font-weight:600; color:#fff; background:var(--accent); border:0; border-radius:8px; cursor:pointer; }
        button:focus-visible { outline:3px solid var(--accent); outline-offset:2px; }
        strong { color:var(--text); }
    </style>
</head>
<body>
<main>
@if ($done)
    <h1>You are unsubscribed</h1>
    <p><strong>{{ $email }}</strong> will not receive any more emails from this sender. This can take a moment to apply to messages already queued, but none will be delivered.</p>
@else
    <h1>Unsubscribe</h1>
    <p>Stop all further emails to <strong>{{ $email }}</strong>?</p>
    <form method="POST" action="{{ route('sequencer.unsubscribe.perform', $token) }}">
        @csrf
        <button type="submit">Yes, unsubscribe me</button>
    </form>
@endif
</main>
</body>
</html>
