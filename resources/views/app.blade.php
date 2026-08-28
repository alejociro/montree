<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @if (($page['component'] ?? null) === 'Landing')
            <link rel="preload" as="image" type="image/avif" fetchpriority="high"
                  media="not all and (orientation: portrait) and (max-width: 639px)"
                  href="/landing/cocora-wide-1600.avif"
                  imagesizes="100vw"
                  imagesrcset="/landing/cocora-wide-640.avif 640w, /landing/cocora-wide-960.avif 960w, /landing/cocora-wide-1280.avif 1280w, /landing/cocora-wide-1600.avif 1600w, /landing/cocora-wide-1920.avif 1920w, /landing/cocora-wide-2560.avif 2560w, /landing/cocora-wide-3200.avif 3200w, /landing/cocora-wide-3840.avif 3840w, /landing/cocora-wide-4480.avif 4480w, /landing/cocora-wide-5120.avif 5120w">
            <link rel="preload" as="image" type="image/avif" fetchpriority="high"
                  media="(orientation: portrait) and (max-width: 639px)"
                  href="/landing/cocora-portrait-1080.avif"
                  imagesizes="100vw"
                  imagesrcset="/landing/cocora-portrait-540.avif 540w, /landing/cocora-portrait-720.avif 720w, /landing/cocora-portrait-1080.avif 1080w">
        @endif

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Newsreader:ital,wght@0,400;0,500;0,600;1,400;1,500;1,600&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
