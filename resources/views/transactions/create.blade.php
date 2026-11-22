<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-white">{{ __('Add transaction') }}</h1>
                <p class="text-xs text-zinc-400 mt-1">{{ __('Record a new inflow or outflow to your financial ledger.') }}</p>
            </div>
            <a href="{{ route('transactions.index') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-white/[0.08] bg-zinc-900/60 text-xs font-medium text-zinc-300 hover:text-white hover:bg-zinc-800 transition">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                </svg>
                {{ __('Back to transactions') }}
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="sure-card p-6 sm:p-8 rounded-2xl border border-white/[0.07] bg-[#0c0d12]/90 shadow-xl backdrop-blur-md">
                <form method="POST" action="{{ route('transactions.store') }}">
                    @csrf

                    @include('transactions.partials.form', [
                        'transaction' => $transaction,
                        'types' => $types,
                        'categories' => $categories,
                    ])
                </form>
            </div>
        </div>
    </div>
</x-app-layout>