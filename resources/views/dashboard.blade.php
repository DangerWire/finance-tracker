<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2.5">
                    <h1 class="font-semibold text-xl text-zinc-100 leading-tight tracking-tight">
                        {{ __('Dashboard') }}
                    </h1>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-mono-num font-medium bg-zinc-800 text-zinc-300 border border-zinc-700/60">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                        <span>{{ $baseCurrency }}</span>
                    </span>
                </div>
                <p class="text-xs text-zinc-400 mt-1">
                    {{ __('Overview for :month', ['month' => $monthLabel]) }}
                </p>
            </div>

            <div class="flex items-center gap-2.5">
                <a href="{{ route('transaction-imports.create') }}"
                    class="inline-flex items-center gap-2 px-3.5 py-2 bg-zinc-900 border border-zinc-800 rounded-lg text-xs font-medium text-zinc-300 hover:text-white hover:bg-zinc-800/80 transition duration-150">
                    <svg class="w-3.5 h-3.5 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    <span>{{ __('Import transactions') }}</span>
                </a>
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

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            {{-- Status & Action Banners --}}
            @if (session('status'))
                <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm rounded-xl px-4 py-3 flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-400 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            @if ($unconvertedCount > 0)
                <div class="bg-amber-500/10 border border-amber-500/20 text-amber-300 text-xs rounded-xl px-4 py-3 flex items-center gap-2">
                    <svg class="w-4 h-4 text-amber-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <span>{{ trans_choice(':count transaction could not be converted to :currency and is excluded from these totals. Run finance:backfill-base-amounts once a rate is available.', $unconvertedCount, ['count' => $unconvertedCount, 'currency' => $baseCurrency]) }}</span>
                </div>
            @endif

            @if ($pendingDebtActionsCount > 0)
                <div class="bg-amber-500/10 border border-amber-500/20 text-amber-300 text-xs rounded-xl px-4 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse shrink-0"></span>
                        <span>
                            <strong class="font-semibold">{{ __('Action required') }}:</strong>
                            {{ __(':count loan proposal(s) or payment confirmation(s) require your attention', ['count' => $pendingDebtActionsCount]) }}
                        </span>
                    </div>
                    <a href="{{ route('debts.index') }}" class="inline-flex items-center gap-1 text-xs font-medium text-amber-300 hover:text-amber-200 underline underline-offset-4 shrink-0">
                        <span>{{ __('View pending items') }}</span>
                        <span>&rarr;</span>
                    </a>
                </div>
            @endif

            {{-- Row 1: Essential KPI Cards --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {{-- Total Balance --}}
                <div class="sure-card rounded-xl p-5 sure-hover-lift">
                    <div class="flex items-center justify-between text-xs text-zinc-400 mb-2">
                        <span class="font-medium uppercase tracking-wider text-[11px]">{{ __('Total balance') }}</span>
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-mono-num font-medium bg-zinc-800 text-zinc-300 border border-zinc-700">
                            ALL
                        </span>
                    </div>
                    <p @class([
                        'text-2xl font-semibold font-mono-num tracking-tight',
                        'text-emerald-400' => $totalBalance >= 0,
                        'text-rose-400' => $totalBalance < 0,
                    ])>
                        {{ $totalBalance >= 0 ? '+' : '−' }}{{ number_format(abs($totalBalance), 2) }}
                        <span class="text-xs font-normal text-zinc-500 ml-1">{{ $baseCurrency }}</span>
                    </p>
                    <p class="mt-2 text-xs text-zinc-500">
                        {{ __('All-time net ledger balance') }}
                    </p>
                </div>

                {{-- Monthly Income --}}
                <div class="sure-card rounded-xl p-5 sure-hover-lift">
                    <div class="flex items-center justify-between text-xs text-zinc-400 mb-2">
                        <span class="font-medium uppercase tracking-wider text-[11px]">{{ __('Monthly income') }}</span>
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-mono-num font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                            +IN
                        </span>
                    </div>
                    <p class="text-2xl font-semibold font-mono-num text-emerald-400 tracking-tight">
                        +{{ number_format($monthlyIncome, 2) }}
                        <span class="text-xs font-normal text-zinc-500 ml-1">{{ $baseCurrency }}</span>
                    </p>
                    <p class="mt-2 text-xs text-zinc-500">
                        {{ $monthLabel }}
                    </p>
                </div>

                {{-- Monthly Expenses --}}
                <div class="sure-card rounded-xl p-5 sure-hover-lift">
                    <div class="flex items-center justify-between text-xs text-zinc-400 mb-2">
                        <span class="font-medium uppercase tracking-wider text-[11px]">{{ __('Monthly expenses') }}</span>
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-mono-num font-medium bg-rose-500/10 text-rose-400 border border-rose-500/20">
                            −OUT
                        </span>
                    </div>
                    <p class="text-2xl font-semibold font-mono-num text-rose-400 tracking-tight">
                        −{{ number_format($monthlyExpenses, 2) }}
                        <span class="text-xs font-normal text-zinc-500 ml-1">{{ $baseCurrency }}</span>
                    </p>
                    @if ($spendingChangePercent !== null)
                        <div class="mt-2 flex items-center gap-1.5 text-xs">
                            <span @class([
                                'inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-mono-num font-medium',
                                'text-rose-400 bg-rose-500/10 border border-rose-500/20' => $spendingChangePercent > 0,
                                'text-emerald-400 bg-emerald-500/10 border border-emerald-500/20' => $spendingChangePercent <= 0,
                            ])>
                                {{ $spendingChangePercent > 0 ? '↑' : '↓' }}
                                {{ number_format(abs($spendingChangePercent), 1) }}%
                            </span>
                            <span class="text-zinc-500">{{ __('vs :month', ['month' => $previousMonthLabel]) }}</span>
                        </div>
                    @else
                        <p class="mt-2 text-xs text-zinc-500">
                            {{ __('Active in current monthly period') }}
                        </p>
                    @endif
                </div>

                {{-- Monthly Net --}}
                <div class="sure-card rounded-xl p-5 sure-hover-lift">
                    <div class="flex items-center justify-between text-xs text-zinc-400 mb-2">
                        <span class="font-medium uppercase tracking-wider text-[11px]">{{ __('Monthly net') }}</span>
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-mono-num font-medium bg-zinc-800 text-zinc-400 border border-zinc-700">
                            NET
                        </span>
                    </div>
                    <p @class([
                        'text-2xl font-semibold font-mono-num tracking-tight',
                        'text-emerald-400' => $monthlyNet >= 0,
                        'text-rose-400' => $monthlyNet < 0,
                    ])>
                        {{ $monthlyNet >= 0 ? '+' : '−' }}{{ number_format(abs($monthlyNet), 2) }}
                        <span class="text-xs font-normal text-zinc-500 ml-1">{{ $baseCurrency }}</span>
                    </p>
                    <p class="mt-2 text-xs text-zinc-500">
                        @if ($savingsRate !== null)
                            {{ __('Savings rate: :rate%', ['rate' => number_format($savingsRate, 1)]) }}
                        @else
                            {{ __('No income recorded this month') }}
                        @endif
                    </p>
                </div>
            </div>

            {{-- Row 2: Main Content Grid --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {{-- Left Column (2/3): Recent Transactions + Top Spending Categories --}}
                <div class="lg:col-span-2 space-y-6">
                    {{-- Recent Transactions --}}
                    <div class="sure-card rounded-xl overflow-hidden">
                        <div class="border-b border-white/[0.06] px-6 py-4 flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <h2 class="font-semibold text-sm text-zinc-100">{{ __('Recent transactions') }}</h2>
                                @if ($recentTransactions->isNotEmpty())
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-mono-num font-medium bg-zinc-800 text-zinc-400 border border-zinc-700/60">
                                        {{ $recentTransactions->count() }}
                                    </span>
                                @endif
                            </div>
                            <a href="{{ route('transactions.index') }}" class="text-xs font-medium text-emerald-400 hover:text-emerald-300 transition-colors">
                                {{ __('View all') }} &rarr;
                            </a>
                        </div>

                        @if ($recentTransactions->isEmpty())
                            <div class="px-6 py-12 text-center">
                                <div class="w-10 h-10 rounded-xl bg-zinc-900 border border-zinc-800 flex items-center justify-center mx-auto mb-3 text-zinc-500">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                    </svg>
                                </div>
                                <p class="text-sm font-medium text-zinc-200">{{ __('No transactions yet.') }}</p>
                                <p class="text-xs text-zinc-500 mt-1 max-w-sm mx-auto">
                                    {{ __('Start tracking your finances by adding your first income or expense record.') }}
                                </p>
                                <div class="mt-4">
                                    <a href="{{ route('transactions.create') }}">
                                        <x-primary-button>{{ __('Add transaction') }}</x-primary-button>
                                    </a>
                                </div>
                            </div>
                        @else
                            <div class="divide-y divide-white/[0.04]">
                                @foreach ($recentTransactions as $transaction)
                                    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 px-6 py-3.5 hover:bg-white/[0.02] transition-colors">
                                        <div class="flex min-w-0 flex-wrap items-center gap-3">
                                            <span @class([
                                                'inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium border shrink-0',
                                                'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' => $transaction->type === \App\Enums\TransactionType::Income,
                                                'bg-rose-500/10 text-rose-400 border-rose-500/20' => $transaction->type === \App\Enums\TransactionType::Expense,
                                            ])>{{ __($transaction->type->label()) }}</span>

                                            @if ($transaction->isSplit())
                                                <span class="inline-flex items-center gap-1 rounded-md bg-zinc-800/80 px-2 py-0.5 text-xs font-medium text-zinc-300 border border-zinc-700">
                                                    <span>{{ __('Split') }}</span>
                                                    <span class="text-zinc-500 font-mono-num">({{ $transaction->splits->count() }})</span>
                                                </span>
                                            @else
                                                <span class="text-sm font-medium text-zinc-200">{{ $transaction->category ?? '—' }}</span>
                                            @endif

                                            <span class="text-xs text-zinc-400 truncate">
                                                <span class="font-mono-num text-zinc-500">{{ $transaction->occurred_at->format('M d · H:i') }}</span>
                                                @if ($transaction->note)
                                                    <span class="mx-1 text-zinc-600">·</span>
                                                    <span>{{ \Illuminate\Support\Str::limit($transaction->note, 40) }}</span>
                                                @endif
                                            </span>
                                        </div>

                                        <div class="flex items-center gap-4 shrink-0">
                                            <div class="whitespace-nowrap text-right text-sm font-mono-num font-medium">
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
                                            </div>

                                            <div class="whitespace-nowrap text-xs">
                                                <a href="{{ route('transactions.show', $transaction) }}"
                                                    class="text-zinc-400 hover:text-zinc-200 transition-colors mr-2.5">{{ __('View') }}</a>
                                                <a href="{{ route('transactions.edit', $transaction) }}"
                                                    class="text-emerald-400 hover:text-emerald-300 transition-colors font-medium">{{ __('Edit') }}</a>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- Top Spending Categories --}}
                    <div class="sure-card rounded-xl overflow-hidden">
                        <div class="border-b border-white/[0.06] px-6 py-4 flex items-center justify-between">
                            <h2 class="font-semibold text-sm text-zinc-100">{{ __('Top spending categories') }}</h2>
                            <a href="{{ route('insights.index') }}" class="text-xs font-medium text-emerald-400 hover:text-emerald-300 transition-colors">
                                {{ __('View insights') }} &rarr;
                            </a>
                        </div>

                        @if ($topCategories === [])
                            <div class="px-6 py-8 text-center">
                                <p class="text-xs text-zinc-500">{{ __('No expenses recorded for this month yet.') }}</p>
                            </div>
                        @else
                            <div class="p-6 space-y-4">
                                @foreach ($topCategories as $category)
                                    <div class="space-y-1.5">
                                        <div class="flex items-center justify-between gap-3 text-xs">
                                            <div class="flex items-center gap-2 min-w-0">
                                                <span class="font-medium text-zinc-200 truncate">{{ $category['category'] }}</span>
                                                @if ($category['is_unusual'])
                                                    <span class="rounded-full bg-amber-500/10 border border-amber-500/20 px-2 py-0.5 text-[10px] font-medium text-amber-400 shrink-0">
                                                        {{ __('unusual') }}
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="flex items-center gap-3 shrink-0 font-mono-num">
                                                <span class="text-zinc-200 font-medium">
                                                    {{ number_format($category['total'], 2) }}
                                                    <span class="text-[11px] text-zinc-500">{{ $category['currency'] }}</span>
                                                </span>
                                                <span class="text-zinc-500 w-12 text-right">{{ number_format($category['share_of_spend_percent'], 1) }}%</span>
                                            </div>
                                        </div>
                                        <div class="w-full bg-zinc-800/80 rounded-full h-1.5 overflow-hidden">
                                            <div class="bg-emerald-400 h-full rounded-full transition-all duration-300"
                                                style="width: {{ min(100, $category['share_of_spend_percent']) }}%"></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Right Column (1/3): Budgets, Debts, Upcoming Recurring --}}
                <div class="lg:col-span-1 space-y-6">
                    {{-- Monthly Budgets --}}
                    <div class="sure-card rounded-xl overflow-hidden">
                        <div class="border-b border-white/[0.06] px-5 py-4 flex items-center justify-between">
                            <h2 class="font-semibold text-sm text-zinc-100">{{ __('Monthly budgets') }}</h2>
                            <a href="{{ route('budgets.index') }}" class="text-xs font-medium text-emerald-400 hover:text-emerald-300 transition-colors">
                                {{ __('Manage') }} &rarr;
                            </a>
                        </div>

                        @if ($topBudgets === [])
                            <div class="p-6 text-center space-y-3">
                                <p class="text-xs font-medium text-zinc-300">{{ __('No budgets set for this month.') }}</p>
                                <p class="text-[11px] text-zinc-500">
                                    {{ __('Track spending targets by category directly from your dashboard.') }}
                                </p>
                                <a href="{{ route('budgets.index') }}"
                                    class="inline-flex items-center px-3 py-1.5 rounded-lg bg-zinc-900 border border-zinc-700/80 text-xs font-medium text-zinc-200 hover:text-white hover:bg-zinc-800 transition-colors">
                                    {{ __('Set a monthly budget') }}
                                </a>
                            </div>
                        @else
                            <div class="p-5 space-y-4">
                                {{-- Overall progress --}}
                                <div class="p-3.5 rounded-lg bg-white/[0.02] border border-white/[0.05] space-y-2">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="text-zinc-400">{{ __('Overall progress') }}</span>
                                        <span class="font-mono-num font-medium text-zinc-200">{{ number_format($budgetOverview['percentage_used'], 1) }}%</span>
                                    </div>
                                    <div class="w-full bg-zinc-800 rounded-full h-1.5 overflow-hidden">
                                        <div @class([
                                            'h-full rounded-full transition-all duration-300',
                                            'bg-emerald-400' => $budgetOverview['status'] === 'safe',
                                            'bg-amber-400' => $budgetOverview['status'] === 'warning',
                                            'bg-rose-400' => $budgetOverview['status'] === 'exceeded',
                                        ]) style="width: {{ min(100, $budgetOverview['percentage_used']) }}%"></div>
                                    </div>
                                    <div class="flex items-center justify-between text-[11px] text-zinc-500 font-mono-num">
                                        <span>{{ number_format($budgetOverview['total_spent'], 0) }} / {{ number_format($budgetOverview['total_budget'], 0) }} {{ $budgetOverview['currency'] }}</span>
                                        <span>
                                            @if ($budgetOverview['total_remaining'] >= 0)
                                                {{ number_format($budgetOverview['total_remaining'], 0) }} {{ __('remaining') }}
                                            @else
                                                {{ number_format(abs($budgetOverview['total_remaining']), 0) }} {{ __('over limit') }}
                                            @endif
                                        </span>
                                    </div>
                                </div>

                                {{-- Individual Top Budgets --}}
                                <div class="space-y-3">
                                    @foreach ($topBudgets as $budget)
                                        <div class="space-y-1">
                                            <div class="flex items-center justify-between gap-2 text-xs">
                                                <div class="flex items-center gap-1.5 min-w-0">
                                                    <span class="font-medium text-zinc-200 truncate">{{ $budget['category'] }}</span>
                                                    <span @class([
                                                        'px-1.5 py-0.2 rounded text-[10px] font-medium border shrink-0',
                                                        'bg-rose-500/10 text-rose-400 border-rose-500/20' => $budget['status'] === 'exceeded',
                                                        'bg-amber-500/10 text-amber-400 border-amber-500/20' => $budget['status'] === 'warning',
                                                        'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' => $budget['status'] === 'safe',
                                                    ])>
                                                        @if ($budget['status'] === 'exceeded')
                                                            {{ __('Over budget') }}
                                                        @elseif ($budget['status'] === 'warning')
                                                            {{ __('Near limit') }}
                                                        @else
                                                            {{ __('Safe') }}
                                                        @endif
                                                    </span>
                                                </div>
                                                <span class="font-mono-num text-zinc-400 text-[11px] shrink-0">
                                                    {{ number_format($budget['spent_amount'], 0) }} / {{ number_format($budget['budget_amount'], 0) }}
                                                </span>
                                            </div>
                                            <div class="w-full bg-zinc-800/80 rounded-full h-1 overflow-hidden">
                                                <div @class([
                                                    'h-full rounded-full transition-all duration-300',
                                                    'bg-emerald-400' => $budget['status'] === 'safe',
                                                    'bg-amber-400' => $budget['status'] === 'warning',
                                                    'bg-rose-400' => $budget['status'] === 'exceeded',
                                                ]) style="width: {{ min(100, $budget['percentage_used']) }}%"></div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Peer-to-Peer Debts Snapshot --}}
                    <div class="sure-card rounded-xl overflow-hidden">
                        <div class="border-b border-white/[0.06] px-5 py-4 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <h2 class="font-semibold text-sm text-zinc-100">{{ __('Peer-to-peer debts') }}</h2>
                                @if ($pendingDebtActionsCount > 0)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-medium bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                        {{ __('Pending action') }} ({{ $pendingDebtActionsCount }})
                                    </span>
                                @endif
                            </div>
                            <a href="{{ route('debts.index') }}" class="text-xs font-medium text-emerald-400 hover:text-emerald-300 transition-colors">
                                {{ __('Manage') }} &rarr;
                            </a>
                        </div>

                        <div class="p-5 space-y-3">
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-zinc-400">{{ __('Total lent') }}</span>
                                <span class="font-mono-num font-medium text-emerald-400">
                                    +{{ number_format($totalLent, 2) }} <span class="text-zinc-500">{{ $baseCurrency }}</span>
                                </span>
                            </div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-zinc-400">{{ __('Total borrowed') }}</span>
                                <span class="font-mono-num font-medium text-rose-400">
                                    −{{ number_format($totalBorrowed, 2) }} <span class="text-zinc-500">{{ $baseCurrency }}</span>
                                </span>
                            </div>
                            <div class="pt-3 border-t border-white/[0.06] flex items-center justify-between text-xs">
                                <span class="font-medium text-zinc-200">{{ __('Net position') }}</span>
                                <span @class([
                                    'font-mono-num font-semibold',
                                    'text-emerald-400' => $netDebtPosition >= 0,
                                    'text-rose-400' => $netDebtPosition < 0,
                                ])>
                                    {{ $netDebtPosition >= 0 ? '+' : '−' }}{{ number_format(abs($netDebtPosition), 2) }}
                                    <span class="text-zinc-500 font-normal">{{ $baseCurrency }}</span>
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- Upcoming Recurring Bills --}}
                    <div class="sure-card rounded-xl overflow-hidden">
                        <div class="border-b border-white/[0.06] px-5 py-4 flex items-center justify-between">
                            <h2 class="font-semibold text-sm text-zinc-100">{{ __('Upcoming recurring') }}</h2>
                            <a href="{{ route('recurring.index') }}" class="text-xs font-medium text-emerald-400 hover:text-emerald-300 transition-colors">
                                {{ __('Manage') }} &rarr;
                            </a>
                        </div>

                        @if ($upcomingRecurring->isEmpty())
                            <div class="p-6 text-center space-y-3">
                                <p class="text-xs text-zinc-500">{{ __('No active recurring schedules.') }}</p>
                                <a href="{{ route('recurring.create') }}"
                                    class="inline-flex items-center px-3 py-1.5 rounded-lg bg-zinc-900 border border-zinc-700/80 text-xs font-medium text-zinc-200 hover:text-white hover:bg-zinc-800 transition-colors">
                                    {{ __('Add recurring') }}
                                </a>
                            </div>
                        @else
                            <div class="divide-y divide-white/[0.04]">
                                @foreach ($upcomingRecurring as $item)
                                    <div class="px-5 py-3 flex items-center justify-between gap-3 hover:bg-white/[0.02] transition-colors">
                                        <div class="min-w-0">
                                            <p class="text-xs font-medium text-zinc-200 truncate">
                                                {{ $item->name ?: ($item->category ?: '—') }}
                                            </p>
                                            <p class="text-[11px] text-zinc-500 font-mono-num mt-0.5">
                                                {{ $item->next_occurrence_on->format('M d') }} · {{ $item->frequency->label() }}
                                            </p>
                                        </div>
                                        <span @class([
                                            'text-xs font-mono-num font-medium whitespace-nowrap shrink-0',
                                            'text-emerald-400' => $item->type === \App\Enums\TransactionType::Income,
                                            'text-zinc-200' => $item->type === \App\Enums\TransactionType::Expense,
                                        ])>
                                            {{ $item->type->sign() > 0 ? '+' : '−' }}{{ number_format((float) $item->amount, 2) }}
                                            <span class="text-[10px] text-zinc-500 font-normal">{{ $item->currency }}</span>
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
