<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Transactions') }}
            </h2>
            <a href="{{ route('transactions.create') }}">
                <x-primary-button>{{ __('Add transaction') }}</x-primary-button>
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm rounded-lg px-4 py-3">
                    {{ session('status') }}
                </div>
            @endif

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="bg-white p-4 shadow-sm sm:rounded-lg">
                    <p class="text-sm text-gray-500">{{ __('Income') }}</p>
                    <p class="mt-1 text-2xl font-semibold text-emerald-600">{{ number_format($income, 2) }}</p>
                </div>
                <div class="bg-white p-4 shadow-sm sm:rounded-lg">
                    <p class="text-sm text-gray-500">{{ __('Expenses') }}</p>
                    <p class="mt-1 text-2xl font-semibold text-rose-600">{{ number_format($expenses, 2) }}</p>
                </div>
                <div class="bg-white p-4 shadow-sm sm:rounded-lg">
                    <p class="text-sm text-gray-500">{{ __('Balance') }}</p>
                    <p @class([
                        'mt-1 text-2xl font-semibold',
                        'text-emerald-600' => $balance >= 0,
                        'text-rose-600' => $balance < 0,
                    ])>{{ number_format($balance, 2) }}</p>
                </div>
            </div>

            <form method="GET" action="{{ route('transactions.index') }}"
                class="bg-white p-4 shadow-sm sm:rounded-lg flex flex-wrap items-end gap-4">
                <div>
                    <x-input-label for="filter-type" :value="__('Filter by type')" />
                    <select id="filter-type" name="type"
                        class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">{{ __('All') }}</option>
                        @foreach ($types as $type)
                            <option value="{{ $type->value }}" @selected(request('type') === $type->value)>
                                {{ $type->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-input-label for="filter-category" :value="__('Category')" />
                    <input id="filter-category" name="category" type="text"
                        class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        value="{{ request('category') }}">
                </div>

                <x-primary-button>{{ __('Apply') }}</x-primary-button>

                @if (request()->hasAny(['type', 'category']))
                    <a href="{{ route('transactions.index') }}" class="text-sm text-gray-600 hover:text-gray-900">
                        {{ __('Reset') }}
                    </a>
                @endif
            </form>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                @if ($transactions->isEmpty())
                    <p class="p-6 text-gray-500">{{ __('No transactions yet.') }}</p>
                @else
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Date') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Category') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Type') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Note') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Amount') }}</th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($transactions as $transaction)
                                <tr class="hover:bg-gray-50">
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-700">
                                        {{ $transaction->occurred_at->format('Y-m-d') }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-700">{{ $transaction->category ?? '—' }}</td>
                                    <td class="px-6 py-4 text-sm">
                                        <span @class([
                                            'inline-flex rounded-full px-2 py-1 text-xs font-medium',
                                            'bg-emerald-100 text-emerald-800' => $transaction->type === \App\Enums\TransactionType::Income,
                                            'bg-rose-100 text-rose-800' => $transaction->type === \App\Enums\TransactionType::Expense,
                                        ])>{{ $transaction->type->label() }}</span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">{{ \Illuminate\Support\Str::limit($transaction->note, 40) }}</td>
                                    <td @class([
                                        'whitespace-nowrap px-6 py-4 text-right text-sm font-medium',
                                        'text-emerald-600' => $transaction->type === \App\Enums\TransactionType::Income,
                                        'text-gray-900' => $transaction->type === \App\Enums\TransactionType::Expense,
                                    ])>
                                        {{ $transaction->type->sign() > 0 ? '+' : '−' }}{{ number_format($transaction->amount, 2) }}
                                        <span class="text-xs text-gray-400">{{ $transaction->currency }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                        <a href="{{ route('transactions.show', $transaction) }}"
                                            class="text-gray-600 hover:text-gray-900">{{ __('View') }}</a>
                                        <a href="{{ route('transactions.edit', $transaction) }}"
                                            class="ml-3 text-indigo-600 hover:text-indigo-900">{{ __('Edit') }}</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="border-t border-gray-100 px-6 py-4">
                        {{ $transactions->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>