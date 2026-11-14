<x-guest-layout>
    <div class="mb-6">
        <h1 class="text-xl font-bold tracking-tight text-white">{{ __('Register') }}</h1>
        <p class="text-xs text-zinc-400 mt-1">{{ __('Create your personal financial vault with multi-currency tracking.') }}</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" class="block w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" placeholder="Alex Chen" />
            <x-input-error :messages="$errors->get('name')" class="mt-1.5" />
        </div>

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block w-full" type="email" name="email" :value="old('email')" required autocomplete="username" placeholder="alex@example.com" />
            <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
        </div>

        <!-- Password -->
        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="block w-full"
                            type="password"
                            name="password"
                            required autocomplete="new-password"
                            placeholder="••••••••" />
            <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
        </div>

        <!-- Confirm Password -->
        <div>
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
            <x-text-input id="password_confirmation" class="block w-full"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password"
                            placeholder="••••••••" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1.5" />
        </div>

        <div class="pt-2 space-y-3">
            <x-primary-button class="w-full justify-center py-2.5 text-xs">
                {{ __('Register') }}
            </x-primary-button>
        </div>
    </form>

    <div class="mt-6 pt-4 text-center border-t border-white/[0.06] text-xs text-zinc-400">
        <span>{{ __('Already registered?') }}</span>
        <a href="{{ route('login') }}" class="ml-1 text-emerald-400 hover:text-emerald-300 font-medium transition-colors">
            {{ __('Log in') }}
        </a>
    </div>
</x-guest-layout>
