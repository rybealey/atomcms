<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ __('Payment received') }}</title>
    <style>
        html, body { margin: 0; height: 100%; background: #0f1419; color: #e6edf3; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; }
        body { display: flex; align-items: center; justify-content: center; }
        .card { text-align: center; padding: 32px 28px; max-width: 360px; }
        .check { width: 64px; height: 64px; margin: 0 auto 16px; border-radius: 50%; background: #2ea043; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 36px; font-weight: bold; }
        h1 { margin: 0 0 8px; font-size: 20px; font-weight: 600; }
        p { margin: 4px 0; color: #8b949e; font-size: 14px; line-height: 1.5; }
        .small { margin-top: 18px; font-size: 12px; color: #6e7681; }
    </style>
</head>
<body>
    <div class="card">
        <div class="check">&#10003;</div>
        <h1>{{ __('Payment received') }}</h1>
        <p>{{ __('Your diamonds will appear in-game shortly.') }}</p>
        <p class="small">{{ __('This window will close automatically.') }}</p>
    </div>
    <script>setTimeout(function () { window.close(); }, 1800);</script>
</body>
</html>
