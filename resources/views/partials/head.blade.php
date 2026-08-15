<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="color-scheme" content="light dark">
<meta name="theme-color" media="(prefers-color-scheme: light)" content="#f7f6f8">
<meta name="theme-color" media="(prefers-color-scheme: dark)" content="#131218">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="robots" content="noindex, nofollow">

<title>{{ isset($title) && $title ? $title.' · '.config('app.name') : config('app.name') }}</title>

<link rel="manifest" href="/manifest.webmanifest">
<link rel="icon" href="/icons/icon-192.png" sizes="192x192">
<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">

@include('partials.theme-script')

@auth
    <script>
        window.rgUserId = @json(auth()->id());
        window.rgVapidKey = @json(config('webpush.vapid.public_key'));
    </script>
@endauth

@vite(['resources/css/app.css', 'resources/js/app.js'])
