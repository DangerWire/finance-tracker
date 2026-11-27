@php
    $status = isset($status) ? (int) $status : (isset($exception) && method_exists($exception, 'getStatusCode') ? (int) $exception->getStatusCode() : 500);

    if (! isset($isAuthenticated)) {
        try {
            $isAuthenticated = auth()->check();
        } catch (\Throwable) {
            $isAuthenticated = false;
        }
    }

    $copy = match ($status) {
        401 => [
            'title' => __('Authentication required'),
            'description' => __('Please sign in to access this page.'),
            'tone' => 'amber',
        ],
        403 => [
            'title' => __('Access denied'),
            'description' => __('You do not have permission to view or modify this resource.'),
            'tone' => 'amber',
        ],
        404 => [
            'title' => __('This is not the web page you are looking for'),
            'description' => __('The page you requested was moved, deleted, or does not exist.'),
            'tone' => 'emerald',
        ],
        405 => [
            'title' => __('Method not allowed'),
            'description' => __('This action cannot be accessed directly with that request method.'),
            'tone' => 'amber',
        ],
        419 => [
            'title' => __('Page expired'),
            'description' => __('Your session expired for security reasons. Refresh and try again.'),
            'tone' => 'amber',
        ],
        429 => [
            'title' => __('Too many requests'),
            'description' => __('You have made too many requests in a short period. Please wait a moment and try again.'),
            'tone' => 'amber',
        ],
        503 => [
            'title' => __('Service unavailable'),
            'description' => __('Finance Tracker is briefly down for maintenance. Please try again shortly.'),
            'tone' => 'rose',
        ],
        default => [
            'title' => __('Something went wrong'),
            'description' => __('An unexpected error occurred while loading this page. The issue has been logged.'),
            'tone' => $status >= 500 ? 'rose' : 'amber',
        ],
    };

    $tone = $copy['tone'];
    $showRetry = in_array($status, [419, 429], true) || $status >= 500;

    $accentPalette = match ($tone) {
        'rose' => [
            'glow' => 'from-rose-900/20 via-rose-950/10 to-transparent',
            'orb' => 'bg-rose-900/15',
            'codeGradient' => 'from-zinc-100 via-zinc-300 to-rose-400',
            'badgeBg' => 'bg-rose-500/10 text-rose-400 border-rose-500/20',
            'highlight' => 'text-rose-300',
            'planetTop' => '#e11d48',
            'planetMid' => '#881337',
            'planetBot' => '#18181b',
            'ring' => '#fb7185',
            'visor' => '#fb7185',
        ],
        'amber' => [
            'glow' => 'from-amber-900/20 via-amber-950/10 to-transparent',
            'orb' => 'bg-amber-900/15',
            'codeGradient' => 'from-zinc-100 via-zinc-300 to-amber-400',
            'badgeBg' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
            'highlight' => 'text-amber-300',
            'planetTop' => '#d97706',
            'planetMid' => '#78350f',
            'planetBot' => '#18181b',
            'ring' => '#fbbf24',
            'visor' => '#fbbf24',
        ],
        default => [
            'glow' => 'from-emerald-900/20 via-teal-950/10 to-transparent',
            'orb' => 'bg-emerald-900/15',
            'codeGradient' => 'from-zinc-100 via-zinc-300 to-emerald-400',
            'badgeBg' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
            'highlight' => 'text-emerald-300',
            'planetTop' => '#10b981',
            'planetMid' => '#065f46',
            'planetBot' => '#18181b',
            'ring' => '#34d399',
            'visor' => '#34d399',
        ],
    };

    $homeUrl = url('/');
    $dashboardUrl = Route::has('dashboard') ? route('dashboard') : $homeUrl;
    $transactionsUrl = Route::has('transactions.index') ? route('transactions.index') : $dashboardUrl;
    $loginUrl = Route::has('login') ? route('login') : $homeUrl;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark h-full bg-[#09090b]">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $status }} — {{ $copy['title'] }} | {{ config('app.name', 'Finance Tracker') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

    <!-- Scripts and Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans antialiased text-zinc-200 bg-[#09090b] selection:bg-emerald-500 selection:text-zinc-950 flex flex-col justify-between overflow-x-hidden">

    <!-- Cosmic Background Elements -->
    <div class="fixed inset-0 pointer-events-none overflow-hidden" aria-hidden="true">
        <div class="absolute -top-40 left-1/2 -translate-x-1/2 w-[1000px] h-[500px] bg-gradient-to-b {{ $accentPalette['glow'] }} blur-3xl opacity-75"></div>
        <div class="absolute top-1/4 -left-20 w-80 h-80 bg-sky-900/10 rounded-full blur-3xl"></div>
        <div class="absolute bottom-1/4 -right-20 w-96 h-96 {{ $accentPalette['orb'] }} rounded-full blur-3xl"></div>

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
        <a href="{{ $homeUrl }}" class="flex items-center gap-2.5 group rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400/70">
            <x-application-logo class="w-8 h-8 transition-transform group-hover:scale-105" />
            <span class="font-bold text-base text-white tracking-tight group-hover:text-zinc-200 transition">
                Finance<span class="text-emerald-400">Tracker</span>
            </span>
        </a>

        @if (Route::has('login'))
            <nav class="flex items-center gap-4 text-sm" aria-label="Error navigation">
                @if ($isAuthenticated)
                    <a href="{{ $dashboardUrl }}" class="text-zinc-400 hover:text-white transition rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400/70">
                        {{ __('Dashboard') }}
                    </a>
                @else
                    <a href="{{ $loginUrl }}" class="text-zinc-400 hover:text-white transition rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400/70">
                        {{ __('Log in') }}
                    </a>
                @endif
            </nav>
        @endif
    </header>

    <!-- Main Content Area (Unified Cosmic Error Page) -->
    <main class="relative z-10 flex-1 flex flex-col items-center justify-center px-6 py-10 text-center max-w-4xl mx-auto">

        <!-- Space Illustration (Astronaut Cat & Planet Scene) -->
        <div class="relative w-72 sm:w-96 h-52 sm:h-60 mx-auto mb-6 select-none" aria-hidden="true">
            <svg class="w-full h-full" viewBox="0 0 400 260" fill="none" xmlns="http://www.w3.org/2000/svg">
                <!-- Distant Orbit Ring -->
                <ellipse cx="200" cy="140" rx="170" ry="45" stroke="#27272a" stroke-width="1.5" stroke-dasharray="4 6" opacity="0.8" />

                <!-- Cratered Floating Planet -->
                <g class="animate-pulse motion-reduce:animate-none" style="animation-duration: 4s;">
                    <circle cx="80" cy="90" r="38" fill="url(#planetGradient)" />
                    <ellipse cx="80" cy="90" rx="55" ry="14" stroke="{{ $accentPalette['ring'] }}" stroke-width="2" opacity="0.7" transform="rotate(-18 80 90)" />
                    <circle cx="70" cy="80" r="6" fill="#09090b" opacity="0.35" />
                    <circle cx="92" cy="100" r="8" fill="#09090b" opacity="0.35" />
                    <circle cx="65" cy="105" r="4" fill="#09090b" opacity="0.35" />
                </g>

                <!-- Floating Satellite -->
                <g transform="translate(310, 60) rotate(15)">
                    <rect x="0" y="5" width="22" height="12" rx="3" fill="#71717a" />
                    <rect x="-12" y="8" width="10" height="6" fill="#38bdf8" opacity="0.8" />
                    <rect x="24" y="8" width="10" height="6" fill="#38bdf8" opacity="0.8" />
                    <line x1="11" y1="5" x2="11" y2="-4" stroke="#d4d4d8" stroke-width="1.5" />
                    <circle cx="11" cy="-4" r="2" fill="#fb7185" />
                </g>

                <!-- Floating Astronaut Cat -->
                <g class="animate-bounce motion-reduce:animate-none" style="animation-duration: 6s;">
                    <path d="M 235 155 Q 260 210, 200 240" stroke="#3f3f46" stroke-width="2" stroke-linecap="round" fill="none" />

                    <!-- Jetpack / Backpack -->
                    <rect x="180" y="95" width="40" height="48" rx="8" fill="#27272a" stroke="#3f3f46" stroke-width="2" />
                    <rect x="186" y="102" width="8" height="20" rx="2" fill="#18181b" />
                    <rect x="198" y="102" width="16" height="6" rx="1" fill="{{ $accentPalette['planetTop'] }}" />

                    <!-- Cat Spacesuit Body -->
                    <ellipse cx="205" cy="130" rx="24" ry="26" fill="#f4f4f5" />
                    <rect x="196" y="118" width="18" height="12" rx="3" fill="#18181b" />
                    <circle cx="201" cy="124" r="2" fill="#10b981" />
                    <circle cx="209" cy="124" r="2" fill="#fb7185" />

                    <!-- Floating Legs / Paws -->
                    <ellipse cx="192" cy="154" rx="7" ry="11" fill="#e4e4e7" stroke="#d4d4d8" stroke-width="1.5" />
                    <ellipse cx="218" cy="154" rx="7" ry="11" fill="#e4e4e7" stroke="#d4d4d8" stroke-width="1.5" />

                    <!-- Floating Arms / Paws -->
                    <ellipse cx="178" cy="130" rx="6" ry="10" fill="#e4e4e7" stroke="#d4d4d8" stroke-width="1.5" transform="rotate(25 178 130)" />
                    <ellipse cx="232" cy="125" rx="6" ry="10" fill="#e4e4e7" stroke="#d4d4d8" stroke-width="1.5" transform="rotate(-35 232 125)" />

                    <!-- Astronaut Helmet Base & Ears -->
                    <ellipse cx="205" cy="85" rx="28" ry="26" fill="#f4f4f5" stroke="#d4d4d8" stroke-width="2" />
                    <polygon points="186,65 194,76 182,75" fill="#f4f4f5" stroke="#d4d4d8" stroke-width="1.5" />
                    <polygon points="224,65 216,76 228,75" fill="#f4f4f5" stroke="#d4d4d8" stroke-width="1.5" />

                    <!-- Dark Tinted Visor -->
                    <ellipse cx="205" cy="86" rx="20" ry="16" fill="#09090b" />
                    <path d="M 193 80 Q 205 76, 217 80" stroke="{{ $accentPalette['visor'] }}" stroke-width="2" stroke-linecap="round" fill="none" opacity="0.85" />
                    <circle cx="214" cy="84" r="2.5" fill="#fff" opacity="0.8" />
                    <line x1="192" y1="88" x2="187" y2="87" stroke="#52525b" stroke-width="1" />
                    <line x1="192" y1="91" x2="186" y2="92" stroke="#52525b" stroke-width="1" />
                    <line x1="218" y1="88" x2="223" y2="87" stroke="#52525b" stroke-width="1" />
                    <line x1="218" y1="91" x2="224" y2="92" stroke="#52525b" stroke-width="1" />
                </g>

                <!-- Planet Gradient Definition -->
                <defs>
                    <linearGradient id="planetGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="{{ $accentPalette['planetTop'] }}" />
                        <stop offset="60%" stop-color="{{ $accentPalette['planetMid'] }}" />
                        <stop offset="100%" stop-color="{{ $accentPalette['planetBot'] }}" />
                    </linearGradient>
                </defs>
            </svg>
        </div>

        <!-- Status Code & Typography -->
        <div class="space-y-3">
            <h1 class="text-7xl sm:text-8xl font-extrabold tracking-tight text-transparent bg-clip-text bg-gradient-to-r {{ $accentPalette['codeGradient'] }} font-mono-num">
                {{ $status }}
            </h1>
            <h2 class="text-2xl sm:text-3xl font-bold text-white tracking-tight">
                {{ $copy['title'] }}
            </h2>
            <p class="text-zinc-400 text-sm sm:text-base max-w-lg mx-auto">
                {{ $copy['description'] }}
            </p>
        </div>

        <!-- Callout Note: Contact Dave or Malvine -->
        <div class="mt-8 w-full max-w-md mx-auto rounded-xl bg-[#111218]/95 border border-white/[0.08] p-4 shadow-xl backdrop-blur-sm">
            <div class="flex items-start gap-3.5 text-left">
                <div class="p-2 rounded-lg {{ $accentPalette['badgeBg'] }} border shrink-0 mt-0.5" aria-hidden="true">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                </div>
                <div class="space-y-1">
                    <p class="text-sm font-semibold text-zinc-100">
                        {{ __('Need help or think this is a mistake?') }}
                    </p>
                    <p class="text-xs text-zinc-400 leading-relaxed">
                        {{ __('Please contact') }} <span class="font-semibold {{ $accentPalette['highlight'] }}">Dave or Malvine</span> {{ __('for assistance.') }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Navigation Action Buttons -->
        <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
            @if ($isAuthenticated)
                <a href="{{ $dashboardUrl }}"
                   class="inline-flex items-center px-5 py-2.5 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-zinc-950 font-semibold text-sm transition shadow-lg shadow-emerald-500/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    {{ __('Back to Dashboard') }}
                </a>
                <a href="{{ $transactionsUrl }}"
                   class="inline-flex items-center px-5 py-2.5 rounded-lg bg-zinc-900 hover:bg-zinc-800 text-zinc-200 border border-white/[0.08] font-medium text-sm transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400/70">
                    {{ __('View Transactions') }}
                </a>
            @else
                <a href="{{ $homeUrl }}"
                   class="inline-flex items-center px-5 py-2.5 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-zinc-950 font-semibold text-sm transition shadow-lg shadow-emerald-500/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    {{ __('Back to Home') }}
                </a>
                <a href="{{ $loginUrl }}"
                   class="inline-flex items-center px-5 py-2.5 rounded-lg bg-zinc-900 hover:bg-zinc-800 text-zinc-200 border border-white/[0.08] font-medium text-sm transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400/70">
                    {{ __('Log in') }}
                </a>
            @endif

            @if ($showRetry)
                <button type="button" onclick="window.location.reload()"
                        class="inline-flex items-center px-4 py-2.5 rounded-lg bg-zinc-900/80 hover:bg-zinc-800 text-zinc-200 border border-white/[0.08] text-sm font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400/70">
                    {{ __('Try again') }}
                </button>
            @endif

            <button type="button" onclick="window.history.back()"
                    class="inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-medium text-zinc-400 hover:text-zinc-100 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400/70">
                &larr; {{ __('Go back') }}
            </button>
        </div>
    </main>

    <!-- Footer -->
    <footer class="relative z-10 w-full max-w-7xl mx-auto px-6 py-6 text-center text-xs text-zinc-500 border-t border-white/[0.06]">
        <p>&copy; {{ date('Y') }} {{ config('app.name', 'Finance Tracker') }}.</p>
    </footer>

</body>
</html>
