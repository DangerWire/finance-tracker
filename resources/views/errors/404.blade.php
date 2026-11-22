<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-[#0d1117]">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 — {{ __('This is not the web page you are looking for') }} | {{ config('app.name', 'Finance Tracker') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

    <!-- Scripts and Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans antialiased text-gray-200 bg-[#0d1117] selection:bg-indigo-500 selection:text-white flex flex-col justify-between overflow-x-hidden">

    <!-- Cosmic Background Elements -->
    <div class="fixed inset-0 pointer-events-none overflow-hidden">
        <div class="absolute -top-40 left-1/2 -translate-x-1/2 w-[1000px] h-[500px] bg-gradient-to-b from-indigo-900/25 via-purple-900/10 to-transparent blur-3xl opacity-70"></div>
        <div class="absolute top-1/4 -left-20 w-80 h-80 bg-blue-900/15 rounded-full blur-3xl"></div>
        <div class="absolute bottom-1/4 -right-20 w-96 h-96 bg-purple-900/15 rounded-full blur-3xl"></div>
        
        <!-- Twinkling Stars Pattern -->
        <svg class="absolute inset-0 w-full h-full opacity-35" xmlns="http://www.w3.org/2000/svg">
            <circle cx="12%" cy="18%" r="1" fill="#fff" opacity="0.8" />
            <circle cx="28%" cy="12%" r="1.5" fill="#fff" opacity="0.6" />
            <circle cx="45%" cy="22%" r="1" fill="#fff" opacity="0.4" />
            <circle cx="65%" cy="15%" r="2" fill="#fff" opacity="0.7" />
            <circle cx="82%" cy="25%" r="1" fill="#fff" opacity="0.5" />
            <circle cx="15%" cy="65%" r="1.5" fill="#fff" opacity="0.6" />
            <circle cx="35%" cy="75%" r="1" fill="#fff" opacity="0.4" />
            <circle cx="72%" cy="60%" r="2" fill="#fff" opacity="0.7" />
            <circle cx="88%" cy="80%" r="1" fill="#fff" opacity="0.5" />
            <circle cx="50%" cy="88%" r="1.5" fill="#fff" opacity="0.6" />
        </svg>
    </div>

    <!-- Header Navigation -->
    <header class="relative z-10 w-full max-w-7xl mx-auto px-6 py-6 flex items-center justify-between">
        <a href="{{ url('/') }}" class="flex items-center gap-2 group">
            <x-application-logo class="w-8 h-8 fill-current text-indigo-400 group-hover:text-indigo-300 transition" />
            <span class="font-bold text-lg text-white tracking-tight group-hover:text-gray-100 transition">
                {{ config('app.name', 'Finance Tracker') }}
            </span>
        </a>

        @if (Route::has('login'))
            <nav class="flex items-center gap-4 text-sm">
                @auth
                    <a href="{{ route('dashboard') }}" class="text-gray-400 hover:text-white transition">
                        {{ __('Dashboard') }}
                    </a>
                @else
                    <a href="{{ route('login') }}" class="text-gray-400 hover:text-white transition">
                        {{ __('Log in') }}
                    </a>
                @endauth
            </nav>
        @endif
    </header>

    <!-- Main Content Area (GitHub 404 Style) -->
    <main class="relative z-10 flex-1 flex flex-col items-center justify-center px-6 py-12 text-center max-w-4xl mx-auto">
        
        <!-- GitHub Space Illustration (Astronaut Octocat & Planet Scene) -->
        <div class="relative w-72 sm:w-96 h-56 sm:h-64 mx-auto mb-6 select-none">
            <svg class="w-full h-full" viewBox="0 0 400 260" fill="none" xmlns="http://www.w3.org/2000/svg">
                <!-- Distant Orbit Ring -->
                <ellipse cx="200" cy="140" rx="170" ry="45" stroke="#30363d" stroke-width="1.5" stroke-dasharray="4 6" opacity="0.6" />

                <!-- Cratered Floating Planet -->
                <g class="animate-pulse" style="animation-duration: 4s;">
                    <circle cx="80" cy="90" r="38" fill="url(#planetGradient)" />
                    <!-- Planet Rings -->
                    <ellipse cx="80" cy="90" rx="55" ry="14" stroke="#6366f1" stroke-width="2" opacity="0.7" transform="rotate(-18 80 90)" />
                    <!-- Planet Craters -->
                    <circle cx="70" cy="80" r="6" fill="#1e1e38" opacity="0.5" />
                    <circle cx="92" cy="100" r="8" fill="#1e1e38" opacity="0.5" />
                    <circle cx="65" cy="105" r="4" fill="#1e1e38" opacity="0.5" />
                </g>

                <!-- Floating Satellite -->
                <g transform="translate(310, 60) rotate(15)">
                    <rect x="0" y="5" width="22" height="12" rx="3" fill="#8b949e" />
                    <rect x="-12" y="8" width="10" height="6" fill="#388bfd" opacity="0.8" />
                    <rect x="24" y="8" width="10" height="6" fill="#388bfd" opacity="0.8" />
                    <line x1="11" y1="5" x2="11" y2="-4" stroke="#c9d1d9" stroke-width="1.5" />
                    <circle cx="11" cy="-4" r="2" fill="#f85149" />
                </g>

                <!-- Floating Astronaut Cat (GitHub Style) -->
                <g class="animate-bounce" style="animation-duration: 6s;">
                    <!-- Tether Cable -->
                    <path d="M 235 155 Q 260 210, 200 240" stroke="#484f58" stroke-width="2" stroke-linecap="round" fill="none" />

                    <!-- Jetpack / Backpack -->
                    <rect x="180" y="95" width="40" height="48" rx="8" fill="#30363d" stroke="#484f58" stroke-width="2" />
                    <rect x="186" y="102" width="8" height="20" rx="2" fill="#21262d" />
                    <rect x="198" y="102" width="16" height="6" rx="1" fill="#4f46e5" />

                    <!-- Cat Spacesuit Body -->
                    <ellipse cx="205" cy="130" rx="24" ry="26" fill="#f0f6fc" />
                    <!-- Spacesuit Chest Panel -->
                    <rect x="196" y="118" width="18" height="12" rx="3" fill="#21262d" />
                    <circle cx="201" cy="124" r="2" fill="#3fb950" />
                    <circle cx="209" cy="124" r="2" fill="#f85149" />

                    <!-- Floating Legs / Paws -->
                    <ellipse cx="192" cy="154" rx="7" ry="11" fill="#e6edf3" stroke="#d0d7de" stroke-width="1.5" />
                    <ellipse cx="218" cy="154" rx="7" ry="11" fill="#e6edf3" stroke="#d0d7de" stroke-width="1.5" />

                    <!-- Floating Arms / Paws -->
                    <ellipse cx="178" cy="130" rx="6" ry="10" fill="#e6edf3" stroke="#d0d7de" stroke-width="1.5" transform="rotate(25 178 130)" />
                    <ellipse cx="232" cy="125" rx="6" ry="10" fill="#e6edf3" stroke="#d0d7de" stroke-width="1.5" transform="rotate(-35 232 125)" />

                    <!-- Astronaut Helmet Base & Ears -->
                    <ellipse cx="205" cy="85" rx="28" ry="26" fill="#f0f6fc" stroke="#d0d7de" stroke-width="2" />
                    <!-- Cat Ears on Helmet -->
                    <polygon points="186,65 194,76 182,75" fill="#f0f6fc" stroke="#d0d7de" stroke-width="1.5" />
                    <polygon points="224,65 216,76 228,75" fill="#f0f6fc" stroke="#d0d7de" stroke-width="1.5" />

                    <!-- Dark Tinted Visor -->
                    <ellipse cx="205" cy="86" rx="20" ry="16" fill="#0d1117" />
                    <!-- Visor Reflection -->
                    <path d="M 193 80 Q 205 76, 217 80" stroke="#6366f1" stroke-width="2" stroke-linecap="round" fill="none" opacity="0.8" />
                    <circle cx="214" cy="84" r="2.5" fill="#fff" opacity="0.8" />
                    <!-- Cute Whiskers through reflection -->
                    <line x1="192" y1="88" x2="187" y2="87" stroke="#484f58" stroke-width="1" />
                    <line x1="192" y1="91" x2="186" y2="92" stroke="#484f58" stroke-width="1" />
                    <line x1="218" y1="88" x2="223" y2="87" stroke="#484f58" stroke-width="1" />
                    <line x1="218" y1="91" x2="224" y2="92" stroke="#484f58" stroke-width="1" />
                </g>

                <!-- Planet Gradient Definition -->
                <defs>
                    <linearGradient id="planetGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#4f46e5" />
                        <stop offset="60%" stop-color="#2e1065" />
                        <stop offset="100%" stop-color="#0f0728" />
                    </linearGradient>
                </defs>
            </svg>
        </div>

        <!-- 404 Header & Typography -->
        <div class="space-y-3">
            <h1 class="text-7xl sm:text-8xl font-extrabold tracking-tight text-transparent bg-clip-text bg-gradient-to-r from-gray-100 via-gray-300 to-indigo-400 font-mono">
                404
            </h1>
            <h2 class="text-2xl sm:text-3xl font-bold text-white tracking-tight">
                {{ __('This is not the web page you are looking for') }}
            </h2>
            <p class="text-gray-400 text-sm sm:text-base max-w-lg mx-auto">
                {{ __('The page you requested was moved, deleted, or does not exist.') }}
            </p>
        </div>

        <!-- Callout Note: Contact Dave or Malvine -->
        <div class="mt-8 w-full max-w-md mx-auto rounded-xl bg-gray-900/90 border border-gray-800 p-4 shadow-xl backdrop-blur-sm">
            <div class="flex items-start gap-3.5 text-left">
                <div class="p-2 rounded-lg bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 shrink-0 mt-0.5">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                </div>
                <div class="space-y-1">
                    <p class="text-sm font-semibold text-gray-200">
                        {{ __('Need help or think this is a mistake?') }}
                    </p>
                    <p class="text-xs text-gray-400 leading-relaxed">
                        {{ __('Please contact') }} <span class="font-semibold text-indigo-300">Dave or Malvine</span> {{ __('for assistance.') }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Navigation Action Buttons -->
        <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
            @auth
                <a href="{{ route('dashboard') }}"
                   class="inline-flex items-center px-5 py-2.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium text-sm transition shadow-lg shadow-indigo-600/30 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    {{ __('Back to Dashboard') }}
                </a>
                <a href="{{ route('transactions.index') }}"
                   class="inline-flex items-center px-5 py-2.5 rounded-lg bg-gray-800 hover:bg-gray-700 text-gray-200 border border-gray-700 font-medium text-sm transition focus:outline-none focus:ring-2 focus:ring-gray-600">
                    {{ __('View Transactions') }}
                </a>
            @else
                <a href="{{ url('/') }}"
                   class="inline-flex items-center px-5 py-2.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium text-sm transition shadow-lg shadow-indigo-600/30 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    {{ __('Back to Home') }}
                </a>
                <a href="{{ route('login') }}"
                   class="inline-flex items-center px-5 py-2.5 rounded-lg bg-gray-800 hover:bg-gray-700 text-gray-200 border border-gray-700 font-medium text-sm transition focus:outline-none focus:ring-2 focus:ring-gray-600">
                    {{ __('Log in') }}
                </a>
            @endauth

            <button type="button" onclick="window.history.back()"
                    class="inline-flex items-center px-4 py-2.5 text-sm font-medium text-gray-400 hover:text-gray-200 transition">
                &larr; {{ __('Go back') }}
            </button>
        </div>
    </main>

    <!-- Footer -->
    <footer class="relative z-10 w-full max-w-7xl mx-auto px-6 py-6 text-center text-xs text-gray-500 border-t border-gray-800/60">
        <p>&copy; {{ date('Y') }} {{ config('app.name', 'Finance Tracker') }}.</p>
    </footer>

</body>
</html>
