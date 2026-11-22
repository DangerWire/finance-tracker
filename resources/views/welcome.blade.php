<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Finance Tracker') }} — Wealth Tracking Engineered with Precision</title>

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

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-[#09090b] text-zinc-100 min-h-screen antialiased selection:bg-emerald-500/20 selection:text-emerald-300">
        <!-- Subtle Top Ambient Glow -->
        <div class="pointer-events-none fixed inset-0 z-0 sure-radial-glow"></div>

        <!-- Sticky Minimalist Navigation Bar -->
        <header class="sticky top-0 z-50 bg-[#09090b]/80 border-b border-white/[0.06] backdrop-blur-md">
            <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
                <a href="/" class="flex items-center gap-2.5 group">
                    <x-application-logo class="h-8 w-8 transition-transform group-hover:scale-105" />
                    <span class="font-semibold text-sm tracking-tight text-white">
                        Finance<span class="text-emerald-400">Tracker</span>
                    </span>
                    <span class="hidden sm:inline-block px-1.5 py-0.5 rounded text-[10px] font-mono-num font-medium bg-zinc-800 text-zinc-400 border border-zinc-700/60 ml-1">
                        v2.5
                    </span>
                </a>

                <div class="flex items-center gap-3">
                    <x-language-switcher />

                    @auth
                        <a href="{{ url('/dashboard') }}" class="inline-flex items-center gap-1.5 px-4 py-2 bg-zinc-100 hover:bg-white text-zinc-950 text-xs font-medium rounded-lg transition shadow-sm">
                            <span>{{ __('Dashboard') }}</span>
                            <span class="text-zinc-600">&rarr;</span>
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="px-3.5 py-1.5 text-xs font-medium text-zinc-300 hover:text-white transition">
                            {{ __('Log in') }}
                        </a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="inline-flex items-center px-4 py-2 bg-zinc-100 hover:bg-white text-zinc-950 text-xs font-medium rounded-lg transition shadow-sm">
                                {{ __('Register') }}
                            </a>
                        @endif
                    @endauth
                </div>
            </div>
        </header>

        <main class="relative z-10 space-y-24 sm:space-y-32 py-12 sm:py-20">
            <!-- Hero Section -->
            <section class="max-w-5xl mx-auto px-6 text-center">
                <div class="inline-flex items-center gap-2 px-3 py-1 mb-8 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-medium">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span>Powered by Google Gemini 2.5 & Real-Time FX Conversion</span>
                </div>

                <h1 class="text-4xl sm:text-6xl md:text-7xl font-bold tracking-tight text-white mb-6 leading-[1.08]">
                    Personal wealth tracking,<br class="hidden sm:inline" />
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-zinc-100 via-zinc-200 to-zinc-400">
                        engineered with precision.
                    </span>
                </h1>

                <p class="max-w-2xl mx-auto text-base sm:text-lg text-zinc-400 font-light mb-10 leading-relaxed">
                    A clean, open-source personal financial ledger built for disciplined operators. Multi-currency conversions frozen in time, automated recurring bill dispatch, and neural spending analytics.
                </p>

                <div class="flex flex-col sm:flex-row items-center justify-center gap-3 mb-16">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="w-full sm:w-auto px-6 py-3 bg-zinc-100 hover:bg-white text-zinc-950 text-xs font-medium rounded-lg transition shadow-sm flex items-center justify-center gap-2">
                            <span>Open Your Dashboard</span>
                            <span>&rarr;</span>
                        </a>
                    @else
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="w-full sm:w-auto px-6 py-3 bg-zinc-100 hover:bg-white text-zinc-950 text-xs font-medium rounded-lg transition shadow-sm flex items-center justify-center gap-2">
                                <span>Get Started Free</span>
                                <span>&rarr;</span>
                            </a>
                        @endif
                        <a href="{{ route('login') }}" class="w-full sm:w-auto px-6 py-3 bg-zinc-900 border border-zinc-800 hover:bg-zinc-800 text-zinc-200 text-xs font-medium rounded-lg transition flex items-center justify-center">
                            <span>Sign in to Existing Vault</span>
                        </a>
                    @endauth
                </div>

                <!-- Sure.am / Maybe-Inspired Hero Product Mockup -->
                <div class="sure-card rounded-2xl p-6 sm:p-8 text-left border border-white/[0.08] shadow-2xl overflow-hidden relative">
                    <!-- Top Bar of Mockup -->
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-6 border-b border-white/[0.06]">
                        <div>
                            <span class="text-[11px] font-medium uppercase tracking-wider text-zinc-500">Total Consolidated Capital</span>
                            <div class="flex items-baseline gap-2 mt-1">
                                <span class="text-3xl sm:text-4xl font-bold font-mono-num text-zinc-100 tracking-tight">¥ 148,290.50</span>
                                <span class="text-sm font-normal text-zinc-500">CNY</span>
                                <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-[11px] font-mono-num font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                    +12.4% this month
                                </span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="px-2.5 py-1 rounded-md bg-zinc-800 text-zinc-300 text-xs font-mono-num">
                                FX: FRANKFURTER ACTIVE
                            </span>
                            <span class="px-2.5 py-1 rounded-md bg-emerald-500/10 text-emerald-400 text-xs font-mono-num">
                                SYNCHRONIZED
                            </span>
                        </div>
                    </div>

                    <!-- Net Worth Trend Area Curve (SVG) -->
                    <div class="py-6">
                        <div class="h-32 sm:h-44 w-full relative">
                            <svg class="w-full h-full overflow-visible" viewBox="0 0 500 140" fill="none" preserveAspectRatio="none">
                                <defs>
                                    <linearGradient id="sureEmeraldGrad" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#10B981" stop-opacity="0.25"/>
                                        <stop offset="100%" stop-color="#10B981" stop-opacity="0.0"/>
                                    </linearGradient>
                                </defs>
                                <!-- Filled Area -->
                                <path d="M0 110 Q 75 100, 150 75 T 300 45 T 450 20 L 500 15 L 500 140 L 0 140 Z" fill="url(#sureEmeraldGrad)"/>
                                <!-- Stroke Line -->
                                <path d="M0 110 Q 75 100, 150 75 T 300 45 T 450 20 L 500 15" stroke="#10B981" stroke-width="2.5" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <div class="flex justify-between items-center text-[10px] font-mono-num text-zinc-500 pt-2 border-t border-white/[0.04]">
                            <span>OCT 01</span>
                            <span>OCT 15</span>
                            <span>NOV 01</span>
                            <span>TODAY (NOV 14)</span>
                        </div>
                    </div>

                    <!-- Mini KPI Summary Row -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-6 border-t border-white/[0.06]">
                        <div class="p-3.5 rounded-xl bg-zinc-950/50 border border-white/[0.04]">
                            <span class="text-[11px] text-zinc-500 uppercase tracking-wider block mb-1">Monthly Inflow</span>
                            <span class="text-base font-semibold font-mono-num text-emerald-400">+¥ 32,500.00</span>
                        </div>
                        <div class="p-3.5 rounded-xl bg-zinc-950/50 border border-white/[0.04]">
                            <span class="text-[11px] text-zinc-500 uppercase tracking-wider block mb-1">Monthly Outflow</span>
                            <span class="text-base font-semibold font-mono-num text-rose-400">−¥ 14,210.00</span>
                        </div>
                        <div class="p-3.5 rounded-xl bg-zinc-950/50 border border-white/[0.04]">
                            <span class="text-[11px] text-zinc-500 uppercase tracking-wider block mb-1">Savings Rate</span>
                            <span class="text-base font-semibold font-mono-num text-zinc-200">56.3%</span>
                        </div>
                    </div>

                    <!-- Realistic Recent Activity Preview Table -->
                    <div class="mt-6 pt-6 border-t border-white/[0.06]">
                        <div class="flex items-center justify-between mb-3 text-xs font-medium text-zinc-400">
                            <span>Recent Activity</span>
                            <span class="text-emerald-400 hover:text-emerald-300 transition cursor-pointer">View full ledger &rarr;</span>
                        </div>
                        <div class="divide-y divide-white/[0.04]">
                            <div class="py-2.5 flex items-center justify-between text-xs">
                                <div class="flex items-center gap-3">
                                    <div class="w-6 h-6 rounded-md bg-zinc-800 flex items-center justify-center text-zinc-300 font-mono-num text-[10px]">AC</div>
                                    <div>
                                        <span class="text-zinc-200 font-medium">Alibaba Cloud (HK)</span>
                                        <span class="text-zinc-500 ml-2">Cloud Infrastructure</span>
                                    </div>
                                </div>
                                <span class="font-mono-num text-zinc-300">−¥ 842.00</span>
                            </div>
                            <div class="py-2.5 flex items-center justify-between text-xs">
                                <div class="flex items-center gap-3">
                                    <div class="w-6 h-6 rounded-md bg-emerald-500/10 text-emerald-400 flex items-center justify-center font-mono-num text-[10px]">CR</div>
                                    <div>
                                        <span class="text-zinc-200 font-medium">Consulting Retainer</span>
                                        <span class="text-zinc-500 ml-2">Direct Deposit</span>
                                    </div>
                                </div>
                                <span class="font-mono-num text-emerald-400 font-medium">+¥ 25,000.00</span>
                            </div>
                            <div class="py-2.5 flex items-center justify-between text-xs">
                                <div class="flex items-center gap-3">
                                    <div class="w-6 h-6 rounded-md bg-zinc-800 flex items-center justify-center text-zinc-300 font-mono-num text-[10px]">SB</div>
                                    <div>
                                        <span class="text-zinc-200 font-medium">Starbucks Reserve</span>
                                        <span class="text-zinc-500 ml-2">Food & Beverage</span>
                                    </div>
                                </div>
                                <span class="font-mono-num text-zinc-300">−¥ 48.00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Bento Grid Feature Section -->
            <section class="max-w-6xl mx-auto px-6">
                <div class="text-center mb-16">
                    <h2 class="text-2xl sm:text-4xl font-bold tracking-tight text-white mb-3">
                        Architected for total financial sovereignty.
                    </h2>
                    <p class="text-zinc-400 text-sm sm:text-base font-light">
                        Every transaction, schedule, and currency rate stored with cryptographic clarity.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Feature 1: Multi-Currency Engine -->
                    <div class="sure-card rounded-2xl p-7 sure-hover-lift flex flex-col justify-between">
                        <div>
                            <div class="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-center mb-5">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 18h12l3-18H3z"/>
                                </svg>
                            </div>
                            <h3 class="text-lg font-semibold text-white mb-2">Frozen Multi-Currency Engine</h3>
                            <p class="text-sm text-zinc-400 font-light leading-relaxed mb-6">
                                Record transactions in arbitrary global currencies (CNY, USD, EUR, IDR). Historical exchange rates from the European Central Bank are frozen at entry time, preventing retroactive balance shifts.
                            </p>
                        </div>
                        <div class="p-3.5 rounded-xl bg-zinc-950/60 border border-white/[0.04] text-xs font-mono-num text-zinc-400 flex items-center justify-between">
                            <span>$100.00 USD &rarr; ¥ 724.50 CNY</span>
                            <span class="text-emerald-400">RATE FROZEN</span>
                        </div>
                    </div>

                    <!-- Feature 2: Gemini AI Analysis Core -->
                    <div class="sure-card rounded-2xl p-7 sure-hover-lift flex flex-col justify-between">
                        <div>
                            <div class="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-center mb-5">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                            </div>
                            <h3 class="text-lg font-semibold text-white mb-2">Google Gemini 2.5 Intelligence</h3>
                            <p class="text-sm text-zinc-400 font-light leading-relaxed mb-6">
                                Precomputed SQL arithmetic delivered to Gemini 2.5 Flash via structured JSON schema. Zero hallucinations, pure observational intelligence highlighting anomalies and savings opportunities.
                            </p>
                        </div>
                        <div class="p-3.5 rounded-xl bg-zinc-950/60 border border-white/[0.04] text-xs font-mono-num text-zinc-400 flex items-center justify-between">
                            <span>AI TELEMETRY: PRISM STRUCTURED</span>
                            <span class="text-emerald-400">READY</span>
                        </div>
                    </div>

                    <!-- Feature 3: Recurring Automation Engine -->
                    <div class="sure-card rounded-2xl p-7 sure-hover-lift flex flex-col justify-between">
                        <div>
                            <div class="w-10 h-10 rounded-xl bg-zinc-800 text-zinc-200 border border-zinc-700 flex items-center justify-center mb-5">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                            </div>
                            <h3 class="text-lg font-semibold text-white mb-2">Automated Recurring Bills</h3>
                            <p class="text-sm text-zinc-400 font-light leading-relaxed mb-6">
                                Set recurring subscriptions once. A background worker runs daily at 04:00 with strict database-level idempotency, preventing duplicate charges while alerting you before due dates.
                            </p>
                        </div>
                        <div class="p-3.5 rounded-xl bg-zinc-950/60 border border-white/[0.04] text-xs font-mono-num text-zinc-400 flex items-center justify-between">
                            <span>DAILY RUNNER: 04:00 CRON</span>
                            <span class="text-zinc-300">IDEMPOTENT</span>
                        </div>
                    </div>

                    <!-- Feature 4: Real-Time Category Safeguards -->
                    <div class="sure-card rounded-2xl p-7 sure-hover-lift flex flex-col justify-between">
                        <div>
                            <div class="w-10 h-10 rounded-xl bg-zinc-800 text-zinc-200 border border-zinc-700 flex items-center justify-center mb-5">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                            </div>
                            <h3 class="text-lg font-semibold text-white mb-2">Real-Time Category Budgets</h3>
                            <p class="text-sm text-zinc-400 font-light leading-relaxed mb-6">
                                Set monthly limits per category. Visual progress bars signal normal pacing, 80% caution alerts, and hard overruns, accompanied by sub-category transaction splitting.
                            </p>
                        </div>
                        <div class="p-3.5 rounded-xl bg-zinc-950/60 border border-white/[0.04] text-xs font-mono-num text-zinc-400 flex items-center justify-between">
                            <span>BUDGET ALARMS: ACTIVE</span>
                            <span class="text-zinc-300">CALCULATED IN SQL</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Call to Action Section -->
            <section class="max-w-4xl mx-auto px-6 text-center">
                <div class="sure-card rounded-3xl p-10 sm:p-14 border border-white/[0.08] relative overflow-hidden">
                    <div class="relative z-10">
                        <h2 class="text-3xl sm:text-5xl font-bold tracking-tight text-white mb-4">
                            Deploy your personal ledger.
                        </h2>
                        <p class="text-zinc-400 text-sm sm:text-base font-light max-w-xl mx-auto mb-8">
                            Complete tracking, instant CSV imports, automated recurring engine, and intelligent spending analysis.
                        </p>
                        <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
                            @auth
                                <a href="{{ url('/dashboard') }}" class="px-8 py-3 bg-zinc-100 hover:bg-white text-zinc-950 text-xs font-medium rounded-lg transition shadow-sm">
                                    {{ __('Dashboard') }} &rarr;
                                </a>
                            @else
                                <a href="{{ route('register') }}" class="px-8 py-3 bg-zinc-100 hover:bg-white text-zinc-950 text-xs font-medium rounded-lg transition shadow-sm">
                                    {{ __('Register') }} &rarr;
                                </a>
                                <a href="{{ route('login') }}" class="px-8 py-3 bg-zinc-900 border border-zinc-800 hover:bg-zinc-800 text-zinc-200 text-xs font-medium rounded-lg transition">
                                    {{ __('Log in') }}
                                </a>
                            @endauth
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <!-- Minimalist Footer -->
        <footer class="border-t border-white/[0.06] py-10 bg-[#09090b]">
            <div class="max-w-7xl mx-auto px-6 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-zinc-500 font-mono-num">
                <div class="flex items-center gap-2">
                    <x-application-logo class="h-5 w-5" />
                    <span>Finance Tracker // Open Source Wealth Engine</span>
                </div>
                <div>
                    <span>Laravel 13 · Tailwind CSS · Google Gemini 2.5 · PostgreSQL</span>
                </div>
            </div>
        </footer>
    </body>
</html>
