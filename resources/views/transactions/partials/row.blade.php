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

<div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 px-6 py-3 hover:bg-gray-50">
    <div class="flex min-w-0 flex-wrap items-center gap-3">
        <span @class([
            'inline-flex rounded-full px-2 py-1 text-xs font-medium',
            'bg-emerald-100 text-emerald-800' => $transaction->type === \App\Enums\TransactionType::Income,
            'bg-rose-100 text-rose-800' => $transaction->type === \App\Enums\TransactionType::Expense,
        ])>{{ $transaction->type->label() }}</span>

        <span class="text-sm text-gray-900">{{ $transaction->category ?? '—' }}</span>

        <span class="text-sm text-gray-500">
            {{ $transaction->occurred_at->format('H:i') }} ·
            {{ \Illuminate\Support\Str::limit($transaction->note, 40) }}
        </span>
    </div>

    <div class="flex items-center gap-4">
        <span class="whitespace-nowrap text-right text-sm font-medium">
            <span @class([
                'text-emerald-600' => $transaction->type === \App\Enums\TransactionType::Income,
                'text-gray-900' => $transaction->type === \App\Enums\TransactionType::Expense,
            ])>
                {{ $transaction->type->sign() > 0 ? '+' : '−' }}{{ number_format($transaction->amount, 2) }}
                <span class="text-xs font-normal text-gray-400">{{ $transaction->currency }}</span>
            </span>
            @if ($transaction->base_amount !== null && $transaction->currency !== $transaction->base_currency)
                <span class="block text-xs font-normal text-gray-400">
                    {{ $transaction->type->sign() > 0 ? '+' : '−' }}{{ number_format($transaction->base_amount, 2) }}
                    {{ $transaction->base_currency }}
                </span>
            @endif
        </span>

        <span class="whitespace-nowrap text-sm">
            <a href="{{ route('transactions.show', $transaction) }}"
                class="text-gray-600 hover:text-gray-900">{{ __('View') }}</a>
            <a href="{{ route('transactions.edit', $transaction) }}"
                class="ml-3 text-indigo-600 hover:text-indigo-900">{{ __('Edit') }}</a>
        </span>
    </div>
</div>