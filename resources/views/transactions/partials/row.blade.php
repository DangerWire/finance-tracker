@php
    /**
     * One transaction rendered as a compact row.
     *
     * Used by the grouped-by-day view, where rows sit inside a disclosure
     * rather than inside a table.
     *
     * @var \App\Models\Transaction $transaction
     */
@endphp

<div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1.5 px-6 py-3.5 hover:bg-white/[0.02] transition-colors">
    <div class="flex min-w-0 flex-wrap items-center gap-3">
        <span @class([
            'inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium border',
            'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' => $transaction->type === \App\Enums\TransactionType::Income,
            'bg-rose-500/10 text-rose-400 border-rose-500/20' => $transaction->type === \App\Enums\TransactionType::Expense,
        ])>{{ $transaction->type->label() }}</span>

        @if ($transaction->isSplit())
            <span class="inline-flex items-center gap-1 rounded-md bg-zinc-800/80 px-2 py-0.5 text-xs font-medium text-zinc-300 border border-zinc-700">
                <span>{{ __('Split') }}</span>
                <span class="text-zinc-500 font-mono-num">({{ $transaction->splits->count() }})</span>
            </span>
        @else
            <span class="text-sm font-medium text-zinc-200">{{ $transaction->category ?? '—' }}</span>
        @endif

        <span class="text-xs text-zinc-400">
            <span class="font-mono-num">{{ $transaction->occurred_at->format('H:i') }}</span> ·
            {{ \Illuminate\Support\Str::limit($transaction->note, 45) }}
        </span>
    </div>

    <div class="flex items-center gap-4">
        <span class="whitespace-nowrap text-right text-sm font-mono-num font-medium">
            <span @class([
                'text-emerald-400' => $transaction->type === \App\Enums\TransactionType::Income,
                'text-zinc-200' => $transaction->type === \App\Enums\TransactionType::Expense,
            ])>
                {{ $transaction->type->sign() > 0 ? '+' : '−' }}{{ number_format($transaction->amount, 2) }}
                <span class="text-xs font-normal text-zinc-500 ml-0.5">{{ $transaction->currency }}</span>
            </span>
            @if ($transaction->base_amount !== null && $transaction->currency !== $transaction->base_currency)
                <span class="block text-[11px] font-normal text-zinc-500">
                    {{ $transaction->type->sign() > 0 ? '+' : '−' }}{{ number_format($transaction->base_amount, 2) }}
                    {{ $transaction->base_currency }}
                </span>
            @endif
        </span>

        <span class="whitespace-nowrap text-xs">
            <a href="{{ route('transactions.show', $transaction) }}"
                class="text-zinc-400 hover:text-zinc-200 transition-colors mr-3">{{ __('View') }}</a>
            <a href="{{ route('transactions.edit', $transaction) }}"
                class="text-emerald-400 hover:text-emerald-300 transition-colors font-medium">{{ __('Edit') }}</a>
        </span>
    </div>
</div>
