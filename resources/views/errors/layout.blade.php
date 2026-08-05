<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Something went wrong' }} | {{ config('app.name', 'Ventures Mart') }}</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" sizes="48x48" href="/favicon-48x48.png">
    <link rel="icon" type="image/png" sizes="96x96" href="/favicon-96x96.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/favicon-192x192.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    <meta name="theme-color" content="#ffffff">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Poppins, system-ui, sans-serif;
            background:
                radial-gradient(ellipse 80% 60% at 20% 10%, rgba(230, 30, 77, 0.1), transparent 55%),
                linear-gradient(160deg, #e8eef8 0%, #f5f7fb 45%, #fff 100%);
            color: #1c2c4c;
            display: grid;
            place-items: center;
            padding: 1.5rem;
        }
        .card {
            width: min(100%, 32rem);
            background: #fff;
            border: 1px solid #d9e2f1;
            border-radius: 1.25rem;
            padding: 2rem 1.5rem;
            text-align: center;
            box-shadow: 0 12px 36px rgba(7, 31, 99, 0.1);
        }
        img { max-width: 10rem; height: auto; margin-bottom: 1rem; }
        .code { color: #0b2e8a; font-size: 0.85rem; font-weight: 800; letter-spacing: 0.12em; text-transform: uppercase; margin: 0 0 0.5rem; }
        h1 { margin: 0 0 0.65rem; font-size: clamp(1.4rem, 3vw, 1.85rem); letter-spacing: -0.03em; }
        p { margin: 0 0 1.35rem; color: rgba(28, 44, 76, 0.72); line-height: 1.55; }
        .actions { display: flex; flex-wrap: wrap; gap: 0.65rem; justify-content: center; }
        a {
            display: inline-flex; align-items: center; justify-content: center;
            min-height: 2.75rem; padding: 0.65rem 1.1rem; border-radius: 999px;
            text-decoration: none; font-weight: 800; font-size: 0.92rem;
        }
        .primary { background: #0b2e8a; color: #fff; }
        .ghost { background: transparent; color: #0b2e8a; border: 1px solid #c9d6ea; }
    </style>
</head>
<body>
    <div class="card">
        <img src="/images/ventures-mart-logo.png" alt="{{ config('app.name', 'Ventures Mart') }}">
        <p class="code">{{ $code ?? 500 }}</p>
        <h1>{{ $title ?? 'Something went wrong' }}</h1>
        <p>{{ $body ?? 'Please try again or return home.' }}</p>
        <div class="actions">
            @if (!empty($showRetry))
                <a class="primary" href="javascript:location.reload()">Retry</a>
            @endif
            <a class="{{ !empty($showRetry) ? 'ghost' : 'primary' }}" href="/">Home</a>
            @if (!empty($showShop))
                <a class="ghost" href="/shop">Continue shopping</a>
            @endif
        </div>
    </div>
</body>
</html>
