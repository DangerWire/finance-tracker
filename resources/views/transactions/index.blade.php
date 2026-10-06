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
                    <p class="mt-1 text-2xl font-semibold text-emerald-600">{{ number_format($income, 2) }}
                        <span class="text-sm font-normal text-gray-400">{{ $baseCurrency }}</span>
                    </p>
                </div>
                <div class="bg-white p-4 shadow-sm sm:rounded-lg">
                    <p class="text-sm text-gray-500">{{ __('Expenses') }}</p>
                    <p class="mt-1 text-2xl font-semibold text-rose-600">{{ number_format($expenses, 2) }}
                        <span class="text-sm font-normal text-gray-400">{{ $baseCurrency }}</span>
                    </p>
                </div>
                <div class="bg-white p-4 shadow-sm sm:rounded-lg">
                    <p class="text-sm text-gray-500">{{ __('Balance') }}</p>
                    <p @class([
                        'mt-1 text-2xl font-semibold',
                        'text-emerald-600' => $balance >= 0,
                        'text-rose-600' => $balance < 0,
                    ])>{{ number_format($balance, 2) }}
                        <span class="text-sm font-normal text-gray-400">{{ $baseCurrency }}</span>
                    </p>
                </div>
            </div>

@if ($unconvertedCount > 0)
                <div class="bg-amber-50 border border-amber-200 text-amber-800 text-sm rounded-lg px-4 py-3">
                    {{ trans_choice(':count transaction could not be converted to :currency and is excluded from these totals. Run finance:backfill-base-amounts once a rate is available.', $unconvertedCount, ['count' => $unconvertedCount, 'currency' => $baseCurrency]) }}
                </div>
            @endif

            @php
    $activeFilters = collect([
        'type' => request('type'),
        'category' => request('category'),
        'date' => $day ?? null,
        'from' => $from ?? null,
        'to' => $to ?? null,
    ])->filter(fn ($value): bool => $value !== null && $value !== '');

    // The panel has to survive navigation that changes nothing about the
    // filters. Paging the calendar only moves `month`, and changing the layout
    // only sets `group`, so without this marker either action would collapse
    // the panel out from under the user mid-interaction.
    //
    // `panel` rides along on every control inside the panel, which is exactly
    // when it should stay open. Reset deliberately omits it, returning to the
    // default collapsed view.
    $panelOpen = $activeFilters->isNotEmpty()
        || request()->filled('panel')
        || request()->filled('month');
@endphp

<details id="filter-panel" @if ($panelOpen) open @endif
    class="bg-white shadow-sm sm:rounded-lg">
    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3">
        <span class="flex items-center gap-2">
            <span class="text-sm font-medium text-gray-800">{{ __('Filters') }}</span>
            @if ($activeFilters->isNotEmpty())
                <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-800">
                    {{ trans_choice(':count filter applied|:count filters applied', $activeFilters->count(), ['count' => $activeFilters->count()]) }}
                </span>
            @endif
        </span>
        <span class="text-xs text-gray-400">{{ __('Show or hide') }}</span>
    </summary>

    <form id="transaction-filters" method="GET" action="{{ route('transactions.index') }}"
        class="flex flex-wrap items-end gap-4 border-t border-gray-100 px-4 py-4">
        {{-- Sent with every change made inside the panel, so the panel stays
             open across a filter change or a date selection. --}}
        <input type="hidden" name="panel" value="1">

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
            <select id="filter-category" name="category"
                class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">{{ __('All') }}</option>
                @foreach ($categories as $category)
                    <option value="{{ $category }}" @selected(request('category') === $category)>
                        {{ $category }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <x-input-label for="filter-group" :value="__('Layout')" />
            <select id="filter-group" name="group"
                class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="1" @selected($grouped)>{{ __('Group by day') }}</option>
                <option value="0" @selected(! $grouped)>{{ __('Flat list') }}</option>
            </select>
        </div>

        {{-- Inside the form on purpose: the calendar writes the selection into
             these inputs and submits the form, so they have to be fields of it. --}}
        <div class="basis-full border-t border-gray-100 pt-4">
            <p class="mb-2 text-sm font-medium text-gray-700" id="date-filter-label">{{ __('Dates') }}</p>
            @include('transactions.partials.date-picker')
        </div>

        <noscript>
            <x-primary-button>{{ __('Apply') }}</x-primary-button>
        </noscript>

        {{-- An anchor rather than a button so clearing filters still works when
             JavaScript is unavailable. The layout choice is kept because it is
             a view preference rather than a filter on the data. --}}
        <a id="filter-reset" href="{{ route('transactions.index', ['group' => $grouped ? 1 : 0]) }}"
            class="text-sm text-gray-600 hover:text-gray-900 {{ $activeFilters->isEmpty() ? 'hidden' : '' }}">
            {{ __('Reset') }}
        </a>
    </form>
</details>

@if ($day ?? null)
    <div class="bg-indigo-50 border border-indigo-200 text-indigo-800 text-sm rounded-lg px-4 py-3">
        {{ __('Showing one day: :date.', ['date' => $day]) }}
    </div>
@elseif (($from ?? null) || ($to ?? null))
    <div class="bg-indigo-50 border border-indigo-200 text-indigo-800 text-sm rounded-lg px-4 py-3">
        {{ __('Showing from :from to :to', ['from' => $from ?? __('any date'), 'to' => $to ?? __('today')]) }}
    </div>
@endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                @if ($grouped)
                    @if ($groups->isEmpty())
                        <p class="p-6 text-gray-500">{{ __('No transactions yet.') }}</p>
                    @else
                        <div class="divide-y divide-gray-100">
                            @foreach ($groups as $group)
                                {{-- A day with a single transaction is shown open: there is
                                     nothing to hide behind a disclosure. --}}
                                @if ($group['count'] === 1)
                                    <div class="px-6 py-4">
                                        <div class="flex items-baseline justify-between gap-4">
                                            <p class="text-sm font-medium text-gray-900">{{ $group['label'] }}</p>
                                            <p class="text-sm text-gray-500">
                                                @include('transactions.partials.row', ['transaction' => $group['transactions'][0]])
                                            </p>
                                        </div>
                                    </div>
                                @else
                                    <details class="group/day">
                                        <summary class="flex cursor-pointer list-none items-baseline justify-between gap-4 px-6 py-4 hover:bg-gray-50">
                                            <span class="flex items-baseline gap-3">
                                                <span class="text-sm font-medium text-gray-900">{{ $group['label'] }}</span>
                                                <span class="text-xs text-gray-500">
                                                    {{ trans_choice(':count transaction|:count transactions', $group['count'], ['count' => $group['count']]) }}
                                                </span>
                                            </span>
                                            <span class="flex items-baseline gap-4 text-sm">
                                                @if ($group['income'] > 0)
                                                    <span class="text-emerald-600">
                                                        +{{ number_format($group['income'], 2) }}
                                                    </span>
                                                @endif
                                                @if ($group['expenses'] > 0)
                                                    <span class="text-gray-700">
                                                        −{{ number_format($group['expenses'], 2) }}
                                                    </span>
                                                @endif
                                            </span>
                                        </summary>

                                        <div class="border-t border-gray-100">
                                            @foreach ($group['transactions'] as $transaction)
                                                @include('transactions.partials.row', ['transaction' => $transaction])
                                            @endforeach
                                        </div>
                                    </details>
                                @endif
                            @endforeach
                        </div>

                        <div class="border-t border-gray-100 px-6 py-4">
                            {{ $days->links() }}
                        </div>
                    @endif
                @elseif ($transactions->isEmpty())
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
                                        @if ($transaction->base_amount !== null && $transaction->currency !== $transaction->base_currency)
                                            <br>
                                            <span class="text-xs font-normal text-gray-400">
                                                {{ $transaction->type->sign() > 0 ? '+' : '−' }}{{ number_format($transaction->base_amount, 2) }}
                                                {{ $transaction->base_currency }}
                                            </span>
                                        @endif
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

    @push('scripts')
        <script>
            // Filters apply as soon as they change, so there is no Apply button.
            // Without JavaScript the noscript button inside the form covers it.
            const filterForm = document.getElementById('transaction-filters');

            // A single day overrides the range, so both can stay filled in:
            // the server gives the day precedence and the notice under the
            // panel states which one is in effect.
            filterForm.addEventListener('change', () => filterForm.submit());
        </script>
    @endpush
</x-app-layout>