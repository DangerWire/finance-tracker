<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-white">{{ __('Settings & Profile') }}</h1>
                <p class="text-xs text-zinc-400 mt-1">{{ __('Manage your account parameters, primary ledger currency, and security credentials.') }}</p>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="sure-card p-6 sm:p-8 rounded-2xl border border-white/[0.07] bg-[#0c0d12]/90 shadow-xl backdrop-blur-md">
                <div class="max-w-2xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="sure-card p-6 sm:p-8 rounded-2xl border border-white/[0.07] bg-[#0c0d12]/90 shadow-xl backdrop-blur-md">
                <div class="max-w-2xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <div class="sure-card p-6 sm:p-8 rounded-2xl border border-rose-500/20 bg-[#0c0d12]/90 shadow-xl backdrop-blur-md">
                <div class="max-w-2xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
