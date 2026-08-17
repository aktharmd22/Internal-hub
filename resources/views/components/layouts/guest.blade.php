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
                <x-app.brand size="lg" subtitle="Renewals &amp; client work" class="flex-1" />

                <div class="ml-auto" x-data>
                    <x-app.theme-toggle compact />
                </div>
            </div>

            {{ $slot }}
        </div>
    </div>
</body>
</html>
