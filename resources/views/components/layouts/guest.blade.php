@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', ['title' => $title])
</head>
<body class="min-h-dvh auth-backdrop text-ink-950 antialiased">
    {{-- Theme control sits out of the way in the corner rather than competing
         with the logo for the top of the page. --}}
    <div class="fixed top-0 right-0 z-10 p-3 safe-t" x-data>
        <x-app.theme-toggle compact />
    </div>

    <div class="min-h-dvh flex flex-col px-5 safe-t safe-b">
        {{-- Optically centred: slightly above true centre reads as balanced,
             and it keeps the form clear of the on-screen keyboard. --}}
        <div class="flex-1 flex flex-col justify-center py-12 lg:py-16">
            <div class="w-full max-w-[380px] mx-auto auth-card-halo">

                <div class="flex flex-col items-center text-center mb-8">
                    <x-app.brand size="xl" cap="max-w-[220px]" :show-name="false" center />

                    @unless (\App\Support\Brand::isWordmark())
                        <p class="t-section text-ink-950 mt-3">{{ \App\Support\Brand::name() }}</p>
                    @endunless

                    <p class="t-sub text-ink-400 mt-2">Renewals, assets and client work</p>
                </div>

                {{ $slot }}
            </div>
        </div>

        <footer class="pb-6 text-center">
            <p class="t-meta text-ink-400">
                &copy; {{ now()->year }} {{ \App\Support\Brand::name() }}
            </p>
        </footer>
    </div>
</body>
</html>
