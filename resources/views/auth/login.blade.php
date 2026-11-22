<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <div class="mb-6">
        <h1 class="text-xl font-bold tracking-tight text-white">{{ __('Log in') }}</h1>
        <p class="text-xs text-zinc-400 mt-1">{{ __('Sign in to access your financial ledger and insights.') }}</p>
    </div>

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="operator@example.com" />
            <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
        </div>

        <!-- Password -->
        <div>
            <div class="flex items-center justify-between mb-1.5">
                <x-input-label for="password" :value="__('Password')" class="mb-0" />
                @if (Route::has('password.request'))
                    <a class="text-[11px] text-zinc-400 hover:text-zinc-200 transition-colors" href="{{ route('password.request') }}">
                        {{ __('Forgot your password?') }}
                    </a>
                @endif
            </div>

            <x-text-input id="password" class="block w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password"
                            placeholder="••••••••" />

            <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
        </div>

        <!-- Remember Me -->
        <div class="flex items-center justify-between pt-1">
            <label for="remember_me" class="inline-flex items-center cursor-pointer">
                <input id="remember_me" type="checkbox" class="rounded bg-zinc-900 border-zinc-800 text-emerald-500 shadow-sm focus:ring-emerald-500/20" name="remember">
                <span class="ms-2 text-xs text-zinc-400">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="pt-2 space-y-3">
            <x-primary-button class="w-full justify-center py-2.5 text-xs">
                {{ __('Log in') }}
            </x-primary-button>

            @if (Route::has('register'))
                <a href="{{ route('register') }}" class="w-full inline-flex items-center justify-center px-4 py-2 bg-zinc-900/80 border border-zinc-800 text-zinc-300 hover:bg-zinc-800 hover:text-white font-medium text-xs rounded-lg transition duration-150 shadow-sm">
                    {{ __('Register') }}
                </a>
            @endif
        </div>
    </form>

    @if (Route::has('register'))
        <div class="mt-6 pt-4 text-center border-t border-white/[0.06] text-xs text-zinc-400">
            <span>{{ __("Don't have an account?") }}</span>
            <a href="{{ route('register') }}" class="ml-1 text-emerald-400 hover:text-emerald-300 font-medium transition-colors">
                {{ __('Register') }}
            </a>
        </div>
    @endif
</x-guest-layout>
