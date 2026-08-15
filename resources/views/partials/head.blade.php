<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="color-scheme" content="light dark">
<meta name="theme-color" media="(prefers-color-scheme: light)" content="#f7f6f8">
<meta name="theme-color" media="(prefers-color-scheme: dark)" content="#131218">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="robots" content="noindex, nofollow">

<title>{{ isset($title) && $title ? $title.' · '.config('app.name') : config('app.name') }}</title>

@include('partials.theme-script')

@vite(['resources/css/app.css', 'resources/js/app.js'])
