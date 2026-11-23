<!doctype html>
<html lang="da">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#7d598b">
    <link rel="icon" href="/favicon.ico?v={{ @filemtime(public_path('favicon.ico')) }}" sizes="48x48">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png?v={{ @filemtime(public_path('apple-touch-icon.png')) }}">
    <link rel="manifest" href="/manifest.webmanifest?v={{ @filemtime(public_path('manifest.webmanifest')) }}">
    <title>SurfHorsens – adgang</title>
    <style>
        /* Klubbens farver (designguiden); lys eller mørk efter enheden */
        :root { color-scheme: light; --bg:#fce6e8; --card:#fffef0; --ink:#575756; --ink2:#575756; --line:#e8d5d6; --accent:#7d598b; --accentink:#fffef0; --bad:#825c6d; --badsoft:#fbe2e7; }
        @media (prefers-color-scheme: dark) { :root { color-scheme: dark; --bg:#575756; --card:#575756; --ink:#fffef0; --ink2:#ddddd1; --line:#7c7c78; --accent:#f8c760; --accentink:#575756; --bad:#f8d0e1; --badsoft:#6c5a62; } }
        * { box-sizing: border-box; }
        body { margin:0; min-height:100vh; display:grid; place-items:center; background:var(--bg); color:var(--ink); font:15px/1.45 Futura, "Futura PT", Arial, sans-serif; padding:24px 16px; }
        .box { width:100%; max-width:380px; text-align:center; }
        img { width:64px; height:64px; border-radius:16px; }
        h1 { font-size:1.4rem; margin:14px 0 4px; }
        p { color:var(--ink2); margin:0 0 18px; }
        form { background:var(--card); border:1px solid var(--line); border-radius:14px; padding:18px; text-align:left; }
        label { font-size:.82rem; font-weight:600; color:var(--ink2); }
        input { width:100%; font:inherit; color:var(--ink); background:var(--card); border:1px solid var(--line); border-radius:10px; padding:10px 12px; margin:6px 0 14px; min-height:44px; }
        input:focus { outline:2px solid var(--accent); outline-offset:-1px; }
        button { width:100%; min-height:44px; border:0; border-radius:10px; background:var(--accent); color:var(--accentink); font:inherit; font-weight:600; cursor:pointer; }
        .err { background:var(--badsoft); color:var(--bad); border-radius:10px; padding:9px 12px; margin-bottom:12px; font-size:.9rem; }
        small { display:block; color:var(--ink2); margin-top:14px; }
        h1 { color: var(--accent); }
        .logo-klub { display:inline-block; width:200px; height:auto; border-radius:0; }
    </style>
</head>
<body>
<div class="box">
    <img class="logo-klub" src="/brand/surfhorsens-logo.webp" alt="SurfHorsens">
    <h1>SurfHorsens</h1>
    <p>Proof of concept – kun for bestyrelsen.</p>
    <form method="post" action="/adgang">
        @csrf
        <input type="hidden" name="til" value="{{ $to }}">
        @if ($error)<div class="err" role="alert">{{ $error }}</div>@endif
        <label for="code">Adgangskode til siden</label>
        <input id="code" name="code" type="password" autocomplete="current-password" required autofocus>
        <button type="submit">Fortsæt</button>
    </form>
    <small>Koden huskes på denne enhed i 90 dage.</small>
</div>
</body>
</html>
