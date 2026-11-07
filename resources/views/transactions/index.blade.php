<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Transactions') }}
            </h2>
            <div class="flex items-center gap-3" x-data>
                @php
                    $activeFiltersCount = collect([
                        'type' => request('type'),
                        'category' => request('category'),
                        'date' => $day ?? null,
                        'from' => $from ?? null,
                        'to' => $to ?? null,
                    ])->filter(fn ($value): bool => $value !== null && $value !== '')->count();
                @endphp
                <button type="button" @click="$dispatch('open-filter-modal')"
                    class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-1.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                    {{ __('Filters') }}
                    @if ($activeFiltersCount > 0)
                        <span class="ml-2 rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-bold text-indigo-700">
                            {{ $activeFiltersCount }}
                        </span>
                    @endif
                </button>
                <a href="{{ route('transactions.create') }}">
                    <x-primary-button>{{ __('Add transaction') }}</x-primary-button>
                </a>
            </div>
        </div>
    </x-slot>

    @php
        $activeFilters = collect([
            'type' => request('type'),
            'category' => request('category'),
            'date' => $day ?? null,
            'from' => $from ?? null,
            'to' => $to ?? null,
        ])->filter(fn ($value): bool => $value !== null && $value !== '');

        $panelOpen = $activeFilters->isNotEmpty()
            || request()->filled('panel')
            || request()->filled('month');
    @endphp

    <div class="py-12" x-data="{
        showFilterModal: {{ (request()->filled('panel') || request()->filled('month')) ? 'true' : 'false' }}
    }" @open-filter-modal.window="showFilterModal = true">
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

            @if ($activeFilters->isNotEmpty() || ($day ?? null) || ($from ?? null) || ($to ?? null))
                <div class="flex flex-wrap items-center justify-between gap-3 bg-white p-4 shadow-sm sm:rounded-lg border border-gray-100">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 mr-1">{{ __('Active filters:') }}</span>
                        @if (request('type'))
                            <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700 border border-indigo-200">
                                {{ __('Type') }}: {{ ucfirst(request('type')) }}
                            </span>
                        @endif
                        @if (request('category'))
                            <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700 border border-indigo-200">
                                {{ __('Category') }}: {{ request('category') }}
                            </span>
                        @endif
                        @if ($day ?? null)
                            <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700 border border-indigo-200">
                                {{ __('Date') }}: {{ $day }}
                            </span>
                        @elseif (($from ?? null) || ($to ?? null))
                            <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700 border border-indigo-200">
                                {{ __('Dates') }}: {{ $from ?? '...' }} &rarr; {{ $to ?? '...' }}
                            </span>
                        @endif
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="button" @click="showFilterModal = true" class="text-xs text-indigo-600 hover:text-indigo-900 font-medium">
                            {{ __('Edit filters') }}
                        </button>
                        <span class="text-gray-300">|</span>
                        <a href="{{ route('transactions.index', ['group' => $grouped ? 1 : 0]) }}" class="text-xs text-rose-600 hover:text-rose-900 font-medium">
                            {{ __('Clear all') }}
                        </a>
                    </div>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                @if ($grouped)
                    @if ($groups->isEmpty())
                        <p class="p-6 text-gray-500">{{ __('No transactions yet.') }}</p>
                    @else
                        <div class="divide-y divide-gray-100">
                            @foreach ($groups as $group)
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
                                            <span class="flex items-center gap-2">
                                                <svg class="h-4 w-4 text-gray-400 transition group-open/day:rotate-90"
                                                    viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd"
                                                        d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z"
                                                        clip-rule="evenodd" />
                                                </svg>
                                                <span class="text-sm font-medium text-gray-900">{{ $group['label'] }}</span>
                                                <span class="text-xs text-gray-400">({{ $group['count'] }})</span>
                                            </span>
                                            <span class="text-sm font-medium space-x-3">
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
                                    <td class="px-6 py-4 text-sm text-gray-700">
                                        @if ($transaction->isSplit())
                                            <span class="inline-flex items-center gap-1 rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
                                                <span>{{ __('Split') }}</span>
                                                <span class="text-gray-400">({{ $transaction->splits->count() }})</span>
                                            </span>
                                        @else
                                            {{ $transaction->category ?? '—' }}
                                        @endif
                                    </td>
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

        <!-- Filter Modal Popup -->
        <div x-cloak x-show="showFilterModal" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="filter-modal-title" role="dialog" aria-modal="true" id="filter-modal" data-open="{{ $panelOpen ? 'true' : 'false' }}">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div x-show="showFilterModal"
                    x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="ease-in duration-200"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
                    @click="showFilterModal = false"
                    aria-hidden="true"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div x-show="showFilterModal"
                    x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave="ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-xl sm:w-full">
                    <form id="transaction-filters" method="GET" action="{{ route('transactions.index') }}">
                        <input type="hidden" name="panel" value="1">

                        <div class="bg-white px-6 pt-5 pb-4">
                            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                                <div>
                                    <h3 class="text-lg leading-6 font-semibold text-gray-900" id="filter-modal-title">
                                        {{ __('Filter transactions') }}
                                    </h3>
                                </div>
                                <button type="button" @click="showFilterModal = false" class="text-gray-400 hover:text-gray-500">
                                    <span class="sr-only">{{ __('Close') }}</span>
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <div class="mt-4 space-y-4">
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div>
                                        <x-input-label for="filter-type" :value="__('Filter by type')" />
                                        <select id="filter-type" name="type"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
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
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
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
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                            <option value="1" @selected($grouped)>{{ __('Group by day') }}</option>
                                            <option value="0" @selected(! $grouped)>{{ __('Flat list') }}</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="border-t border-gray-100 pt-4">
                                    <p class="mb-2 text-sm font-medium text-gray-700" id="date-filter-label">{{ __('Dates') }}</p>
                                    @include('transactions.partials.date-picker')
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50 px-6 py-3 flex items-center justify-between">
                            <a id="filter-reset" href="{{ route('transactions.index', ['group' => $grouped ? 1 : 0]) }}"
                                class="text-sm font-medium text-gray-600 hover:text-gray-900 {{ $activeFilters->isEmpty() ? 'invisible' : '' }}">
                                {{ __('Reset') }}
                            </a>
                            <div class="flex items-center gap-3">
                                <button type="button" @click="showFilterModal = false" class="inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    {{ __('Cancel') }}
                                </button>
                                <button type="submit" class="inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-gray-800 text-sm font-medium text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    {{ __('Apply') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            const filterForm = document.getElementById('transaction-filters');
            if (filterForm) {
                filterForm.addEventListener('change', (event) => {
                    if (event.target.closest('[data-calendar-month-select]')) {
                        return;
                    }
                });
            }
        </script>
    @endpush
</x-app-layout>