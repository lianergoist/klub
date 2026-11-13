<!doctype html>
<html lang="da">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#7e5a8c">
    <link rel="icon" href="/icon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/icon-192.png">
    <link rel="manifest" href="/manifest.webmanifest">
    <title>Klubsystem – adgang</title>
    <style>
        :root { color-scheme: light; --bg:#f4f6f5; --card:#fff; --ink:#10201f; --ink2:#4a5a58; --line:#dde3e1; --accent:#0f7c8c; --bad:#b42318; --badsoft:#fde8e6; }
        @media (prefers-color-scheme: dark) { :root { color-scheme: dark; --bg:#0d1716; --card:#15211f; --ink:#e9efee; --ink2:#b3c0be; --line:#2a3a38; --accent:#4cc2cf; --bad:#ff8a80; --badsoft:#3a1714; } }
        * { box-sizing: border-box; }
        body { margin:0; min-height:100vh; display:grid; place-items:center; background:var(--bg); color:var(--ink); font:15px/1.45 ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; padding:24px 16px; }
        .box { width:100%; max-width:380px; text-align:center; }
        img { width:64px; height:64px; border-radius:16px; }
        h1 { font-size:1.4rem; margin:14px 0 4px; }
        p { color:var(--ink2); margin:0 0 18px; }
        form { background:var(--card); border:1px solid var(--line); border-radius:14px; padding:18px; text-align:left; }
        label { font-size:.82rem; font-weight:600; color:var(--ink2); }
        input { width:100%; font:inherit; color:var(--ink); background:var(--card); border:1px solid var(--line); border-radius:10px; padding:10px 12px; margin:6px 0 14px; min-height:44px; }
        input:focus { outline:2px solid var(--accent); outline-offset:-1px; }
        button { width:100%; min-height:44px; border:0; border-radius:10px; background:var(--accent); color:#fff; font:inherit; font-weight:600; cursor:pointer; }
        .err { background:var(--badsoft); color:var(--bad); border-radius:10px; padding:9px 12px; margin-bottom:12px; font-size:.9rem; }
        small { display:block; color:var(--ink2); margin-top:14px; }
        html[data-tema=klub] { --bg:#e3d8d3; --card:#fbf7f5; --ink:#33243a; --ink2:#5e4a66; --line:#d6c8c2; --accent:#7e5a8c; }
        @media (prefers-color-scheme: dark) { html[data-tema=klub] { --bg:#1d1520; --card:#291f2e; --ink:#f3ebef; --ink2:#cbbcd0; --line:#45364c; --accent:#a274c0; } }
        html[data-tema=klub] h1 { color: var(--accent); }
        .logo-klub { display:inline-block; width:200px; height:auto; border-radius:0; } .logo-std { display:none; }
    </style>
    <script>
        (function () {
            var t = new URLSearchParams(location.search).get('tema');
            var m = /[?&]tema=(klub|standard)/.exec(decodeURIComponent(new URLSearchParams(location.search).get('til') || ''));
            try { t = t || (m && m[1]) || localStorage.getItem('tema'); } catch (e) {}
            if (!t || t === 'klub') document.documentElement.dataset.tema = 'klub'; // SurfHorsens er standard
        })();
    </script>
</head>
<body>
<div class="box">
    <img class="logo-std" src="/icon.svg" alt="">
    <img class="logo-klub" src="/brand/surfhorsens-logo.webp" alt="SurfHorsens">
    <h1>Klubsystem</h1>
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
