@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', ['title' => $title])
</head>
<body class="min-h-dvh bg-canvas text-ink-950 antialiased">
    <a
        href="#main"
        class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-3 focus:rounded-control focus:border focus:border-ink-200 focus:bg-surface focus:px-3 focus:py-2 focus:t-sub"
    >Skip to content</a>

    <div class="flex min-h-dvh">
        <x-app.sidebar />

        <div class="flex min-w-0 flex-1 flex-col">
            <x-app.topbar :title="$title">{{ $actions ?? '' }}</x-app.topbar>

            <main id="main" class="flex-1 pb-[calc(4.5rem+env(safe-area-inset-bottom))] lg:pb-10">
                {{ $slot }}
            </main>
        </div>
    </div>

    <x-app.bottom-nav />

    @include('partials.toasts')
</body>
</html>
