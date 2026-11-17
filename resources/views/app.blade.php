<!doctype html>
<html lang="da">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#7e5a8c">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="SurfHorsens">
    <title>SurfHorsens</title>
    <link rel="manifest" href="/manifest.webmanifest?v={{ @filemtime(public_path('manifest.webmanifest')) }}">
    <link rel="icon" href="/favicon.ico?v={{ @filemtime(public_path('favicon.ico')) }}" sizes="48x48">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png?v={{ @filemtime(public_path('apple-touch-icon.png')) }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    @if (config('app.test_banner'))
    <div class="test-ribbon">TESTUDGAVE · nye funktioner afprøves her · data her påvirker ikke <a href="https://klub.rpix.dk">klub.rpix.dk</a></div>
    @endif
    <div id="app"></div>
</body>
</html>
