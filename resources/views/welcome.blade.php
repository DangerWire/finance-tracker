<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Finance Tracker') }} // TACTICAL CAPITAL OS</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=space-grotesk:400,500,600,700|jetbrains-mono:400,500,700" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            body {
                font-family: 'Space Grotesk', sans-serif;
                background-color: #07080b;
                color: #f1f5f9;
            }
            .font-mono-tech {
                font-family: 'JetBrains Mono', monospace;
            }
        </style>
    </head>
    <body class="bg-[#07080b] text-slate-100 selection:bg-[#FFE500] selection:text-black">

        <!-- Master Viewport Container -->
        <div id="landing-viewport" class="relative w-full min-h-screen bg-[#07080b] cyber-grid overflow-x-clip">

            <!-- Persistent HUD Scanlines & Vignette -->
            <div class="pointer-events-none fixed inset-0 z-40 bg-[radial-gradient(ellipse_at_center,transparent_0%,rgba(7,8,11,0.75)_100%)]"></div>

            <!-- Top Tactical HUD Navigation -->
            <header class="fixed top-0 left-0 right-0 z-50 flex items-center justify-between px-6 py-4 md:px-10 border-b border-white/10 bg-[#07080b]/80 backdrop-blur-md">
                <div class="flex items-center gap-4">
                    <div class="flex items-center justify-center w-8 h-8 bg-[#FFE500] text-black font-mono-tech font-bold text-sm clip-chamfer">
                        FT
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-mono-tech text-xs tracking-widest text-[#FFE500] uppercase font-bold">FINANCE // OS</span>
                            <span class="inline-block w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        </div>
                        <p class="font-mono-tech text-[10px] text-gray-400 tracking-wider">SYSTEM v2.4 // PROTOCOL ACTIVE</p>
                    </div>
                </div>

                <!-- Auth Navigation -->
                <nav class="flex items-center gap-3">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="group relative px-5 py-2 font-mono-tech text-xs uppercase tracking-wider text-black bg-[#FFE500] hover:bg-yellow-300 transition clip-chamfer font-semibold flex items-center gap-2 glow-amber-btn">
                            <span>{{ __('Dashboard') }}</span>
                            <span class="transition-transform group-hover:translate-x-1">&rarr;</span>
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="px-4 py-2 font-mono-tech text-xs uppercase tracking-wider text-gray-300 hover:text-[#FFE500] border border-white/10 hover:border-[#FFE500]/50 transition clip-chamfer bg-white/5">
                            {{ __('Log in') }}
                        </a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="px-5 py-2 font-mono-tech text-xs uppercase tracking-wider text-black bg-[#FFE500] hover:bg-yellow-300 font-semibold transition clip-chamfer glow-amber-btn flex items-center gap-1.5">
                                <span>{{ __('Register') }}</span>
                                <span>+</span>
                            </a>
                        @endif
                    @endauth
                </nav>
            </header>

            <!-- Right-Side Fixed Progress Stage HUD -->
            <aside class="fixed right-6 top-1/2 -translate-y-1/2 z-50 hidden md:flex flex-col items-center gap-5">
                <div id="hud-stage-counter" class="font-mono-tech text-[11px] text-[#FFE500] tracking-widest font-semibold">
                    01 // 05
                </div>

                <!-- Vertical Rail -->
                <div class="relative w-0.5 h-48 bg-white/10 rounded-full overflow-hidden">
                    <div id="hud-progress-bar" class="absolute top-0 left-0 w-full h-0 bg-[#FFE500] transition-all duration-150"></div>
                </div>

                <!-- 5 Clickable Stage Dots -->
                <div class="flex flex-col gap-3">
                    <button class="hud-dot w-2.5 h-2.5 rounded-full border border-[#FFE500] bg-[#FFE500] transition-all hover:scale-125" title="01 Overview"></button>
                    <button class="hud-dot w-2.5 h-2.5 rounded-full border border-gray-700 bg-gray-800 transition-all hover:scale-125" title="02 Recurring Automation"></button>
                    <button class="hud-dot w-2.5 h-2.5 rounded-full border border-gray-700 bg-gray-800 transition-all hover:scale-125" title="03 Neural Core"></button>
                    <button class="hud-dot w-2.5 h-2.5 rounded-full border border-gray-700 bg-gray-800 transition-all hover:scale-125" title="04 Budgets"></button>
                    <button class="hud-dot w-2.5 h-2.5 rounded-full border border-gray-700 bg-gray-800 transition-all hover:scale-125" title="05 Access"></button>
                </div>
            </aside>

            <!-- Bottom Left Fixed Telemetry Ticker -->
            <footer class="fixed bottom-4 left-6 md:left-10 z-40 hidden sm:flex items-center gap-4 text-[11px] font-mono-tech text-gray-400">
                <div class="flex items-center gap-1.5 text-emerald-400">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                    <span>ONLINE</span>
                </div>
                <span class="text-white/20">|</span>
                <span id="hud-section-name" class="tracking-wider text-gray-300">OVERVIEW // PROTOCOL DEPLOYED</span>
                <span class="text-white/20">|</span>
                <span class="text-gray-400">BASE_CURRENCY: CNY</span>
            </footer>

            <!-- PINNED STAGE VIEWPORT (Controlled by GSAP ScrollTrigger) -->
            <div id="pinned-stage" class="relative w-full h-screen overflow-hidden">

                <!-- SLIDE 1: Hero / Protocol Overview -->
                <section class="cyber-slide slide-1 absolute inset-0 flex flex-col justify-center items-center px-6 md:px-16 pt-16">
                    <div class="max-w-5xl w-full mx-auto flex flex-col items-center text-center">

                        <!-- Telemetry Pill -->
                        <div class="inline-flex items-center gap-2 px-3 py-1 mb-6 border border-[#FFE500]/40 bg-[#FFE500]/10 text-[#FFE500] text-xs font-mono-tech tracking-widest clip-badge uppercase">
                            <span class="w-1.5 h-1.5 bg-[#FFE500] animate-ping"></span>
                            <span>FINANCIAL PROTOCOL // INITIALIZED</span>
                        </div>

                        <!-- Massive Title -->
                        <h1 class="text-4xl sm:text-6xl md:text-7xl lg:text-8xl font-black uppercase tracking-tight text-white mb-6 leading-none">
                            TACTICAL <span class="text-[#FFE500] glow-text-amber">CAPITAL</span><br />
                            CONTROL SYSTEM
                        </h1>

                        <p class="max-w-2xl text-base sm:text-lg text-slate-300 mb-10 leading-relaxed font-light">
                            Personal finance engineered for uncompromising tactical precision. Multi-currency conversions frozen in time, algorithmic budget bounds, and neural spending insights.
                        </p>

                        <!-- Cyber Token Card Element -->
                        <div class="relative w-full max-w-md p-6 bg-[#0f121a] border border-[#FFE500]/30 clip-chamfer-lg glow-amber-box mb-8 text-left">
                            <div class="flex justify-between items-start mb-6">
                                <div>
                                    <p class="font-mono-tech text-[10px] text-gray-400 tracking-widest uppercase">VAULT IDENTIFIER</p>
                                    <p class="font-mono-tech text-xs text-[#FFE500] font-bold">TX-ALPHA-9021</p>
                                </div>
                                <div class="px-2 py-0.5 font-mono-tech text-[10px] bg-emerald-500/20 border border-emerald-500/40 text-emerald-400 clip-badge">
                                    SYNCHRONIZED
                                </div>
                            </div>
                            <div class="mb-4">
                                <p class="font-mono-tech text-xs text-gray-400 uppercase">Total Consolidated Capital</p>
                                <p class="text-3xl font-black text-white font-mono-tech">¥ 148,290.50 <span class="text-sm font-normal text-[#FFE500]">CNY</span></p>
                            </div>
                            <div class="flex items-center justify-between text-[11px] font-mono-tech text-gray-400 pt-3 border-t border-white/10">
                                <span>STATUS: RECURRING ENGINE ARMED</span>
                                <span class="text-[#FFE500]">FRANKFURTER FX &check;</span>
                            </div>
                        </div>

                        <!-- Scroll to Proceed Indicator -->
                        <button id="hero-scroll-btn" class="flex items-center gap-3 px-6 py-3 border border-white/20 bg-white/5 hover:border-[#FFE500] hover:bg-[#FFE500]/10 transition clip-chamfer text-xs font-mono-tech uppercase tracking-widest text-slate-200">
                            <span>SCROLL TO ADVANCE TELEMETRY</span>
                            <span class="animate-bounce text-[#FFE500]">&darr;</span>
                        </button>
                    </div>
                </section>

                <!-- SLIDE 2: Recurring Automation & Scheduled Bills -->
                <section class="cyber-slide slide-2 absolute inset-0 flex flex-col justify-center items-center px-6 md:px-16 pt-16">
                    <div class="max-w-5xl w-full mx-auto">
                        <div class="mb-8">
                            <span class="font-mono-tech text-xs text-cyan-400 tracking-widest uppercase">STAGE 02 // RECURRING AUTOMATION ENGINE</span>
                            <h2 class="text-3xl sm:text-5xl font-black uppercase text-white mt-1">
                                AUTONOMOUS <span class="text-cyan-400 glow-text-cyan">TRANSACTION DISPATCH</span>
                            </h2>
                            <p class="text-slate-300 mt-2 max-w-xl text-sm sm:text-base">
                                Set recurring schedules once. Background workers automatically post due bills with strict idempotency and advance notification alerts.
                            </p>
                        </div>

                        <!-- 3 Tactical Recurring Automation Pods -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="slide-2-card p-6 bg-[#0c1017] border border-cyan-500/40 clip-chamfer glow-cyan-box">
                                <div class="flex justify-between items-center mb-4">
                                    <span class="font-mono-tech text-xs text-cyan-400 font-bold">CRON RUNNER</span>
                                    <span class="text-[10px] font-mono-tech px-2 py-0.5 bg-cyan-400/10 text-cyan-400 border border-cyan-400/30 clip-badge">DAILY 04:00</span>
                                </div>
                                <div class="text-2xl font-black font-mono-tech text-white mb-2">IDEMPOTENT</div>
                                <p class="text-xs text-gray-400 leading-relaxed font-mono-tech">
                                    Unique database key prevents double-posting.<br />
                                    Daily, weekly, monthly, or yearly cadence.
                                </p>
                            </div>

                            <div class="slide-2-card p-6 bg-[#0c1017] border border-[#FFE500]/40 clip-chamfer glow-amber-box">
                                <div class="flex justify-between items-center mb-4">
                                    <span class="font-mono-tech text-xs text-[#FFE500] font-bold">BILL ALARMS</span>
                                    <span class="text-[10px] font-mono-tech px-2 py-0.5 bg-[#FFE500]/10 text-[#FFE500] border border-[#FFE500]/30 clip-badge">ARMED</span>
                                </div>
                                <div class="text-2xl font-black font-mono-tech text-white mb-2">T-3 DAYS</div>
                                <p class="text-xs text-gray-400 leading-relaxed font-mono-tech">
                                    Proactive notification triggers before bills arrive.<br />
                                    Never get caught with zero liquid balance.
                                </p>
                            </div>

                            <div class="slide-2-card p-6 bg-[#0c1017] border border-emerald-500/40 clip-chamfer">
                                <div class="flex justify-between items-center mb-4">
                                    <span class="font-mono-tech text-xs text-emerald-400 font-bold">INSTANT DISPATCH</span>
                                    <span class="text-[10px] font-mono-tech px-2 py-0.5 bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 clip-badge">ON-DEMAND</span>
                                </div>
                                <div class="text-2xl font-black font-mono-tech text-white mb-2">RUN NOW</div>
                                <p class="text-xs text-gray-400 leading-relaxed font-mono-tech">
                                    Manual trigger generates all pending items immediately.<br />
                                    Full audit log with transaction linkage.
                                </p>
                            </div>
                        </div>

                        <!-- System Note -->
                        <div class="mt-8 p-4 bg-white/5 border border-white/10 clip-chamfer text-xs font-mono-tech text-gray-300 flex items-center justify-between">
                            <span>AUTOMATION PROTOCOL: php artisan finance:generate-recurring runs autonomously.</span>
                            <span class="text-cyan-400 font-bold">DISPATCH READY</span>
                        </div>
                    </div>
                </section>

                <!-- SLIDE 3: Neural Core / Gemini AI -->
                <section class="cyber-slide slide-3 absolute inset-0 flex flex-col justify-center items-center px-6 md:px-16 pt-16">
                    <div class="max-w-5xl w-full mx-auto">
                        <div class="mb-8">
                            <span class="font-mono-tech text-xs text-[#FFE500] tracking-widest uppercase">STAGE 03 // NEURAL SPENDING ENGINE</span>
                            <h2 class="text-3xl sm:text-5xl font-black uppercase text-white mt-1">
                                GOOGLE GEMINI <span class="text-[#FFE500] glow-text-amber">ANALYSIS CORE</span>
                            </h2>
                            <p class="text-slate-300 mt-2 max-w-xl text-sm sm:text-base">
                                Precomputed SQL arithmetic delivered to Gemini 2.5 Flash. Zero hallucinations, pure observational intelligence.
                            </p>
                        </div>

                        <!-- High Tech Terminal Output -->
                        <div class="bg-[#0b0e14] border border-[#FFE500]/30 clip-chamfer-lg p-6 font-mono-tech text-xs leading-relaxed text-gray-300">
                            <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                                <div class="flex items-center gap-2 text-cyan-400">
                                    <span class="w-2 h-2 rounded-full bg-cyan-400 animate-ping"></span>
                                    <span>GEMINI_AI // PRISM STRUCTURED PROTOCOL</span>
                                </div>
                                <span class="text-gray-500">PROVIDER: GEMINI-2.5-FLASH</span>
                            </div>

                            <div class="space-y-3">
                                <div class="slide-3-item flex items-start gap-3">
                                    <span class="text-[#FFE500] font-bold">&gt;</span>
                                    <span>STATISTICS ENGINE: October expense count: 24 transactions. Total: ¥12,450.00.</span>
                                </div>
                                <div class="slide-3-item flex items-start gap-3 p-3 bg-red-500/10 border border-red-500/30 text-red-300 clip-chamfer">
                                    <span class="text-red-400 font-bold">&gt; [ALERT]</span>
                                    <span>HOUSING SPIKE: Housing accounts for 58.4% of recorded expenditures. Exceeds standard 45% threshold.</span>
                                </div>
                                <div class="slide-3-item flex items-start gap-3 p-3 bg-cyan-500/10 border border-cyan-500/30 text-cyan-300 clip-chamfer">
                                    <span class="text-cyan-400 font-bold">&gt; [INSIGHT]</span>
                                    <span>SUBSCRIPTION CADENCE: 3 recurring services scheduled within 48h. Projected deduction: ¥189.00.</span>
                                </div>
                                <div class="slide-3-item flex items-start gap-3 text-emerald-400">
                                    <span class="font-bold">&gt;</span>
                                    <span>OUTPUT CONFIRMATION: Headline and observations formatted in pure JSON schema. Ready for view rendering.</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- SLIDE 4: Budgets & Split Allocation -->
                <section class="cyber-slide slide-4 absolute inset-0 flex flex-col justify-center items-center px-6 md:px-16 pt-16">
                    <div class="max-w-5xl w-full mx-auto">
                        <div class="mb-8">
                            <span class="font-mono-tech text-xs text-cyan-400 tracking-widest uppercase">STAGE 04 // CAPITAL BOUNDS</span>
                            <h2 class="text-3xl sm:text-5xl font-black uppercase text-white mt-1">
                                REAL-TIME <span class="text-cyan-400 glow-text-cyan">BUDGET SAFEGUARDS</span>
                            </h2>
                            <p class="text-slate-300 mt-2 max-w-xl text-sm sm:text-base">
                                Dynamic progress bars, 80% hazard triggers, and Lunch Money-style sub-category transaction splitting.
                            </p>
                        </div>

                        <!-- Progress Bars & Telemetry -->
                        <div class="space-y-6 bg-[#0c1017] border border-cyan-500/30 clip-chamfer-lg p-6">
                            <div>
                                <div class="flex justify-between items-center text-xs font-mono-tech mb-2">
                                    <span class="text-white font-bold">HOUSING & RENT</span>
                                    <span class="text-amber-400 font-bold">¥ 4,200.00 / ¥ 5,000.00 (84% // WARNING)</span>
                                </div>
                                <div class="w-full h-3 bg-white/10 rounded-full overflow-hidden">
                                    <div class="slide-4-bar h-full bg-amber-400" data-width="84%"></div>
                                </div>
                            </div>

                            <div>
                                <div class="flex justify-between items-center text-xs font-mono-tech mb-2">
                                    <span class="text-white font-bold">FOOD & SUPPLIES</span>
                                    <span class="text-cyan-400 font-bold">¥ 1,800.00 / ¥ 3,500.00 (51% // NOMINAL)</span>
                                </div>
                                <div class="w-full h-3 bg-white/10 rounded-full overflow-hidden">
                                    <div class="slide-4-bar h-full bg-cyan-400" data-width="51%"></div>
                                </div>
                            </div>

                            <div>
                                <div class="flex justify-between items-center text-xs font-mono-tech mb-2">
                                    <span class="text-white font-bold">ENTERTAINMENT & GEAR</span>
                                    <span class="text-red-400 font-bold">¥ 2,100.00 / ¥ 2,000.00 (105% // EXCEEDED)</span>
                                </div>
                                <div class="w-full h-3 bg-white/10 rounded-full overflow-hidden">
                                    <div class="slide-4-bar h-full bg-red-500" data-width="100%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- SLIDE 5: Terminal Access CTA -->
                <section class="cyber-slide slide-5 absolute inset-0 flex flex-col justify-center items-center px-6 md:px-16 pt-16">
                    <div class="max-w-4xl w-full mx-auto text-center">

                        <div class="inline-flex items-center gap-2 px-3 py-1 mb-6 border border-emerald-500/40 bg-emerald-500/10 text-emerald-400 text-xs font-mono-tech tracking-widest clip-badge uppercase">
                            <span>STATUS // READY FOR DEPLOYMENT</span>
                        </div>

                        <h2 class="text-4xl sm:text-6xl font-black uppercase text-white mb-6">
                            INITIALIZE YOUR <span class="text-[#FFE500] glow-text-amber">TERMINAL</span>
                        </h2>

                        <p class="max-w-xl mx-auto text-slate-300 text-base sm:text-lg mb-12 font-light">
                            Deploy your personal ledger today. Complete tracking, instant CSV imports, recurring automation, and intelligent analysis.
                        </p>

                        <!-- Dual Action Pods -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 max-w-2xl mx-auto">
                            @auth
                                <div class="slide-5-box p-8 bg-[#0f121a] border border-[#FFE500] clip-chamfer-lg glow-amber-box sm:col-span-2 text-center">
                                    <p class="font-mono-tech text-xs text-[#FFE500] uppercase mb-2">AUTHENTICATED AGENT DETECTED</p>
                                    <h3 class="text-2xl font-bold text-white mb-4">OPERATING VAULT READY</h3>
                                    <a href="{{ url('/dashboard') }}" class="inline-block px-8 py-3 bg-[#FFE500] hover:bg-yellow-300 text-black font-mono-tech text-sm uppercase font-bold tracking-wider clip-chamfer transition glow-amber-btn">
                                        {{ __('Dashboard') }} &rarr;
                                    </a>
                                </div>
                            @else
                                <div class="slide-5-box p-8 bg-[#0f121a] border border-[#FFE500] clip-chamfer-lg glow-amber-box flex flex-col justify-between">
                                    <div>
                                        <div class="w-10 h-10 mb-4 bg-[#FFE500] text-black font-mono-tech font-bold flex items-center justify-center clip-chamfer text-base">
                                            01
                                        </div>
                                        <h3 class="text-xl font-bold text-white mb-2">NEW AGENT ACCESS</h3>
                                        <p class="text-xs text-gray-400 font-mono-tech leading-relaxed mb-6">Create your isolated encryption vault with custom base currency configuration.</p>
                                    </div>
                                    <a href="{{ route('register') }}" class="w-full py-3 bg-[#FFE500] hover:bg-yellow-300 text-black font-mono-tech text-xs uppercase font-bold tracking-wider clip-chamfer transition glow-amber-btn text-center block">
                                        {{ __('Register') }} &rarr;
                                    </a>
                                </div>

                                <div class="slide-5-box p-8 bg-[#0c1017] border border-cyan-500/40 clip-chamfer-lg glow-cyan-box flex flex-col justify-between">
                                    <div>
                                        <div class="w-10 h-10 mb-4 bg-cyan-400 text-black font-mono-tech font-bold flex items-center justify-center clip-chamfer text-base">
                                            02
                                        </div>
                                        <h3 class="text-xl font-bold text-white mb-2">OPERATOR LOGIN</h3>
                                        <p class="text-xs text-gray-400 font-mono-tech leading-relaxed mb-6">Resume session to access recurring schedulers, transactions, and Gemini insight telemetry.</p>
                                    </div>
                                    <a href="{{ route('login') }}" class="w-full py-3 bg-cyan-400 hover:bg-cyan-300 text-black font-mono-tech text-xs uppercase font-bold tracking-wider clip-chamfer transition text-center block font-bold">
                                        {{ __('Log in') }} &rarr;
                                    </a>
                                </div>
                            @endauth
                        </div>

                    </div>
                </section>

            </div>

        </div>

    </body>
</html>
