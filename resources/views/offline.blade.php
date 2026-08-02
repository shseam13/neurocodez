{{--
    Offline fallback, served by the service worker when a navigation fails.

    Deliberately says "offline" rather than showing cached figures. On a system
    that tracks money, a stale balance presented as current is worse than no
    balance at all.

    Self-contained: no @vite, because the stylesheet may not be cached and this
    page has to render with nothing available.
--}}
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Offline — Neuro Codez</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background: #1e073a;
            color: #fff;
            font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            -webkit-font-smoothing: antialiased;
            text-align: center;
        }
        .card {
            max-width: 26rem;
            padding: 2.5rem 2rem;
            border-radius: 1rem;
            background: rgb(255 255 255 / 0.07);
            border: 1px solid rgb(255 255 255 / 0.15);
            box-shadow: inset 0 1px 0 0 rgb(255 255 255 / 0.22), 0 8px 32px rgb(0 0 0 / 0.25);
        }
        img { width: 44px; height: 44px; }
        h1 { margin: 1.25rem 0 0; font-size: 1.25rem; letter-spacing: -0.01em; }
        p { margin: 0.75rem 0 0; line-height: 1.6; color: rgb(244 240 250 / 0.66); font-size: 0.9375rem; }
        button {
            margin-top: 1.75rem;
            padding: 0.7rem 1.5rem;
            border: 0;
            border-radius: 0.75rem;
            background: #914ee9;
            color: #fff;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
        }
        button:hover { background: #a063ef; }
    </style>
</head>
<body>
    <main class="card">
        <img src="/brand/logo-mark-white-128.png" alt="Neuro Codez">

        <h1>You're offline</h1>
        <p>
            This page needs a connection. Nothing is shown from an old copy —
            on figures you might be acting on, out-of-date is worse than absent.
        </p>

        <button type="button" onclick="location.reload()">Try again</button>
    </main>

    <script>
        // Reload automatically the moment the connection returns.
        window.addEventListener('online', () => location.reload());
    </script>
</body>
</html>
