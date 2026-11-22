<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-white">{{ __('Transaction') }}</h1>
                <p class="text-xs text-zinc-400 mt-1">{{ __('Detailed record information and breakdown.') }}</p>
            </div>
            <a href="{{ route('transactions.index') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-white/[0.08] bg-zinc-900/60 text-xs font-medium text-zinc-300 hover:text-white hover:bg-zinc-800 transition">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                </svg>
                {{ __('Back to list') }}
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="sure-card p-6 sm:p-8 rounded-2xl border border-white/[0.07] bg-[#0c0d12]/90 shadow-xl backdrop-blur-md space-y-6">
                <div class="flex items-baseline justify-between border-b border-white/[0.06] pb-5">
                    <span class="text-xs font-medium text-zinc-400 uppercase tracking-wider">{{ __('Amount') }}</span>
                    <span @class([
                        'text-2xl font-bold font-mono-num tracking-tight',
                        'text-emerald-400' => $transaction->type === \App\Enums\TransactionType::Income,
                        'text-zinc-100' => $transaction->type === \App\Enums\TransactionType::Expense,
                    ])>
                        {{ $transaction->type->sign() > 0 ? '+' : '−' }}{{ number_format($transaction->amount, 2) }}
                        <span class="text-xs font-normal text-zinc-500 ml-1">{{ $transaction->currency }}</span>
                    </span>
                </div>

                <dl class="grid grid-cols-2 gap-4 text-xs">
                    <dt class="text-zinc-400 font-medium">{{ __('Type') }}</dt>
                    <dd class="text-zinc-200">
                        <span @class([
                            'inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium border',
                            'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' => $transaction->type === \App\Enums\TransactionType::Income,
                            'bg-zinc-800/80 text-zinc-300 border-white/[0.06]' => $transaction->type === \App\Enums\TransactionType::Expense,
                        ])>
                            {{ $transaction->type->label() }}
                        </span>
                    </dd>

                    <dt class="text-zinc-400 font-medium">{{ __('Date') }}</dt>
                    <dd class="text-zinc-200 font-mono-num">{{ $transaction->occurred_at->format('Y-m-d H:i') }}</dd>

                    <dt class="text-zinc-400 font-medium">{{ __('Category') }}</dt>
                    <dd class="text-zinc-200">
                        @if ($transaction->isSplit())
                            <span class="inline-flex items-center gap-1 rounded bg-zinc-800/80 border border-white/[0.06] px-2 py-0.5 text-[11px] font-medium text-zinc-300">
                                <span>{{ __('Split') }}</span>
                                <span class="text-zinc-500">({{ $transaction->splits->count() }})</span>
                            </span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded bg-zinc-800/50 border border-white/[0.04] text-zinc-300 text-[11px]">
                                {{ $transaction->category ?? '—' }}
                            </span>
                        @endif
                    </dd>

                    <dt class="text-zinc-400 font-medium">{{ __('Note') }}</dt>
                    <dd class="text-zinc-300">{{ $transaction->note ?? '—' }}</dd>

                    @if ($transaction->base_amount !== null && $transaction->currency !== $transaction->base_currency)
                        <dt class="text-zinc-400 font-medium">{{ __('In base currency') }}</dt>
                        <dd class="text-zinc-200 font-mono-num">
                            {{ number_format($transaction->base_amount, 2) }} {{ $transaction->base_currency }}
                            <span class="block text-[11px] font-normal text-zinc-500 mt-0.5">
                                {{ __('Rate applied') }}: 1 {{ $transaction->currency }} =
                                {{ rtrim(rtrim(number_format((float) $transaction->applied_rate, 4, '.', ''), '0'), '.') }}
                                {{ $transaction->base_currency }}
                            </span>
                        </dd>
                    @endif
                </dl>

                @if ($transaction->isSplit())
                    <div class="border-t border-white/[0.06] pt-5">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-zinc-200 mb-3">{{ __('Split Details') }}</h3>
                        <div class="overflow-hidden border border-white/[0.08] rounded-xl bg-[#09090b]/80">
                            <table class="min-w-full divide-y divide-white/[0.06] text-xs">
                                <thead class="bg-zinc-900/50">
                                    <tr class="text-[11px] text-zinc-400 uppercase tracking-wider">
                                        <th class="px-4 py-2.5 text-left font-medium">{{ __('Category') }}</th>
                                        <th class="px-4 py-2.5 text-left font-medium">{{ __('Note') }}</th>
                                        <th class="px-4 py-2.5 text-right font-medium">{{ __('Amount') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/[0.04]">
                                    @foreach ($transaction->splits as $split)
                                        <tr class="hover:bg-white/[0.02] transition">
                                            <td class="px-4 py-2.5 font-medium text-zinc-200">{{ $split->category }}</td>
                                            <td class="px-4 py-2.5 text-zinc-400">{{ $split->note ?? '—' }}</td>
                                            <td class="px-4 py-2.5 text-right font-medium font-mono-num text-zinc-100 whitespace-nowrap">
                                                {{ number_format($split->amount, 2) }} {{ $transaction->currency }}
                                                @if ($transaction->base_amount !== null && $transaction->currency !== $transaction->base_currency)
                                                    <span class="block text-[11px] font-normal text-zinc-500">
                                                        {{ number_format($split->base_amount, 2) }} {{ $transaction->base_currency }}
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <div class="flex items-center gap-3 border-t border-white/[0.06] pt-5">
                    <a href="{{ route('transactions.edit', $transaction) }}">
                        <x-primary-button>{{ __('Edit') }}</x-primary-button>
                    </a>
                    <a href="{{ route('transactions.index') }}" class="px-4 py-2 rounded-lg text-xs font-medium text-zinc-400 hover:text-white hover:bg-zinc-800 transition">
                        {{ __('Back to list') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>