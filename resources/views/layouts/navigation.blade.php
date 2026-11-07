<nav x-data="{ open: false }" class="bg-[#0c1017]/95 border-b border-white/10 backdrop-blur-md sticky top-0 z-40">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-3 group">
                        <x-application-logo class="block h-9 w-9" />
                        <div class="hidden md:block">
                            <span class="font-bold text-sm tracking-wider text-white group-hover:text-[#FFE500] transition-colors font-sans">FINANCE // <span class="text-[#FFE500]">TRACKER</span></span>
                            <span class="block text-[8px] font-mono tracking-widest text-[#00F0FF] uppercase">TACTICAL HUD v2.4</span>
                        </div>
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-2 sm:-my-px sm:ms-8 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('Dashboard') }}
                    </x-nav-link>

                    <x-nav-link :href="route('transactions.index')" :active="request()->routeIs('transactions.*')">
                        {{ __('Transactions') }}
                    </x-nav-link>

                    <x-nav-link :href="route('recurring.index')" :active="request()->routeIs('recurring.*')">
                        {{ __('Recurring') }}
                    </x-nav-link>

                    <x-nav-link :href="route('budgets.index')" :active="request()->routeIs('budgets.*')">
                        {{ __('Budgets') }}
                    </x-nav-link>

                    <x-nav-link :href="route('insights.index')" :active="request()->routeIs('insights.*')">
                        {{ __('Insights') }}
                    </x-nav-link>
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:gap-4 sm:ms-6">
                <x-language-switcher />
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-white/15 text-xs font-mono rounded-none clip-chamfer text-slate-300 bg-[#0f141c] hover:text-[#FFE500] hover:border-[#FFE500]/50 focus:outline-none transition ease-in-out duration-150">
                            <span class="inline-block w-2 h-2 rounded-full bg-[#FFE500] me-2 animate-pulse"></span>
                            <div>{{ Auth::user()->name }}</div>

                            <div class="ms-2 text-slate-400">
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
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-none clip-chamfer text-slate-400 hover:text-[#FFE500] hover:bg-white/5 focus:outline-none transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden border-t border-white/10 bg-[#090d14]/95 backdrop-blur-md">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>

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

            <div class="pt-3 px-4">
                <x-language-switcher />
            </div>
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-2 border-t border-white/10 bg-[#07090e]">
            <div class="px-4">
                <div class="font-mono text-sm font-semibold text-[#FFE500]">{{ Auth::user()->name }}</div>
                <div class="font-mono text-xs text-slate-400">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
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

