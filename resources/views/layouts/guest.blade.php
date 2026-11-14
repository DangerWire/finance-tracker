<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Finance Tracker') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=space-grotesk:400,500,600,700|jetbrains-mono:400,500,600" rel="stylesheet" />

        <style>
            body {
                font-family: 'Space Grotesk', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            }
            .font-mono-num {
                font-family: 'JetBrains Mono', monospace;
                font-feature-settings: 'tnum' on, 'zero' on;
            }
        </style>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-[#09090b] text-zinc-100 min-h-screen antialiased selection:bg-emerald-500/20 selection:text-emerald-300 flex flex-col justify-center items-center py-12 px-4 sm:px-6 relative">
        <!-- Subtle Top Ambient Glow -->
        <div class="pointer-events-none fixed inset-0 z-0 sure-radial-glow"></div>

        <div class="relative z-10 w-full max-w-md">
            <div class="flex flex-col items-center mb-6">
                <a href="/" class="flex items-center gap-2.5 group mb-3">
                    <x-application-logo class="h-9 w-9 transition-transform group-hover:scale-105" />
                    <span class="font-bold text-lg tracking-tight text-white">
                        Finance<span class="text-emerald-400">Tracker</span>
                    </span>
                </a>
                <x-language-switcher />
            </div>

            <div class="sure-card rounded-2xl p-6 sm:p-8 border border-white/[0.08] shadow-2xl">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
