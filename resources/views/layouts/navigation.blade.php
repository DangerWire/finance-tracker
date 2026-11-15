<nav x-data="{ open: false }" class="bg-[#0c0d12]/90 border-b border-white/[0.06] backdrop-blur-md sticky top-0 z-40">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center gap-8">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 group">
                        <x-application-logo class="h-8 w-8 transition-transform group-hover:scale-105" />
                        <span class="font-semibold text-sm tracking-tight text-white group-hover:text-zinc-200 transition-colors">
                            Finance<span class="text-emerald-400">Tracker</span>
                        </span>
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-1 sm:-my-px sm:flex items-center">
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

                    <x-nav-link :href="Route::has('friends.index') ? route('friends.index') : url('/friends')" :active="request()->routeIs('friends.*')">
                        {{ __('Friends') }}
                    </x-nav-link>

                    <x-nav-link :href="Route::has('debts.index') ? route('debts.index') : url('/debts')" :active="request()->routeIs('debts.*')">
                        {{ __('Debts') }}
                    </x-nav-link>
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:gap-3 sm:ms-6">
                <x-language-switcher />
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center gap-2 px-3 py-1.5 border border-white/[0.08] text-xs font-medium rounded-lg text-zinc-300 bg-zinc-900/80 hover:bg-zinc-800/80 hover:text-white focus:outline-none transition ease-in-out duration-150">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                            <div>{{ Auth::user()->name }}</div>

                            <div class="ms-1 text-zinc-400">
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
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-lg text-zinc-400 hover:text-white hover:bg-zinc-800 focus:outline-none transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden border-t border-white/[0.06] bg-[#0c0d12]/95 backdrop-blur-md">
        <div class="pt-2 pb-3 space-y-1 px-4">
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

            <x-responsive-nav-link :href="Route::has('friends.index') ? route('friends.index') : url('/friends')" :active="request()->routeIs('friends.*')">
                {{ __('Friends') }}
            </x-responsive-nav-link>

            <x-responsive-nav-link :href="Route::has('debts.index') ? route('debts.index') : url('/debts')" :active="request()->routeIs('debts.*')">
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
