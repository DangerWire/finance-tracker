<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Transaction') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-4">
                    <div class="flex items-baseline justify-between border-b border-gray-100 pb-4">
                        <span class="text-sm text-gray-500">{{ __('Amount') }}</span>
                        <span @class([
                            'text-2xl font-semibold',
                            'text-emerald-600' => $transaction->type === \App\Enums\TransactionType::Income,
                            'text-gray-900' => $transaction->type === \App\Enums\TransactionType::Expense,
                        ])>
                            {{ $transaction->type->sign() > 0 ? '+' : '−' }}{{ number_format($transaction->amount, 2) }}
                            <span class="text-sm text-gray-400">{{ $transaction->currency }}</span>
                        </span>
                    </div>

                    <dl class="grid grid-cols-2 gap-4 text-sm">
                        <dt class="text-gray-500">{{ __('Type') }}</dt>
                        <dd>{{ $transaction->type->label() }}</dd>

                        <dt class="text-gray-500">{{ __('Date') }}</dt>
                        <dd>{{ $transaction->occurred_at->format('Y-m-d H:i') }}</dd>

                        <dt class="text-gray-500">{{ __('Category') }}</dt>
                        <dd>
                            @if ($transaction->isSplit())
                                <span class="inline-flex items-center gap-1 rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
                                    <span>{{ __('Split') }}</span>
                                    <span class="text-gray-400">({{ $transaction->splits->count() }})</span>
                                </span>
                            @else
                                {{ $transaction->category ?? '—' }}
                            @endif
                        </dd>

                        <dt class="text-gray-500">{{ __('Note') }}</dt>
                        <dd>{{ $transaction->note ?? '—' }}</dd>

                        @if ($transaction->base_amount !== null && $transaction->currency !== $transaction->base_currency)
                            <dt class="text-gray-500">{{ __('In base currency') }}</dt>
                            <dd>
                                {{ number_format($transaction->base_amount, 2) }} {{ $transaction->base_currency }}
                                <span class="block text-xs text-gray-400">
                                    {{ __('Rate applied') }}: 1 {{ $transaction->currency }} =
                                    {{ rtrim(rtrim(number_format((float) $transaction->applied_rate, 4, '.', ''), '0'), '.') }}
                                    {{ $transaction->base_currency }}
                                </span>
                            </dd>
                        @endif
                    </dl>

                    @if ($transaction->isSplit())
                        <div class="border-t border-gray-100 pt-4">
                            <h3 class="text-sm font-semibold text-gray-800 mb-3">{{ __('Split Details') }}</h3>
                            <div class="overflow-hidden border border-gray-200 rounded-lg">
                                <table class="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead class="bg-gray-50">
                                        <tr class="text-xs text-gray-500 uppercase tracking-wider">
                                            <th class="px-4 py-2 text-left font-medium">{{ __('Category') }}</th>
                                            <th class="px-4 py-2 text-left font-medium">{{ __('Note') }}</th>
                                            <th class="px-4 py-2 text-right font-medium">{{ __('Amount') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 bg-white">
                                        @foreach ($transaction->splits as $split)
                                            <tr>
                                                <td class="px-4 py-2 font-medium text-gray-900">{{ $split->category }}</td>
                                                <td class="px-4 py-2 text-gray-500">{{ $split->note ?? '—' }}</td>
                                                <td class="px-4 py-2 text-right font-medium text-gray-900 whitespace-nowrap">
                                                    {{ number_format($split->amount, 2) }} {{ $transaction->currency }}
                                                    @if ($transaction->base_amount !== null && $transaction->currency !== $transaction->base_currency)
                                                        <span class="block text-xs font-normal text-gray-400">
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

                    <div class="flex items-center gap-4 border-t border-gray-100 pt-4">
                        <a href="{{ route('transactions.edit', $transaction) }}">
                            <x-primary-button>{{ __('Edit') }}</x-primary-button>
                        </a>
                        <a href="{{ route('transactions.index') }}" class="text-sm text-gray-600 hover:text-gray-900">
                            {{ __('Back to list') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>