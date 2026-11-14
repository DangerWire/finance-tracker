<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-zinc-100 leading-tight">
                {{ __('Spending insights') }}
            </h2>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-mono-num font-medium bg-zinc-800 text-zinc-300 border border-zinc-700/60">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                    <span>{{ $stats['display_currency'] }}</span>
                </span>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if ($errors->any())
                <div class="bg-rose-500/10 border border-rose-500/20 text-rose-300 text-sm rounded-xl px-4 py-3 flex items-center gap-2">
                    <svg class="w-4 h-4 text-rose-400 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <!-- Top Summary KPI Cards -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="sure-card rounded-xl p-5 sure-hover-lift">
                    <div class="flex items-center justify-between text-xs text-zinc-400 mb-2">
                        <span class="font-medium uppercase tracking-wider text-[11px]">{{ __('Spending this month') }}</span>
                        @if ($stats['current_period']['is_partial'])
                            <span class="text-[10px] font-mono-num text-amber-400 bg-amber-500/10 px-1.5 py-0.5 rounded border border-amber-500/20">
                                {{ __('(day :day, incomplete)', ['day' => $stats['current_period']['day_of_month']]) }}
                            </span>
                        @endif
                    </div>

                    <p class="text-2xl font-semibold font-mono-num text-zinc-100 tracking-tight">
                        {{ number_format($stats['current_period']['expenses'], 0) }}
                        <span class="text-xs font-normal text-zinc-500 ml-1">{{ $stats['display_currency'] }}</span>
                    </p>

                    @if ($stats['previous_period']['change_percent'] !== null)
                        <div class="mt-2 flex items-center gap-1.5 text-xs">
                            <span @class([
                                'inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-mono-num font-medium',
                                'text-rose-400 bg-rose-500/10 border border-rose-500/20' => $stats['previous_period']['change_percent'] > 0,
                                'text-emerald-400 bg-emerald-500/10 border border-emerald-500/20' => $stats['previous_period']['change_percent'] <= 0,
                            ])>
                                {{ $stats['previous_period']['change_percent'] > 0 ? '↑' : '↓' }}
                                {{ number_format(abs($stats['previous_period']['change_percent']), 1) }}%
                            </span>
                            <span class="text-zinc-500">{{ __('vs :month', ['month' => $stats['previous_period']['label']]) }}</span>
                        </div>
                    @endif
                </div>

                <div class="sure-card rounded-xl p-5 sure-hover-lift">
                    <div class="flex items-center justify-between text-xs text-zinc-400 mb-2">
                        <span class="font-medium uppercase tracking-wider text-[11px]">{{ __('Expenses recorded') }}</span>
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-mono-num font-medium bg-zinc-800 text-zinc-400 border border-zinc-700">
                            COUNT
                        </span>
                    </div>
                    <p class="text-2xl font-semibold font-mono-num text-zinc-100 tracking-tight">
                        {{ $stats['transaction_count'] }}
                    </p>
                    <p class="mt-2 text-xs text-zinc-500">
                        {{ __('Active in current monthly period') }}
                    </p>
                </div>

                <div class="sure-card rounded-xl p-5 sure-hover-lift">
                    <div class="flex items-center justify-between text-xs text-zinc-400 mb-2">
                        <span class="font-medium uppercase tracking-wider text-[11px]">{{ __('Largest single expense') }}</span>
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-mono-num font-medium bg-zinc-800 text-zinc-400 border border-zinc-700">
                            PEAK
                        </span>
                    </div>
                    @if ($stats['largest_expense'])
                        <p class="text-2xl font-semibold font-mono-num text-zinc-100 tracking-tight">
                            {{ number_format($stats['largest_expense']['amount'], 0) }}
                            <span class="text-xs font-normal text-zinc-500 ml-1">{{ $stats['largest_expense']['currency'] }}</span>
                        </p>
                        <p class="mt-2 text-xs text-zinc-400 truncate">
                            <span class="font-medium text-zinc-300">{{ $stats['largest_expense']['category'] }}</span> ·
                            <span class="font-mono-num text-zinc-500">{{ $stats['largest_expense']['date'] }}</span>
                        </p>
                    @else
                        <p class="text-2xl font-semibold font-mono-num text-zinc-500 tracking-tight">—</p>
                        <p class="mt-2 text-xs text-zinc-500">{{ __('No entries found') }}</p>
                    @endif
                </div>
            </div>

            <!-- Sure AI / Gemini Financial Intelligence Hub -->
            <div class="sure-card rounded-xl p-6 space-y-5">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-white/[0.06]">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-semibold text-sm text-zinc-100 flex items-center gap-2">
                                <span>{{ __('AI summary') }}</span>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-mono-num font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                    GEMINI 2.5 FLASH
                                </span>
                            </h3>
                            <p class="text-xs text-zinc-400">{{ __('Algorithmic spending pattern & anomaly detection') }}</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('insights.generate') }}">
                        @csrf
                        <x-primary-button :disabled="! $isConfigured || ! $hasEnoughData" class="w-full sm:w-auto">
                            <svg class="w-3.5 h-3.5 mr-1.5 text-zinc-950" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            <span>{{ __('Generate insights') }}</span>
                        </x-primary-button>
                    </form>
                </div>

                @if (! $isConfigured)
                    <div class="p-6 rounded-xl bg-zinc-950/60 border border-white/[0.04] text-center">
                        <p class="text-sm text-zinc-400">
                            {{ __('Set GEMINI_API_KEY in .env to enable AI summaries. The figures above are calculated by the database and always accurate.') }}
                        </p>
                    </div>
                @elseif (! $hasEnoughData)
                    <div class="p-6 rounded-xl bg-zinc-950/60 border border-white/[0.04] text-center">
                        <p class="text-sm text-zinc-400">
                            {{ __('Record a few more expenses this month and insights will become available.') }}
                        </p>
                    </div>
                @elseif ($insights === null)
                    <div class="p-6 rounded-xl bg-zinc-950/60 border border-white/[0.04] text-center">
                        <p class="text-sm text-zinc-400">
                            {{ __('Insights have not been generated for this period yet.') }}
                        </p>
                    </div>
                @else
                    @if ($headline)
                        <div class="p-4 rounded-xl bg-emerald-500/5 border border-emerald-500/20 text-emerald-200">
                            <p class="text-sm font-medium leading-relaxed">{{ $headline }}</p>
                        </div>
                    @endif

                    <div class="space-y-3">
                        @forelse ($insights as $insight)
                            <div @class([
                                'rounded-xl p-4 transition-colors border',
                                'bg-amber-500/5 border-amber-500/20' => $insight['severity'] === 'attention',
                                'bg-zinc-950/60 border-white/[0.04]' => $insight['severity'] !== 'attention',
                            ])>
                                <div class="flex items-baseline justify-between gap-3 mb-1">
                                    <div class="flex items-center gap-2">
                                        @if ($insight['severity'] === 'attention')
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-400 shrink-0"></span>
                                        @else
                                            <span class="w-1.5 h-1.5 rounded-full bg-zinc-400 shrink-0"></span>
                                        @endif
                                        <p class="text-sm font-medium text-zinc-100">{{ $insight['title'] }}</p>
                                    </div>
                                    @if ($insight['category'] !== '')
                                        <span class="shrink-0 rounded-md bg-zinc-800/80 border border-zinc-700 px-2 py-0.5 text-[11px] font-medium text-zinc-300">
                                            {{ $insight['category'] }}
                                        </span>
                                    @endif
                                </div>
                                <p class="text-xs text-zinc-400 leading-relaxed pl-3.5">{{ $insight['detail'] }}</p>
                            </div>
                        @empty
                            <p class="text-sm text-zinc-400">
                                {{ __('Not enough recorded spending to draw a conclusion this month.') }}
                            </p>
                        @endforelse
                    </div>
                @endif
            </div>

            <!-- "Where it went" Category Allocation Breakdown -->
            @if ($stats['categories'] !== [])
                <div class="sure-card rounded-xl overflow-hidden">
                    <div class="border-b border-white/[0.06] px-6 py-4 flex items-center justify-between">
                        <h3 class="font-semibold text-sm text-zinc-100">{{ __('Where it went') }}</h3>
                        <span class="text-xs text-zinc-400 font-mono-num">{{ count($stats['categories']) }} {{ __('categories') }}</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-white/[0.04]">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3.5 text-left text-xs font-medium uppercase tracking-wider text-zinc-400">{{ __('Category') }}</th>
                                    <th class="px-6 py-3.5 text-left text-xs font-medium uppercase tracking-wider text-zinc-400">{{ __('Spent') }}</th>
                                    <th class="px-6 py-3.5 text-left text-xs font-medium uppercase tracking-wider text-zinc-400">{{ __('Share') }}</th>
                                    <th class="px-6 py-3.5 text-left text-xs font-medium uppercase tracking-wider text-zinc-400">
                                        {{ __('Change vs last month') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/[0.03]">
                                @foreach ($stats['categories'] as $category)
                                    <tr class="hover:bg-white/[0.02] transition-colors">
                                        <td class="px-6 py-4 text-sm text-zinc-100">
                                            <div class="flex items-center gap-2">
                                                <span class="font-medium">{{ $category['category'] }}</span>
                                                @if ($category['is_unusual'])
                                                    <span class="rounded-full bg-amber-500/10 border border-amber-500/20 px-2 py-0.5 text-[10px] font-medium text-amber-400">
                                                        {{ __('unusual') }}
                                                    </span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-xs font-mono-num text-zinc-200">
                                            <span class="font-medium">{{ number_format($category['total'], 0) }}</span>
                                            <span class="text-zinc-500 text-[11px] ml-0.5">{{ $category['currency'] }}</span>
                                        </td>
                                        <td class="px-6 py-4 text-xs">
                                            <div class="flex items-center gap-3">
                                                <span class="font-mono-num text-zinc-300 w-12">{{ number_format($category['share_of_spend_percent'], 1) }}%</span>
                                                <div class="w-24 bg-zinc-800 rounded-full h-1.5 overflow-hidden">
                                                    <div class="bg-emerald-400 h-full rounded-full" style="width: {{ min(100, $category['share_of_spend_percent']) }}%"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td @class([
                                            'px-6 py-4 text-xs font-mono-num font-medium',
                                            'text-rose-400' => ($category['change_percent'] ?? 0) > 0,
                                            'text-emerald-400' => ($category['change_percent'] ?? 0) < 0,
                                            'text-zinc-500' => $category['change_percent'] === null,
                                        ])>
                                            @if ($category['change_percent'] === null)
                                                —
                                            @else
                                                <span class="inline-flex items-center gap-0.5">
                                                    <span>{{ $category['change_percent'] > 0 ? '↑' : '↓' }}</span>
                                                    <span>{{ number_format(abs($category['change_percent']), 1) }}%</span>
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
        </div>
    </div>
</x-app-layout>
