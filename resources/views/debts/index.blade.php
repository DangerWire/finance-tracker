<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-zinc-100 leading-tight flex items-center gap-2.5">
                    <span>{{ __('Peer-to-peer debts') }}</span>
                    @php
                        $userCurrency = $baseCurrency ?? strtoupper((string) (auth()->user()->currency ?? config('finance.base_currency', 'CNY')));
                    @endphp
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-mono-num font-medium bg-zinc-800 text-zinc-400 border border-zinc-700/60">
                        {{ $userCurrency }}
                    </span>
                </h2>
                <p class="text-xs text-zinc-400 mt-1">
                    {{ __('Track money lent and borrowed with friends, approve agreements, and synchronize settlements to your ledger.') }}
                </p>
            </div>
            <div class="flex items-center gap-2.5" x-data>
                <a href="{{ Route::has('friends.index') ? route('friends.index') : url('/friends') }}">
                    <x-secondary-button class="gap-1.5 text-xs">
                        <svg class="w-3.5 h-3.5 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                        <span>{{ __('Friends') }}</span>
                    </x-secondary-button>
                </a>
                <x-primary-button @click="$dispatch('open-modal', 'record-loan-modal')" class="gap-1.5 text-xs">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span>{{ __('Record loan') }}</span>
                </x-primary-button>
            </div>
        </div>
    </x-slot>

    @php
        $curr = $baseCurrency ?? strtoupper((string) (auth()->user()->currency ?? config('finance.base_currency', 'CNY')));
        $lentTotal = $totalLent ?? 0.0;
        $borrowedTotal = $totalBorrowed ?? 0.0;
        $net = $netPosition ?? ($lentTotal - $borrowedTotal);

        // Group debts safely whether controller provided specific lists or full collection
        $pendingList = $pendingDebts ?? (isset($debts) ? $debts->where('status', 'pending_acceptance') : collect());
        $activeList = $activeDebts ?? (isset($debts) ? $debts->where('status', 'active') : collect());
        $awaitingList = $awaitingConfirmationDebts ?? (isset($debts) ? $debts->where('status', 'payment_submitted') : collect());
        $settledList = $settledDebts ?? (isset($debts) ? $debts->where('status', 'settled') : collect());

        // Count action required items
        $actionsNeededCount = 0;
        foreach ($pendingList as $p) {
            if ($p->creator_id !== auth()->id()) {
                $actionsNeededCount++;
            }
        }
        foreach ($awaitingList as $a) {
            if ($a->lender_id === auth()->id()) {
                $actionsNeededCount++;
            }
        }
    @endphp

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
            {{-- Flash Notifications --}}
            @if (session('status'))
                <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm rounded-xl px-4 py-3 flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-400 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            @if (session('error'))
                <div class="bg-rose-500/10 border border-rose-500/20 text-rose-300 text-sm rounded-xl px-4 py-3 flex items-center gap-2">
                    <svg class="w-4 h-4 text-rose-400 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            {{-- Action Required Cyberpunk Alert Banner --}}
            @if ($actionsNeededCount > 0)
                <div class="bg-amber-500/10 border border-amber-500/30 rounded-2xl p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-3.5">
                        <div class="w-10 h-10 rounded-xl bg-amber-500/20 border border-amber-500/40 flex items-center justify-center text-amber-400 shrink-0">
                            <svg class="w-5 h-5 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-semibold text-amber-200">
                                    {{ __('Action required') }}
                                </h3>
                                <span class="px-2 py-0.2 rounded-full text-[10px] font-mono-num font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40">
                                    {{ $actionsNeededCount }}
                                </span>
                            </div>
                            <p class="text-xs text-amber-300/80 mt-0.5">
                                {{ __(':count loan proposal(s) or payment confirmation(s) require your attention', ['count' => $actionsNeededCount]) }}
                            </p>
                        </div>
                    </div>
                    <div class="shrink-0">
                        <a href="#actionable-section" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-500 hover:bg-amber-400 text-zinc-950 font-semibold rounded-lg text-xs transition duration-150">
                            <span>{{ __('View pending items') }}</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </a>
                    </div>
                </div>
            @endif

            {{-- Sure.am KPI Summary Cards --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                {{-- Total Lent --}}
                <div class="sure-card rounded-xl p-5 sure-hover-lift">
                    <div class="flex items-center justify-between text-xs text-zinc-400 mb-2">
                        <span class="font-medium uppercase tracking-wider text-[11px]">{{ __('Total lent') }}</span>
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-mono-num font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                            +LENT
                        </span>
                    </div>
                    <p class="text-2xl font-semibold font-mono-num text-emerald-400 tracking-tight">
                        +{{ number_format($lentTotal, 2) }}
                        <span class="text-xs font-normal text-zinc-500 ml-1">{{ $curr }}</span>
                    </p>
                    <p class="text-[11px] text-zinc-500 mt-2">
                        {{ __('Money owed to you') }}
                    </p>
                </div>

                {{-- Total Borrowed --}}
                <div class="sure-card rounded-xl p-5 sure-hover-lift">
                    <div class="flex items-center justify-between text-xs text-zinc-400 mb-2">
                        <span class="font-medium uppercase tracking-wider text-[11px]">{{ __('Total borrowed') }}</span>
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-mono-num font-medium bg-rose-500/10 text-rose-400 border border-rose-500/20">
                            −OWED
                        </span>
                    </div>
                    <p class="text-2xl font-semibold font-mono-num text-rose-400 tracking-tight">
                        −{{ number_format($borrowedTotal, 2) }}
                        <span class="text-xs font-normal text-zinc-500 ml-1">{{ $curr }}</span>
                    </p>
                    <p class="text-[11px] text-zinc-500 mt-2">
                        {{ __('Money you owe') }}
                    </p>
                </div>

                {{-- Net Position --}}
                <div class="sure-card rounded-xl p-5 sure-hover-lift">
                    <div class="flex items-center justify-between text-xs text-zinc-400 mb-2">
                        <span class="font-medium uppercase tracking-wider text-[11px]">{{ __('Net position') }}</span>
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-mono-num font-medium bg-zinc-800 text-zinc-400 border border-zinc-700">
                            NET
                        </span>
                    </div>
                    <p @class([
                        'text-2xl font-semibold font-mono-num tracking-tight',
                        'text-emerald-400' => $net >= 0,
                        'text-rose-400' => $net < 0,
                    ])>
                        {{ $net >= 0 ? '+' : '−' }}{{ number_format(abs($net), 2) }}
                        <span class="text-xs font-normal text-zinc-500 ml-1">{{ $curr }}</span>
                    </p>
                    <p class="text-[11px] text-zinc-500 mt-2">
                        {{ $net >= 0 ? __('You are in the green') : __('You have net outstanding debt') }}
                    </p>
                </div>
            </div>

            <div id="actionable-section"></div>

            {{-- Section 1: Pending Approval Loans --}}
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <h3 class="text-sm font-semibold text-zinc-100 uppercase tracking-wider text-[11px]">
                            {{ __('Pending approval') }}
                        </h3>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-mono-num font-medium bg-zinc-800 text-zinc-400 border border-zinc-700">
                            {{ $pendingList->count() }}
                        </span>
                    </div>
                </div>

                @if ($pendingList->isEmpty())
                    <div class="sure-card-subtle rounded-xl p-6 text-center border border-white/[0.04]">
                        <p class="text-xs text-zinc-500">{{ __('No pending loan proposals.') }}</p>
                    </div>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach ($pendingList as $debt)
                            @php
                                $isLender = $debt->lender_id === auth()->id();
                                $isCreator = $debt->creator_id === auth()->id();
                                $counterparty = $isLender ? $debt->borrower : $debt->lender;
                                $counterpartyName = $counterparty->name ?? $counterparty->email ?? __('Friend');
                            @endphp
                            <div class="sure-card rounded-xl p-5 sure-hover-lift flex flex-col justify-between">
                                <div>
                                    <div class="flex items-start justify-between gap-3 mb-3">
                                        <div>
                                            <span class="text-xs font-medium text-zinc-400">
                                                {{ $isLender ? __('You lent to') : __('You borrowed from') }}
                                            </span>
                                            <h4 class="text-base font-semibold text-zinc-100 mt-0.5">
                                                {{ $counterpartyName }}
                                            </h4>
                                        </div>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-mono-num font-medium bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                            {{ __('Pending') }}
                                        </span>
                                    </div>

                                    <div class="flex items-baseline gap-2 mb-3">
                                        <span @class([
                                            'text-2xl font-bold font-mono-num tracking-tight',
                                            'text-emerald-400' => $isLender,
                                            'text-rose-400' => !$isLender,
                                        ])>
                                            {{ $isLender ? '+' : '−' }}{{ number_format($debt->amount, 2) }}
                                        </span>
                                        <span class="text-xs font-mono-num text-zinc-500">{{ $debt->currency }}</span>
                                    </div>

                                    @if ($debt->note)
                                        <p class="text-xs text-zinc-400 bg-zinc-900/60 rounded-lg p-2.5 border border-white/[0.04] mb-3">
                                            <span class="text-zinc-500 font-medium">{{ __('Note') }}:</span> {{ $debt->note }}
                                        </p>
                                    @endif

                                    @if ($debt->due_date)
                                        <p class="text-[11px] text-zinc-500 mb-3 flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                            <span>{{ __('Due date') }}: {{ \Carbon\Carbon::parse($debt->due_date)->format('M d, Y') }}</span>
                                        </p>
                                    @endif
                                </div>

                                <div class="pt-3 border-t border-white/[0.06] flex items-center justify-between gap-2">
                                    @if ($isCreator)
                                        <span class="text-xs text-zinc-500 flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                                            {{ __('Waiting for :name to accept', ['name' => $counterpartyName]) }}
                                        </span>
                                    @else
                                        <div class="flex items-center gap-2 w-full sm:w-auto">
                                            <form method="POST" action="{{ Route::has('debts.accept') ? route('debts.accept', $debt->id) : url('/debts/' . $debt->id . '/accept') }}">
                                                @csrf
                                                <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 bg-emerald-500 hover:bg-emerald-400 text-zinc-950 font-semibold rounded-lg text-xs transition duration-150">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                                    </svg>
                                                    <span>{{ __('Accept') }}</span>
                                                </button>
                                            </form>

                                            <form method="POST" action="{{ Route::has('debts.decline') ? route('debts.decline', $debt->id) : url('/debts/' . $debt->id . '/decline') }}">
                                                @csrf
                                                <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 bg-zinc-900 border border-zinc-700/80 hover:bg-zinc-800 text-zinc-400 hover:text-zinc-200 rounded-lg text-xs transition duration-150">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                    </svg>
                                                    <span>{{ __('Decline') }}</span>
                                                </button>
                                            </form>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Section 2: Active Loans --}}
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <h3 class="text-sm font-semibold text-zinc-100 uppercase tracking-wider text-[11px]">
                            {{ __('Active loans') }}
                        </h3>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-mono-num font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                            {{ $activeList->count() }}
                        </span>
                    </div>
                </div>

                @if ($activeList->isEmpty())
                    <div class="sure-card-subtle rounded-xl p-6 text-center border border-white/[0.04]">
                        <p class="text-xs text-zinc-500">{{ __('No active loans.') }}</p>
                    </div>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach ($activeList as $debt)
                            @php
                                $isLender = $debt->lender_id === auth()->id();
                                $counterparty = $isLender ? $debt->borrower : $debt->lender;
                                $counterpartyName = $counterparty->name ?? $counterparty->email ?? __('Friend');
                            @endphp
                            <div class="sure-card rounded-xl p-5 sure-hover-lift flex flex-col justify-between">
                                <div>
                                    <div class="flex items-start justify-between gap-3 mb-3">
                                        <div>
                                            <span class="text-xs font-medium text-zinc-400">
                                                {{ $isLender ? __('You lent to') : __('You borrowed from') }}
                                            </span>
                                            <h4 class="text-base font-semibold text-zinc-100 mt-0.5">
                                                {{ $counterpartyName }}
                                            </h4>
                                        </div>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-mono-num font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                            {{ __('Active') }}
                                        </span>
                                    </div>

                                    <div class="flex items-baseline gap-2 mb-3">
                                        <span @class([
                                            'text-2xl font-bold font-mono-num tracking-tight',
                                            'text-emerald-400' => $isLender,
                                            'text-rose-400' => !$isLender,
                                        ])>
                                            {{ $isLender ? '+' : '−' }}{{ number_format($debt->amount, 2) }}
                                        </span>
                                        <span class="text-xs font-mono-num text-zinc-500">{{ $debt->currency }}</span>
                                    </div>

                                    @if ($debt->note)
                                        <p class="text-xs text-zinc-400 bg-zinc-900/60 rounded-lg p-2.5 border border-white/[0.04] mb-3">
                                            <span class="text-zinc-500 font-medium">{{ __('Note') }}:</span> {{ $debt->note }}
                                        </p>
                                    @endif

                                    @if ($debt->due_date)
                                        <p class="text-[11px] text-zinc-500 mb-3 flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                            <span>{{ __('Due date') }}: {{ \Carbon\Carbon::parse($debt->due_date)->format('M d, Y') }}</span>
                                        </p>
                                    @endif
                                </div>

                                <div class="pt-3 border-t border-white/[0.06] flex items-center justify-between gap-2">
                                    @if (!$isLender)
                                        {{-- Borrower has 'Mark as Paid' button --}}
                                        <form method="POST" action="{{ Route::has('debts.pay') ? route('debts.pay', $debt->id) : url('/debts/' . $debt->id . '/pay') }}" onsubmit="return confirm('{{ __('Confirm you have repaid this loan?') }}')">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-500 hover:bg-emerald-400 text-zinc-950 font-semibold rounded-lg text-xs transition duration-150">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                                </svg>
                                                <span>{{ __('Mark as paid') }}</span>
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-xs text-zinc-500 flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5 text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            {{ __('Waiting for payment from borrower') }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Section 3: Awaiting Confirmation Loans --}}
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <h3 class="text-sm font-semibold text-zinc-100 uppercase tracking-wider text-[11px]">
                            {{ __('Awaiting confirmation') }}
                        </h3>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-mono-num font-medium bg-amber-500/10 text-amber-400 border border-amber-500/20">
                            {{ $awaitingList->count() }}
                        </span>
                    </div>
                </div>

                @if ($awaitingList->isEmpty())
                    <div class="sure-card-subtle rounded-xl p-6 text-center border border-white/[0.04]">
                        <p class="text-xs text-zinc-500">{{ __('No loans awaiting payment confirmation.') }}</p>
                    </div>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach ($awaitingList as $debt)
                            @php
                                $isLender = $debt->lender_id === auth()->id();
                                $counterparty = $isLender ? $debt->borrower : $debt->lender;
                                $counterpartyName = $counterparty->name ?? $counterparty->email ?? __('Friend');
                            @endphp
                            <div class="sure-card rounded-xl p-5 sure-hover-lift flex flex-col justify-between border-amber-500/30">
                                <div>
                                    <div class="flex items-start justify-between gap-3 mb-3">
                                        <div>
                                            <span class="text-xs font-medium text-zinc-400">
                                                {{ $isLender ? __('You lent to') : __('You borrowed from') }}
                                            </span>
                                            <h4 class="text-base font-semibold text-zinc-100 mt-0.5">
                                                {{ $counterpartyName }}
                                            </h4>
                                        </div>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-mono-num font-semibold bg-amber-500/15 text-amber-300 border border-amber-500/30">
                                            {{ __('Payment submitted') }}
                                        </span>
                                    </div>

                                    <div class="flex items-baseline gap-2 mb-3">
                                        <span @class([
                                            'text-2xl font-bold font-mono-num tracking-tight',
                                            'text-emerald-400' => $isLender,
                                            'text-rose-400' => !$isLender,
                                        ])>
                                            {{ $isLender ? '+' : '−' }}{{ number_format($debt->amount, 2) }}
                                        </span>
                                        <span class="text-xs font-mono-num text-zinc-500">{{ $debt->currency }}</span>
                                    </div>

                                    @if ($debt->note)
                                        <p class="text-xs text-zinc-400 bg-zinc-900/60 rounded-lg p-2.5 border border-white/[0.04] mb-3">
                                            <span class="text-zinc-500 font-medium">{{ __('Note') }}:</span> {{ $debt->note }}
                                        </p>
                                    @endif

                                    @if ($debt->payment_submitted_at)
                                        <p class="text-[11px] text-zinc-500 mb-3 flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            <span>{{ __('Paid') }} {{ \Carbon\Carbon::parse($debt->payment_submitted_at)->diffForHumans() }}</span>
                                        </p>
                                    @endif
                                </div>

                                <div class="pt-3 border-t border-white/[0.06] flex items-center justify-between gap-2">
                                    @if ($isLender)
                                        <div class="flex items-center gap-2 w-full sm:w-auto">
                                            {{-- Confirm Payment Received --}}
                                            <form method="POST" action="{{ Route::has('debts.confirm') ? route('debts.confirm', $debt->id) : url('/debts/' . $debt->id . '/confirm') }}" onsubmit="return confirm('{{ __('Confirm payment received? This will post an income transaction to your ledger.') }}')">
                                                @csrf
                                                <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-500 hover:bg-emerald-400 text-zinc-950 font-semibold rounded-lg text-xs transition duration-150">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                                    </svg>
                                                    <span>{{ __('Confirm payment received') }}</span>
                                                </button>
                                            </form>

                                            {{-- Dispute --}}
                                            <form method="POST" action="{{ Route::has('debts.dispute') ? route('debts.dispute', $debt->id) : url('/debts/' . $debt->id . '/dispute') }}" onsubmit="return confirm('{{ __('Dispute this payment? The loan will return to active status.') }}')">
                                                @csrf
                                                <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/30 rounded-lg text-xs transition duration-150">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                                    </svg>
                                                    <span>{{ __('Dispute') }}</span>
                                                </button>
                                            </form>
                                        </div>
                                    @else
                                        <span class="text-xs text-zinc-500 flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                                            {{ __('Waiting for lender to confirm receipt') }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Section 4: Settled History --}}
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <h3 class="text-sm font-semibold text-zinc-100 uppercase tracking-wider text-[11px]">
                            {{ __('Settled history') }}
                        </h3>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-mono-num font-medium bg-zinc-800 text-zinc-400 border border-zinc-700">
                            {{ $settledList->count() }}
                        </span>
                    </div>
                </div>

                @if ($settledList->isEmpty())
                    <div class="sure-card-subtle rounded-xl p-6 text-center border border-white/[0.04]">
                        <p class="text-xs text-zinc-500">{{ __('No settled loans yet.') }}</p>
                    </div>
                @else
                    <div class="sure-card rounded-xl overflow-hidden divide-y divide-white/[0.04]">
                        @foreach ($settledList as $debt)
                            @php
                                $isLender = $debt->lender_id === auth()->id();
                                $counterparty = $isLender ? $debt->borrower : $debt->lender;
                                $counterpartyName = $counterparty->name ?? $counterparty->email ?? __('Friend');
                                $settledTime = $debt->settled_at ? \Carbon\Carbon::parse($debt->settled_at) : ($debt->updated_at ?? null);
                            @endphp
                            <div class="p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:bg-white/[0.01] transition duration-150">
                                <div class="flex items-center gap-3.5">
                                    <div class="w-9 h-9 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-center shrink-0">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <h4 class="text-sm font-medium text-zinc-200">
                                                {{ $isLender ? __('You lent to') : __('You borrowed from') }} {{ $counterpartyName }}
                                            </h4>
                                            <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-mono-num font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                                {{ __('Settled') }}
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-3 text-xs text-zinc-500 mt-0.5">
                                            @if ($debt->note)
                                                <span>{{ $debt->note }}</span>
                                                <span>&bull;</span>
                                            @endif
                                            @if ($settledTime)
                                                <span>{{ $settledTime->format('M d, Y') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-4 self-end sm:self-center">
                                    <span class="text-sm font-semibold font-mono-num text-zinc-300">
                                        {{ number_format($debt->amount, 2) }} {{ $debt->currency }}
                                    </span>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-mono-num text-emerald-400 bg-emerald-500/5 border border-emerald-500/10">
                                        <svg class="w-3 h-3 text-emerald-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                        {{ __('Synced to ledger') }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Record Loan Modal --}}
    <x-modal name="record-loan-modal" :show="$errors->has('friend_id') || $errors->has('amount')" maxWidth="lg">
        <form method="POST" action="{{ Route::has('debts.store') ? route('debts.store') : url('/debts') }}" class="p-6 space-y-6">
            @csrf

            <div class="flex items-center justify-between pb-4 border-b border-white/[0.06]">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-zinc-100">
                            {{ __('Record loan proposal') }}
                        </h3>
                        <p class="text-xs text-zinc-400">
                            {{ __('The recipient must approve this loan before it becomes active.') }}
                        </p>
                    </div>
                </div>
                <button type="button" x-on:click="$dispatch('close')" class="text-zinc-500 hover:text-zinc-300">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            @php
                $friendsCollection = isset($friends) ? $friends : collect();
            @endphp

            @if ($friendsCollection->isEmpty())
                <div class="bg-amber-500/10 border border-amber-500/20 rounded-xl p-4 text-center">
                    <p class="text-xs text-amber-300 mb-3">
                        {{ __('You need to add friends first before recording a loan.') }}
                    </p>
                    <a href="{{ Route::has('friends.index') ? route('friends.index') : url('/friends') }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-amber-500 hover:bg-amber-400 text-zinc-950 font-semibold rounded-lg text-xs transition">
                        <span>{{ __('Go to Friends') }}</span>
                    </a>
                </div>
            @else
                {{-- Friend Select --}}
                <div>
                    <label for="friend_id" class="block text-xs font-medium uppercase tracking-wider text-zinc-400 mb-1.5">
                        {{ __('Select friend') }}
                    </label>
                    <select
                        name="friend_id"
                        id="friend_id"
                        required
                        class="w-full px-3.5 py-2.5 bg-zinc-900 border border-zinc-700/80 rounded-xl text-sm text-zinc-100 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                    >
                        <option value="" disabled selected>{{ __('Choose a friend...') }}</option>
                        @foreach ($friendsCollection as $f)
                            @php
                                $fUser = $f instanceof \App\Models\User ? $f : ($f->user_id === auth()->id() ? ($f->friend ?? $f->user) : ($f->user ?? $f->friend));
                                $fId = $fUser->id ?? ($f->id ?? null);
                                $fName = $fUser->name ?? $fUser->email ?? __('Friend');
                                $fEmail = $fUser->email ?? '';
                            @endphp
                            @if ($fId && $fId !== auth()->id())
                                <option value="{{ $fId }}" {{ old('friend_id') == $fId ? 'selected' : '' }}>
                                    {{ $fName }} ({{ $fEmail }})
                                </option>
                            @endif
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('friend_id')" class="mt-1" />
                </div>

                {{-- Loan Type (Segmented control) --}}
                <div x-data="{ loanType: '{{ old('type', 'lent') }}' }">
                    <label class="block text-xs font-medium uppercase tracking-wider text-zinc-400 mb-1.5">
                        {{ __('Loan type') }}
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        <label
                            class="flex items-center justify-center gap-2 p-3 rounded-xl border cursor-pointer transition duration-150"
                            :class="loanType === 'lent' ? 'bg-emerald-500/10 border-emerald-500/40 text-emerald-400' : 'bg-zinc-900/60 border-zinc-800 text-zinc-400 hover:border-zinc-700'"
                        >
                            <input type="radio" name="type" value="lent" x-model="loanType" class="sr-only">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            <span class="text-xs font-semibold">{{ __('I lent money') }}</span>
                        </label>

                        <label
                            class="flex items-center justify-center gap-2 p-3 rounded-xl border cursor-pointer transition duration-150"
                            :class="loanType === 'borrowed' ? 'bg-rose-500/10 border-rose-500/40 text-rose-400' : 'bg-zinc-900/60 border-zinc-800 text-zinc-400 hover:border-zinc-700'"
                        >
                            <input type="radio" name="type" value="borrowed" x-model="loanType" class="sr-only">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>
                            </svg>
                            <span class="text-xs font-semibold">{{ __('I borrowed money') }}</span>
                        </label>
                    </div>
                    <x-input-error :messages="$errors->get('type')" class="mt-1" />
                </div>

                {{-- Amount & Currency --}}
                <div class="grid grid-cols-3 gap-3">
                    <div class="col-span-2">
                        <label for="amount" class="block text-xs font-medium uppercase tracking-wider text-zinc-400 mb-1.5">
                            {{ __('Amount') }}
                        </label>
                        <input
                            type="number"
                            name="amount"
                            id="amount"
                            step="0.01"
                            min="0.01"
                            value="{{ old('amount') }}"
                            required
                            placeholder="0.00"
                            class="w-full px-3.5 py-2.5 bg-zinc-900 border border-zinc-700/80 rounded-xl text-sm font-mono-num text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                        />
                        <x-input-error :messages="$errors->get('amount')" class="mt-1" />
                    </div>

                    <div>
                        <label class="block text-xs font-medium uppercase tracking-wider text-zinc-400 mb-1.5">
                            {{ __('Currency') }}
                        </label>
                        <input
                            type="text"
                            value="{{ $curr }}"
                            disabled
                            class="w-full px-3.5 py-2.5 bg-zinc-800/80 border border-zinc-700/60 rounded-xl text-sm font-mono-num text-zinc-400 cursor-not-allowed"
                        />
                        <input type="hidden" name="currency" value="{{ $curr }}">
                    </div>
                </div>
                <p class="text-[11px] text-zinc-500 -mt-3">
                    {{ __('Locked to your primary currency (:currency)', ['currency' => $curr]) }}
                </p>

                {{-- Due Date (Optional) --}}
                <div>
                    <label for="due_date" class="block text-xs font-medium uppercase tracking-wider text-zinc-400 mb-1.5">
                        {{ __('Due date') }}
                    </label>
                    <input
                        type="date"
                        name="due_date"
                        id="due_date"
                        value="{{ old('due_date') }}"
                        class="w-full px-3.5 py-2.5 bg-zinc-900 border border-zinc-700/80 rounded-xl text-sm text-zinc-100 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                    />
                    <x-input-error :messages="$errors->get('due_date')" class="mt-1" />
                </div>

                {{-- Note --}}
                <div>
                    <label for="note" class="block text-xs font-medium uppercase tracking-wider text-zinc-400 mb-1.5">
                        {{ __('Note') }}
                    </label>
                    <input
                        type="text"
                        name="note"
                        id="note"
                        value="{{ old('note') }}"
                        maxlength="255"
                        placeholder="{{ __('e.g. Dinner, Concert ticket') }}"
                        class="w-full px-3.5 py-2.5 bg-zinc-900 border border-zinc-700/80 rounded-xl text-sm text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                    />
                    <x-input-error :messages="$errors->get('note')" class="mt-1" />
                </div>

                {{-- Form Actions --}}
                <div class="pt-4 border-t border-white/[0.06] flex items-center justify-end gap-3">
                    <x-secondary-button type="button" x-on:click="$dispatch('close')">
                        {{ __('Cancel') }}
                    </x-secondary-button>
                    <x-primary-button>
                        {{ __('Send loan proposal') }}
                    </x-primary-button>
                </div>
            @endif
        </form>
    </x-modal>
</x-app-layout>
