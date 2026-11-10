<!doctype html>
<html lang="da">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0c4a5e">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Klubben">
    <title>Klubsystem</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/icon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/icon-192.png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    @if (config('app.test_banner'))
    <div class="test-ribbon">TESTUDGAVE · beskeder mellem medlemmer · data her påvirker ikke <a href="https://klub.rpix.dk">klub.rpix.dk</a></div>
    @endif
    <div id="app"></div>
</body>
</html>
