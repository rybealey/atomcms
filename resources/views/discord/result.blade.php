<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $state === 'success' ? __('Discord connected') : __('Discord') }}</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #1e2124;
            color: #f4f4f5;
            font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
            text-align: center;
            padding: 24px;
        }
        .card { max-width: 380px; }
        h1 { font-size: 20px; margin: 0 0 12px; }
        p { font-size: 14px; line-height: 1.5; color: #b9bbbe; margin: 0; }
        .ok { color: #43b581; }
        .bad { color: #f04747; }
    </style>
</head>
<body>
    <div class="card">
        <h1 class="{{ $state === 'success' ? 'ok' : 'bad' }}">
            {{ $state === 'success' ? __('Discord connected') : __('Something went wrong') }}
        </h1>
        <p>{{ $message }}</p>
    </div>

    @if ($autoClose)
        <script>
            // Opened from the game client, so closing is permitted. If the
            // page was reached some other way, the close is a no-op and the
            // message above stands on its own.
            setTimeout(() => window.close(), 1200);
        </script>
    @endif
</body>
</html>
