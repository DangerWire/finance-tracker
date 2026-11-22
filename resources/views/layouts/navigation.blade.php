@php
    $transactionsGroupActive = request()->routeIs('transactions.*', 'recurring.*', 'budgets.*', 'insights.*');
    $socialGroupActive = request()->routeIs('friends.*', 'debts.*');
    $friendsUrl = Route::has('friends.index') ? route('friends.index') : url('/friends');
    $debtsUrl = Route::has('debts.index') ? route('debts.index') : url('/debts');
@endphp

<nav x-data="{ open: false }" aria-label="Primary" class="bg-[#0c0d12]/90 border-b border-white/[0.06] backdrop-blur-md sticky top-0 z-40">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center gap-6">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 group rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400/70">
                        <x-application-logo class="h-8 w-8 transition-transform group-hover:scale-105" />
                        <span class="font-semibold text-sm tracking-tight text-white group-hover:text-zinc-200 transition-colors">
                            Finance<span class="text-emerald-400">Tracker</span>
                        </span>
                    </a>
                </div>

                <!-- Compact Grouped Desktop Navigation: Dashboard · Transactions · Social -->
                <ul class="hidden sm:flex items-center gap-1 list-none m-0 p-0">
                    <!-- 1. Dashboard -->
                    <li>
                        <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                            @if (request()->routeIs('dashboard'))
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400" aria-hidden="true"></span>
                            @endif
                            <span>{{ __('Dashboard') }}</span>
                        </x-nav-link>
                    </li>

                    <!-- 2. Transactions Group Dropdown -->
                    <li
                        class="relative"
                        x-data="{
                            open: false,
                            hoverPreview: false,
                            onPointerEnter(e) {
                                if (window.matchMedia('(hover: hover)').matches && e.pointerType !== 'touch') {
                                    if (! this.open) {
                                        this.open = true;
                                        this.hoverPreview = true;
                                    }
                                }
                            },
                            onPointerLeave() {
                                if (this.hoverPreview) {
                                    this.open = false;
                                    this.hoverPreview = false;
                                }
                            },
                            toggleClick() {
                                if (this.open && this.hoverPreview) {
                                    this.hoverPreview = false;
                                    return;
                                }
                                this.open = ! this.open;
                                this.hoverPreview = false;
                            },
                            close() {
                                this.open = false;
                                this.hoverPreview = false;
                            },
                            onFocusOut(e) {
                                if (e.relatedTarget && ! this.$el.contains(e.relatedTarget)) {
                                    this.close();
                                }
                            }
                        }"
                        @pointerenter="onPointerEnter($event)"
                        @pointerleave="onPointerLeave()"
                        @focusout="onFocusOut($event)"
                        @click.outside="close()"
                        @keydown.escape.window="if (open) { close(); $refs.trigger.focus(); }"
                    >
                        <button
                            x-ref="trigger"
                            type="button"
                            @click="toggleClick()"
                            :aria-expanded="open.toString()"
                            aria-controls="nav-transactions-menu"
                            @class([
                                'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium transition duration-150 ease-in-out focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400/70',
                                'text-white bg-white/[0.08] border border-white/[0.1] shadow-sm' => $transactionsGroupActive,
                                'text-zinc-400 border border-transparent hover:text-zinc-100 hover:bg-white/[0.04]' => ! $transactionsGroupActive,
                            ])
                        >
                            @if ($transactionsGroupActive)
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400" aria-hidden="true"></span>
                            @endif
                            <span>{{ __('Transactions') }}</span>
                            <svg
                                class="w-3.5 h-3.5 text-zinc-500 transition-transform duration-150 motion-reduce:transition-none"
                                :class="{ 'rotate-180 text-zinc-300': open }"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="2"
                                stroke="currentColor"
                                aria-hidden="true"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                            </svg>
                        </button>

                        <!-- Seamless hover bridge (pt-2 keeps pointer inside <li> across the visual gap) -->
                        <div
                            id="nav-transactions-menu"
                            x-show="open"
                            x-transition:enter="transition ease-out duration-150 motion-reduce:transition-none"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-100 motion-reduce:transition-none"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            style="display: none;"
                            class="absolute left-0 top-full pt-2 w-64 origin-top-left z-50"
                        >
                            <ul class="p-1.5 rounded-xl bg-[#111218]/95 border border-white/[0.12] shadow-2xl ring-1 ring-black/60 backdrop-blur-xl space-y-0.5 list-none m-0">
                                <li>
                                    <a
                                        href="{{ route('transactions.index') }}"
                                        @if (request()->routeIs('transactions.*')) aria-current="page" @endif
                                        @class([
                                            'flex items-start gap-2.5 px-2.5 py-2 rounded-lg transition duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400/70',
                                            'bg-white/[0.08] border border-white/[0.08] text-white' => request()->routeIs('transactions.*'),
                                            'border border-transparent text-zinc-300 hover:bg-white/[0.05] hover:text-white' => ! request()->routeIs('transactions.*'),
                                        ])
                                    >
                                        <span @class([
                                            'mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg border',
                                            'bg-emerald-500/15 border-emerald-500/30 text-emerald-400' => request()->routeIs('transactions.*'),
                                            'bg-zinc-900 border-white/[0.07] text-zinc-400' => ! request()->routeIs('transactions.*'),
                                        ])>
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                                            </svg>
                                        </span>
                                        <span class="min-w-0">
                                            <span class="block text-xs font-medium leading-tight">{{ __('Transactions') }}</span>
                                            <span class="block text-[11px] text-zinc-500 mt-0.5">{{ __('Ledger, import & export') }}</span>
                                        </span>
                                    </a>
                                </li>

                                <li>
                                    <a
                                        href="{{ route('recurring.index') }}"
                                        @if (request()->routeIs('recurring.*')) aria-current="page" @endif
                                        @class([
                                            'flex items-start gap-2.5 px-2.5 py-2 rounded-lg transition duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400/70',
                                            'bg-white/[0.08] border border-white/[0.08] text-white' => request()->routeIs('recurring.*'),
                                            'border border-transparent text-zinc-300 hover:bg-white/[0.05] hover:text-white' => ! request()->routeIs('recurring.*'),
                                        ])
                                    >
                                        <span @class([
                                            'mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg border',
                                            'bg-emerald-500/15 border-emerald-500/30 text-emerald-400' => request()->routeIs('recurring.*'),
                                            'bg-zinc-900 border-white/[0.07] text-zinc-400' => ! request()->routeIs('recurring.*'),
                                        ])>
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                                            </svg>
                                        </span>
                                        <span class="min-w-0">
                                            <span class="block text-xs font-medium leading-tight">{{ __('Recurring') }}</span>
                                            <span class="block text-[11px] text-zinc-500 mt-0.5">{{ __('Scheduled bills & income') }}</span>
                                        </span>
                                    </a>
                                </li>

                                <li>
                                    <a
                                        href="{{ route('budgets.index') }}"
                                        @if (request()->routeIs('budgets.*')) aria-current="page" @endif
                                        @class([
                                            'flex items-start gap-2.5 px-2.5 py-2 rounded-lg transition duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400/70',
                                            'bg-white/[0.08] border border-white/[0.08] text-white' => request()->routeIs('budgets.*'),
                                            'border border-transparent text-zinc-300 hover:bg-white/[0.05] hover:text-white' => ! request()->routeIs('budgets.*'),
                                        ])
                                    >
                                        <span @class([
                                            'mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg border',
                                            'bg-emerald-500/15 border-emerald-500/30 text-emerald-400' => request()->routeIs('budgets.*'),
                                            'bg-zinc-900 border-white/[0.07] text-zinc-400' => ! request()->routeIs('budgets.*'),
                                        ])>
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 107.5 7.5h-7.5V6z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0013.5 3v7.5z" />
                                            </svg>
                                        </span>
                                        <span class="min-w-0">
                                            <span class="block text-xs font-medium leading-tight">{{ __('Budgets') }}</span>
                                            <span class="block text-[11px] text-zinc-500 mt-0.5">{{ __('Monthly spending limits') }}</span>
                                        </span>
                                    </a>
                                </li>

                                <li>
                                    <a
                                        href="{{ route('insights.index') }}"
                                        @if (request()->routeIs('insights.*')) aria-current="page" @endif
                                        @class([
                                            'flex items-start gap-2.5 px-2.5 py-2 rounded-lg transition duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400/70',
                                            'bg-white/[0.08] border border-white/[0.08] text-white' => request()->routeIs('insights.*'),
                                            'border border-transparent text-zinc-300 hover:bg-white/[0.05] hover:text-white' => ! request()->routeIs('insights.*'),
                                        ])
                                    >
                                        <span @class([
                                            'mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg border',
                                            'bg-emerald-500/15 border-emerald-500/30 text-emerald-400' => request()->routeIs('insights.*'),
                                            'bg-zinc-900 border-white/[0.07] text-zinc-400' => ! request()->routeIs('insights.*'),
                                        ])>
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
                                            </svg>
                                        </span>
                                        <span class="min-w-0">
                                            <span class="block text-xs font-medium leading-tight">{{ __('Insights') }}</span>
                                            <span class="block text-[11px] text-zinc-500 mt-0.5">{{ __('AI spending analysis') }}</span>
                                        </span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>

                    <!-- 3. Social Group Dropdown -->
                    <li
                        class="relative"
                        x-data="{
                            open: false,
                            hoverPreview: false,
                            onPointerEnter(e) {
                                if (window.matchMedia('(hover: hover)').matches && e.pointerType !== 'touch') {
                                    if (! this.open) {
                                        this.open = true;
                                        this.hoverPreview = true;
                                    }
                                }
                            },
                            onPointerLeave() {
                                if (this.hoverPreview) {
                                    this.open = false;
                                    this.hoverPreview = false;
                                }
                            },
                            toggleClick() {
                                if (this.open && this.hoverPreview) {
                                    this.hoverPreview = false;
                                    return;
                                }
                                this.open = ! this.open;
                                this.hoverPreview = false;
                            },
                            close() {
                                this.open = false;
                                this.hoverPreview = false;
                            },
                            onFocusOut(e) {
                                if (e.relatedTarget && ! this.$el.contains(e.relatedTarget)) {
                                    this.close();
                                }
                            }
                        }"
                        @pointerenter="onPointerEnter($event)"
                        @pointerleave="onPointerLeave()"
                        @focusout="onFocusOut($event)"
                        @click.outside="close()"
                        @keydown.escape.window="if (open) { close(); $refs.trigger.focus(); }"
                    >
                        <button
                            x-ref="trigger"
                            type="button"
                            @click="toggleClick()"
                            :aria-expanded="open.toString()"
                            aria-controls="nav-social-menu"
                            @class([
                                'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium transition duration-150 ease-in-out focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400/70',
                                'text-white bg-white/[0.08] border border-white/[0.1] shadow-sm' => $socialGroupActive,
                                'text-zinc-400 border border-transparent hover:text-zinc-100 hover:bg-white/[0.04]' => ! $socialGroupActive,
                            ])
                        >
                            @if ($socialGroupActive)
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400" aria-hidden="true"></span>
                            @endif
                            <span>{{ __('Social') }}</span>
                            <svg
                                class="w-3.5 h-3.5 text-zinc-500 transition-transform duration-150 motion-reduce:transition-none"
                                :class="{ 'rotate-180 text-zinc-300': open }"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="2"
                                stroke="currentColor"
                                aria-hidden="true"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                            </svg>
                        </button>

                        <!-- Seamless hover bridge -->
                        <div
                            id="nav-social-menu"
                            x-show="open"
                            x-transition:enter="transition ease-out duration-150 motion-reduce:transition-none"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-100 motion-reduce:transition-none"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            style="display: none;"
                            class="absolute left-0 top-full pt-2 w-60 origin-top-left z-50"
                        >
                            <ul class="p-1.5 rounded-xl bg-[#111218]/95 border border-white/[0.12] shadow-2xl ring-1 ring-black/60 backdrop-blur-xl space-y-0.5 list-none m-0">
                                <li>
                                    <a
                                        href="{{ $friendsUrl }}"
                                        @if (request()->routeIs('friends.*')) aria-current="page" @endif
                                        @class([
                                            'flex items-start gap-2.5 px-2.5 py-2 rounded-lg transition duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400/70',
                                            'bg-white/[0.08] border border-white/[0.08] text-white' => request()->routeIs('friends.*'),
                                            'border border-transparent text-zinc-300 hover:bg-white/[0.05] hover:text-white' => ! request()->routeIs('friends.*'),
                                        ])
                                    >
                                        <span @class([
                                            'mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg border',
                                            'bg-emerald-500/15 border-emerald-500/30 text-emerald-400' => request()->routeIs('friends.*'),
                                            'bg-zinc-900 border-white/[0.07] text-zinc-400' => ! request()->routeIs('friends.*'),
                                        ])>
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                                            </svg>
                                        </span>
                                        <span class="min-w-0">
                                            <span class="block text-xs font-medium leading-tight">{{ __('Friends') }}</span>
                                            <span class="block text-[11px] text-zinc-500 mt-0.5">{{ __('Connections & requests') }}</span>
                                        </span>
                                    </a>
                                </li>

                                <li>
                                    <a
                                        href="{{ $debtsUrl }}"
                                        @if (request()->routeIs('debts.*')) aria-current="page" @endif
                                        @class([
                                            'flex items-start gap-2.5 px-2.5 py-2 rounded-lg transition duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400/70',
                                            'bg-white/[0.08] border border-white/[0.08] text-white' => request()->routeIs('debts.*'),
                                            'border border-transparent text-zinc-300 hover:bg-white/[0.05] hover:text-white' => ! request()->routeIs('debts.*'),
                                        ])
                                    >
                                        <span @class([
                                            'mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg border',
                                            'bg-emerald-500/15 border-emerald-500/30 text-emerald-400' => request()->routeIs('debts.*'),
                                            'bg-zinc-900 border-white/[0.07] text-zinc-400' => ! request()->routeIs('debts.*'),
                                        ])>
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                                            </svg>
                                        </span>
                                        <span class="min-w-0">
                                            <span class="block text-xs font-medium leading-tight">{{ __('Debts') }}</span>
                                            <span class="block text-[11px] text-zinc-500 mt-0.5">{{ __('Shared loans & settlements') }}</span>
                                        </span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                </ul>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:gap-3 sm:ms-6">
                <x-language-switcher />
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button type="button" class="inline-flex items-center gap-2 px-3 py-1.5 border border-white/[0.08] text-xs font-medium rounded-lg text-zinc-300 bg-zinc-900/80 hover:bg-zinc-800/80 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400/70 transition ease-in-out duration-150">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400" aria-hidden="true"></span>
                            <div>{{ Auth::user()->name }}</div>

                            <div class="ms-1 text-zinc-400" aria-hidden="true">
                                <svg class="fill-current h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>
                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button
                    type="button"
                    @click="open = ! open"
                    :aria-expanded="open.toString()"
                    aria-controls="mobile-nav-menu"
                    class="inline-flex items-center justify-center p-2 rounded-lg text-zinc-400 hover:text-white hover:bg-zinc-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400/70 transition duration-150 ease-in-out"
                >
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div id="mobile-nav-menu" :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden border-t border-white/[0.06] bg-[#0c0d12]/95 backdrop-blur-md">
        <div class="pt-3 pb-4 space-y-1 px-4">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>

            <div class="pt-3 pb-1 px-3 text-[10px] font-semibold uppercase tracking-wider text-zinc-500">
                {{ __('Transactions') }}
            </div>
            <x-responsive-nav-link :href="route('transactions.index')" :active="request()->routeIs('transactions.*')">
                {{ __('Transactions') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('recurring.index')" :active="request()->routeIs('recurring.*')">
                {{ __('Recurring') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('budgets.index')" :active="request()->routeIs('budgets.*')">
                {{ __('Budgets') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('insights.index')" :active="request()->routeIs('insights.*')">
                {{ __('Insights') }}
            </x-responsive-nav-link>

            <div class="pt-3 pb-1 px-3 text-[10px] font-semibold uppercase tracking-wider text-zinc-500">
                {{ __('Social') }}
            </div>
            <x-responsive-nav-link :href="$friendsUrl" :active="request()->routeIs('friends.*')">
                {{ __('Friends') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="$debtsUrl" :active="request()->routeIs('debts.*')">
                {{ __('Debts') }}
            </x-responsive-nav-link>

            <div class="pt-3">
                <x-language-switcher />
            </div>
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-2 border-t border-white/[0.06] bg-[#09090b]">
            <div class="px-4">
                <div class="font-medium text-sm text-zinc-100">{{ Auth::user()->name }}</div>
                <div class="font-mono-num text-xs text-zinc-400">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1 px-2">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
