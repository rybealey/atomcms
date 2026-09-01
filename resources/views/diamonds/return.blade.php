<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">

    <title>{{ setting('hotel_name') }} - Diamonds</title>

    <link href="https://fonts.googleapis.com/css2?family=Ubuntu+Condensed&display=swap" rel="stylesheet">

    <style>
        :root {
            color-scheme: dark;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            font-family: 'Ubuntu Condensed', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            background: radial-gradient(circle at 50% 0%, #2a2350 0%, #14122b 55%, #0d0b1c 100%);
            color: #f4f2ff;
        }

        .card {
            width: 100%;
            max-width: 420px;
            background: rgba(20, 18, 43, 0.72);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 18px;
            padding: 40px 32px;
            text-align: center;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.45);
        }

        .badge {
            width: 74px;
            height: 74px;
            margin: 0 auto 22px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 38px;
            line-height: 1;
        }

        .badge.success {
            background: rgba(52, 211, 153, 0.16);
            color: #34d399;
        }

        .badge.canceled {
            background: rgba(148, 163, 184, 0.16);
            color: #cbd5e1;
        }

        h1 {
            margin: 0 0 12px;
            font-size: 26px;
            letter-spacing: 0.3px;
        }

        p {
            margin: 0 0 10px;
            font-size: 17px;
            line-height: 1.5;
            color: rgba(244, 242, 255, 0.82);
        }

        .hint {
            margin-top: 18px;
            font-size: 15px;
            color: rgba(244, 242, 255, 0.55);
        }

        button {
            margin-top: 26px;
            width: 100%;
            padding: 13px 18px;
            font-family: inherit;
            font-size: 17px;
            font-weight: 600;
            letter-spacing: 0.3px;
            color: #14122b;
            background: #a78bfa;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: filter 0.15s ease;
        }

        button:hover {
            filter: brightness(1.06);
        }
    </style>
</head>

<body>
    <div class="card">
        @if ($canceled)
            <div class="badge canceled">&#10005;</div>
            <h1>Checkout canceled</h1>
            <p>No payment was taken. You can close this tab and try again from the Diamonds store whenever you're ready.</p>
        @else
            <div class="badge success">&#10003;</div>
            <h1>Payment received</h1>
            <p>Thanks for supporting {{ setting('hotel_name') }}!</p>
            <p>Your diamonds are on their way and will appear in-game within a moment.</p>
        @endif

        <p class="hint">You can close this tab and switch back to your game.</p>

        <button type="button" onclick="window.close()">Close this tab</button>
    </div>
</body>

</html>
