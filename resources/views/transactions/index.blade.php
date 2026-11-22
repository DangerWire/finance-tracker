<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-zinc-100 leading-tight flex items-center gap-2.5">
                    <span>{{ __('Transactions') }}</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-mono-num font-medium bg-zinc-800 text-zinc-400 border border-zinc-700/60">
                        {{ $grouped ? $days->total() . ' ' . __('days') : $transactions->total() }}
                    </span>
                </h2>
            </div>
            <div class="flex items-center gap-2.5" x-data>
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
                    class="inline-flex items-center gap-2 px-3.5 py-2 bg-zinc-900 border border-zinc-800 rounded-lg text-xs font-medium text-zinc-300 hover:text-white hover:bg-zinc-800/80 transition duration-150">
                    <svg class="w-3.5 h-3.5 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                    <span>{{ __('Filters') }}</span>
                    @if ($activeFiltersCount > 0)
                        <span class="rounded-full bg-emerald-500/15 border border-emerald-500/30 px-1.5 py-0.2 text-[10px] font-mono-num font-semibold text-emerald-400">
                            {{ $activeFiltersCount }}
                        </span>
                    @endif
                </button>

                <div class="relative" x-data="{ exportOpen: false }" @click.outside="exportOpen = false">
                    <button type="button" @click="exportOpen = !exportOpen"
                        class="inline-flex items-center gap-2 px-3.5 py-2 bg-zinc-900 border border-zinc-800 rounded-lg text-xs font-medium text-zinc-300 hover:text-white hover:bg-zinc-800/80 transition duration-150">
                        <svg class="w-3.5 h-3.5 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        <span>{{ __('Export') }}</span>
                    </button>
                    <div x-show="exportOpen" x-transition style="display: none;"
                        class="absolute right-0 mt-2 w-48 rounded-xl bg-[#111218] border border-white/[0.08] shadow-xl py-1.5 z-50">
                        <a href="{{ route('transactions.export.csv', request()->only(['type', 'category', 'date', 'from', 'to'])) }}"
                            class="block px-4 py-2 text-xs text-zinc-200 hover:bg-white/[0.05] hover:text-white transition-colors">
                            {{ __('Download CSV') }}
                        </a>
                        <a href="{{ route('transactions.export.statement', request()->only(['type', 'category', 'date', 'from', 'to'])) }}"
                            target="_blank"
                            class="block px-4 py-2 text-xs text-zinc-200 hover:bg-white/[0.05] hover:text-white transition-colors">
                            {{ __('Print / PDF Statement') }}
                        </a>
                    </div>
                </div>

                <a href="{{ route('transactions.create') }}">
                    <x-primary-button class="gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        <span>{{ __('Add transaction') }}</span>
                    </x-primary-button>
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

    <div class="py-8" x-data="{
        showFilterModal: {{ (request()->filled('panel') || request()->filled('month')) ? 'true' : 'false' }}
    }" @open-filter-modal.window="showFilterModal = true">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm rounded-xl px-4 py-3 flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-400 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            <!-- Sure.am High-Density KPI Cards -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="sure-card rounded-xl p-5 sure-hover-lift">
                    <div class="flex items-center justify-between text-xs text-zinc-400 mb-2">
                        <span class="font-medium uppercase tracking-wider text-[11px]">{{ __('Income') }}</span>
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-mono-num font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                            +IN
                        </span>
                    </div>
                    <p class="text-2xl font-semibold font-mono-num text-emerald-400 tracking-tight">
                        +{{ number_format($income, 2) }}
                        <span class="text-xs font-normal text-zinc-500 ml-1">{{ $baseCurrency }}</span>
                    </p>
                </div>

                <div class="sure-card rounded-xl p-5 sure-hover-lift">
                    <div class="flex items-center justify-between text-xs text-zinc-400 mb-2">
                        <span class="font-medium uppercase tracking-wider text-[11px]">{{ __('Expenses') }}</span>
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-mono-num font-medium bg-rose-500/10 text-rose-400 border border-rose-500/20">
                            −OUT
                        </span>
                    </div>
                    <p class="text-2xl font-semibold font-mono-num text-rose-400 tracking-tight">
                        −{{ number_format($expenses, 2) }}
                        <span class="text-xs font-normal text-zinc-500 ml-1">{{ $baseCurrency }}</span>
                    </p>
                </div>

                <div class="sure-card rounded-xl p-5 sure-hover-lift">
                    <div class="flex items-center justify-between text-xs text-zinc-400 mb-2">
                        <span class="font-medium uppercase tracking-wider text-[11px]">{{ __('Balance') }}</span>
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-mono-num font-medium bg-zinc-800 text-zinc-400 border border-zinc-700">
                            NET
                        </span>
                    </div>
                    <p @class([
                        'text-2xl font-semibold font-mono-num tracking-tight',
                        'text-emerald-400' => $balance >= 0,
                        'text-rose-400' => $balance < 0,
                    ])>
                        {{ $balance >= 0 ? '+' : '−' }}{{ number_format(abs($balance), 2) }}
                        <span class="text-xs font-normal text-zinc-500 ml-1">{{ $baseCurrency }}</span>
                    </p>
                </div>
            </div>

            @if ($unconvertedCount > 0)
                <div class="bg-amber-500/10 border border-amber-500/20 text-amber-300 text-xs rounded-xl px-4 py-3 flex items-center gap-2">
                    <svg class="w-4 h-4 text-amber-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <span>{{ trans_choice(':count transaction could not be converted to :currency and is excluded from these totals. Run finance:backfill-base-amounts once a rate is available.', $unconvertedCount, ['count' => $unconvertedCount, 'currency' => $baseCurrency]) }}</span>
                </div>
            @endif

            @if ($activeFilters->isNotEmpty() || ($day ?? null) || ($from ?? null) || ($to ?? null))
                <div class="flex flex-wrap items-center justify-between gap-3 bg-[#111218] px-4 py-3 rounded-xl border border-white/[0.06]">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-medium uppercase tracking-wider text-zinc-500 mr-1">{{ __('Active filters:') }}</span>
                        @if (request('type'))
                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-zinc-800/80 px-2.5 py-1 text-xs font-medium text-zinc-200 border border-zinc-700">
                                <span class="text-zinc-500">{{ __('Type') }}:</span>
                                <span>{{ ucfirst(request('type')) }}</span>
                            </span>
                        @endif
                        @if (request('category'))
                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-zinc-800/80 px-2.5 py-1 text-xs font-medium text-zinc-200 border border-zinc-700">
                                <span class="text-zinc-500">{{ __('Category') }}:</span>
                                <span>{{ request('category') }}</span>
                            </span>
                        @endif
                        @if ($day ?? null)
                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-zinc-800/80 px-2.5 py-1 text-xs font-medium text-zinc-200 border border-zinc-700 font-mono-num">
                                <span class="text-zinc-500 font-sans">{{ __('Date') }}:</span>
                                <span>{{ $day }}</span>
                            </span>
                        @elseif (($from ?? null) || ($to ?? null))
                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-zinc-800/80 px-2.5 py-1 text-xs font-medium text-zinc-200 border border-zinc-700 font-mono-num">
                                <span class="text-zinc-500 font-sans">{{ __('Dates') }}:</span>
                                <span>{{ $from ?? '...' }} &rarr; {{ $to ?? '...' }}</span>
                            </span>
                        @endif
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="button" @click="showFilterModal = true" class="text-xs text-zinc-300 hover:text-white font-medium transition-colors">
                            {{ __('Edit filters') }}
                        </button>
                        <span class="text-zinc-700">|</span>
                        <a href="{{ route('transactions.index', ['group' => $grouped ? 1 : 0]) }}" class="text-xs text-rose-400 hover:text-rose-300 font-medium transition-colors">
                            {{ __('Clear all') }}
                        </a>
                    </div>
                </div>
            @endif

            <!-- Sure.am Modern Transaction Ledger -->
            <div class="sure-card rounded-xl overflow-hidden">
                @if ($grouped)
                    @if ($groups->isEmpty())
                        <div class="p-12 text-center">
                            <p class="text-sm text-zinc-400">{{ __('No transactions yet.') }}</p>
                        </div>
                    @else
                        <div class="divide-y divide-white/[0.04]">
                            @foreach ($groups as $group)
                                @if ($group['count'] === 1)
                                    <div class="px-6 py-4 hover:bg-white/[0.02] transition-colors">
                                        <div class="flex items-baseline justify-between gap-4">
                                            <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 font-mono-num">{{ $group['label'] }}</p>
                                            <div class="w-full">
                                                @include('transactions.partials.row', ['transaction' => $group['transactions'][0]])
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <details class="group/day" open>
                                        <summary class="flex cursor-pointer list-none items-baseline justify-between gap-4 px-6 py-4 hover:bg-white/[0.02] transition-colors bg-[#0c0d12]/50 border-b border-white/[0.04]">
                                            <span class="flex items-center gap-2">
                                                <svg class="h-4 w-4 text-zinc-500 transition-transform group-open/day:rotate-90"
                                                    viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd"
                                                        d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z"
                                                        clip-rule="evenodd" />
                                                </svg>
                                                <span class="text-xs font-semibold uppercase tracking-wider text-zinc-300 font-mono-num">{{ $group['label'] }}</span>
                                                <span class="text-xs text-zinc-500">({{ $group['count'] }})</span>
                                            </span>
                                            <span class="text-xs font-mono-num space-x-3">
                                                @if ($group['income'] > 0)
                                                    <span class="text-emerald-400 font-medium">
                                                        +{{ number_format($group['income'], 2) }}
                                                    </span>
                                                @endif
                                                @if ($group['expenses'] > 0)
                                                    <span class="text-zinc-400 font-medium">
                                                        −{{ number_format($group['expenses'], 2) }}
                                                    </span>
                                                @endif
                                            </span>
                                        </summary>

                                        <div class="divide-y divide-white/[0.03]">
                                            @foreach ($group['transactions'] as $transaction)
                                                @include('transactions.partials.row', ['transaction' => $transaction])
                                            @endforeach
                                        </div>
                                    </details>
                                @endif
                            @endforeach
                        </div>

                        <div class="border-t border-white/[0.06] px-6 py-4 bg-[#0d0e13]">
                            {{ $days->links() }}
                        </div>
                    @endif
                @elseif ($transactions->isEmpty())
                    <div class="p-12 text-center">
                        <p class="text-sm text-zinc-400">{{ __('No transactions yet.') }}</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-white/[0.04]">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3.5 text-left text-xs font-medium uppercase tracking-wider text-zinc-400">{{ __('Date') }}</th>
                                    <th class="px-6 py-3.5 text-left text-xs font-medium uppercase tracking-wider text-zinc-400">{{ __('Category') }}</th>
                                    <th class="px-6 py-3.5 text-left text-xs font-medium uppercase tracking-wider text-zinc-400">{{ __('Type') }}</th>
                                    <th class="px-6 py-3.5 text-left text-xs font-medium uppercase tracking-wider text-zinc-400">{{ __('Note') }}</th>
                                    <th class="px-6 py-3.5 text-right text-xs font-medium uppercase tracking-wider text-zinc-400">{{ __('Amount') }}</th>
                                    <th class="px-6 py-3.5 text-right text-xs font-medium uppercase tracking-wider text-zinc-400"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/[0.03]">
                                @foreach ($transactions as $transaction)
                                    <tr class="hover:bg-white/[0.02] transition-colors">
                                        <td class="whitespace-nowrap px-6 py-4 text-xs font-mono-num text-zinc-400">
                                            {{ $transaction->occurred_at->format('Y-m-d') }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-zinc-200">
                                            @if ($transaction->isSplit())
                                                <span class="inline-flex items-center gap-1 rounded-md bg-zinc-800/80 px-2 py-0.5 text-xs font-medium text-zinc-300 border border-zinc-700">
                                                    <span>{{ __('Split') }}</span>
                                                    <span class="text-zinc-500 font-mono-num">({{ $transaction->splits->count() }})</span>
                                                </span>
                                            @else
                                                <span class="font-medium">{{ $transaction->category ?? '—' }}</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-xs">
                                            <span @class([
                                                 'inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium border',
                                                 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' => $transaction->type === \App\Enums\TransactionType::Income,
                                                 'bg-rose-500/10 text-rose-400 border-rose-500/20' => $transaction->type === \App\Enums\TransactionType::Expense,
                                             ])>{{ $transaction->type->label() }}</span>
                                        </td>
                                        <td class="px-6 py-4 text-xs text-zinc-400">{{ \Illuminate\Support\Str::limit($transaction->note, 45) }}</td>
                                        <td @class([
                                             'whitespace-nowrap px-6 py-4 text-right text-sm font-mono-num font-medium',
                                             'text-emerald-400' => $transaction->type === \App\Enums\TransactionType::Income,
                                             'text-zinc-200' => $transaction->type === \App\Enums\TransactionType::Expense,
                                         ])>
                                            <span>{{ $transaction->type->sign() > 0 ? '+' : '−' }}{{ number_format($transaction->amount, 2) }}</span>
                                            <span class="text-xs font-normal text-zinc-500 ml-0.5">{{ $transaction->currency }}</span>
                                            @if ($transaction->base_amount !== null && $transaction->currency !== $transaction->base_currency)
                                                <span class="block text-[11px] font-normal text-zinc-500">
                                                    {{ $transaction->type->sign() > 0 ? '+' : '−' }}{{ number_format($transaction->base_amount, 2) }}
                                                    {{ $transaction->base_currency }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-right text-xs">
                                            <a href="{{ route('transactions.show', $transaction) }}"
                                                class="text-zinc-400 hover:text-zinc-200 transition-colors mr-3">{{ __('View') }}</a>
                                            <a href="{{ route('transactions.edit', $transaction) }}"
                                                class="text-emerald-400 hover:text-emerald-300 transition-colors font-medium">{{ __('Edit') }}</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="border-t border-white/[0.06] px-6 py-4 bg-[#0d0e13]">
                        {{ $transactions->links() }}
                    </div>
                @endif
            </div>
        </div>

        <!-- Filter Modal Popup (Preserving exact form ID & date picker inputs) -->
        <div x-cloak x-show="showFilterModal" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="filter-modal-title" role="dialog" aria-modal="true" id="filter-modal" data-open="{{ $panelOpen ? 'true' : 'false' }}">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div x-show="showFilterModal"
                    x-transition:enter="ease-out duration-200"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="ease-in duration-150"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="fixed inset-0 bg-black/80 backdrop-blur-sm transition-opacity"
                    @click="showFilterModal = false"
                    aria-hidden="true"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div x-show="showFilterModal"
                    x-transition:enter="ease-out duration-200"
                    x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave="ease-in duration-150"
                    x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    class="inline-block align-bottom bg-zinc-900 border border-zinc-800 rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-xl sm:w-full">
                    <form id="transaction-filters" method="GET" action="{{ route('transactions.index') }}">
                        <input type="hidden" name="panel" value="1">

                        <div class="px-6 pt-6 pb-5">
                            <div class="flex items-center justify-between pb-4 border-b border-white/[0.06]">
                                <div>
                                    <h3 class="text-base font-semibold text-zinc-100" id="filter-modal-title">
                                        {{ __('Filter transactions') }}
                                    </h3>
                                </div>
                                <button type="button" @click="showFilterModal = false" class="text-zinc-500 hover:text-zinc-300 transition-colors">
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
                                            class="mt-1 block w-full rounded-lg bg-zinc-900 border-zinc-800 text-zinc-200 shadow-sm focus:border-zinc-500 focus:ring-zinc-500 text-xs py-2">
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
                                            class="mt-1 block w-full rounded-lg bg-zinc-900 border-zinc-800 text-zinc-200 shadow-sm focus:border-zinc-500 focus:ring-zinc-500 text-xs py-2">
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
                                            class="mt-1 block w-full rounded-lg bg-zinc-900 border-zinc-800 text-zinc-200 shadow-sm focus:border-zinc-500 focus:ring-zinc-500 text-xs py-2">
                                            <option value="1" @selected($grouped)>{{ __('Group by day') }}</option>
                                            <option value="0" @selected(! $grouped)>{{ __('Flat list') }}</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="border-t border-white/[0.06] pt-4">
                                    <p class="mb-2 text-xs font-medium text-zinc-400" id="date-filter-label">{{ __('Dates') }}</p>
                                    @include('transactions.partials.date-picker')
                                </div>
                            </div>
                        </div>

                        <div class="bg-zinc-950/80 px-6 py-4 flex items-center justify-between border-t border-white/[0.06]">
                            <a id="filter-reset" href="{{ route('transactions.index', ['group' => $grouped ? 1 : 0]) }}"
                                class="text-xs font-medium text-zinc-400 hover:text-zinc-200 transition-colors {{ $activeFilters->isEmpty() ? 'invisible' : '' }}">
                                {{ __('Reset') }}
                            </a>
                            <div class="flex items-center gap-2.5">
                                <button type="button" @click="showFilterModal = false" class="px-4 py-2 bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 text-zinc-300 hover:text-white rounded-lg text-xs font-medium transition-colors">
                                    {{ __('Cancel') }}
                                </button>
                                <button type="submit" class="px-4 py-2 bg-zinc-100 hover:bg-white text-zinc-950 rounded-lg text-xs font-medium transition-colors shadow-sm">
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
