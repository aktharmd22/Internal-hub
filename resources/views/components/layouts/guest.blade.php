@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', ['title' => $title])
</head>
<body class="min-h-dvh bg-canvas text-ink-950 antialiased">
    <div class="min-h-dvh flex flex-col px-5 py-8 sm:place-content-center safe-t safe-b">
        <div class="w-full sm:max-w-sm sm:mx-auto">
            <div class="flex items-center gap-2.5 mb-8">
                <span class="grid place-items-center size-9 rounded-control bg-accent-600 text-on-solid shrink-0">
                    <x-icon name="shield-check" class="size-5" />
                </span>
                <div class="min-w-0">
                    <p class="t-section text-ink-950 truncate">{{ config('app.name') }}</p>
                    <p class="t-meta text-ink-400">Renewals &amp; client work</p>
                </div>

                <div class="ml-auto" x-data>
                    <x-app.theme-toggle compact />
                </div>
            </div>

            {{ $slot }}
        </div>
    </div>
</body>
</html>
