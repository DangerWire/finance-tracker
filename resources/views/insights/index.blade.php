<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Spending insights') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if ($errors->any())
                <div class="bg-rose-50 border border-rose-200 text-rose-800 text-sm rounded-lg px-4 py-3">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="bg-white p-4 shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('insights.preference') }}" class="flex flex-wrap items-center gap-4">
                    @csrf
                    @method('PUT')

                    <label for="prefers_base_currency" class="flex items-center gap-2 text-sm text-gray-700">
                        <input id="prefers_base_currency" name="prefers_base_currency" type="checkbox" value="1"
                            @checked($convert)
                            onchange="this.form.submit()"
                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        <span>
                            {{ __('Convert CNY to my base currency') }}
                            <span class="block text-xs text-gray-500">
                                {{ __('Off shows each currency separately instead of one combined total.') }}
                            </span>
                        </span>
                    </label>

                    <noscript>
                        <x-primary-button>{{ __('Save') }}</x-primary-button>
                    </noscript>
                </form>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="bg-white p-4 shadow-sm sm:rounded-lg">
                    <p class="text-sm text-gray-500">
                        {{ __('Spending this month') }}
                        @if ($stats['current_period']['is_partial'])
                            <span class="text-xs text-amber-600">
                                {{ __('(day :day, incomplete)', ['day' => $stats['current_period']['day_of_month']]) }}
                            </span>
                        @endif
                    </p>

                    @if ($convert)
                        <p class="mt-1 text-2xl font-semibold text-gray-900">
                            {{ number_format($stats['current_period']['expenses'], 0) }}
                            <span class="text-sm font-normal text-gray-400">{{ $stats['display_currency'] }}</span>
                        </p>
                        @if ($stats['previous_period']['change_percent'] !== null)
                            <p @class([
                                'mt-1 text-xs font-medium',
                                'text-rose-600' => $stats['previous_period']['change_percent'] > 0,
                                'text-emerald-600' => $stats['previous_period']['change_percent'] <= 0,
                            ])>
                                {{ $stats['previous_period']['change_percent'] > 0 ? '↑' : '↓' }}
                                {{ number_format(abs($stats['previous_period']['change_percent']), 1) }}%
                                <span class="font-normal text-gray-400">{{ __('vs :month', ['month' => $stats['previous_period']['label']]) }}</span>
                            </p>
                        @endif
                    @else
                        @foreach ($stats['currencies'] as $currencyTotal)
                            <p class="mt-1 text-2xl font-semibold text-gray-900">
                                {{ number_format($currencyTotal['total'], 0) }}
                                <span class="text-sm font-normal text-gray-400">{{ $currencyTotal['currency'] }}</span>
                            </p>
                        @endforeach
                        @if ($stats['currencies'] === [])
                            <p class="mt-1 text-2xl font-semibold text-gray-400">—</p>
                        @endif
                    @endif
                </div>

                <div class="bg-white p-4 shadow-sm sm:rounded-lg">
                    <p class="text-sm text-gray-500">{{ __('Expenses recorded') }}</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $stats['transaction_count'] }}</p>
                </div>

                <div class="bg-white p-4 shadow-sm sm:rounded-lg">
                    <p class="text-sm text-gray-500">{{ __('Largest single expense') }}</p>
                    @if ($stats['largest_expense'])
                        <p class="mt-1 text-2xl font-semibold text-gray-900">
                            {{ number_format($stats['largest_expense']['amount'], 0) }}
                            <span class="text-sm font-normal text-gray-400">{{ $stats['largest_expense']['currency'] }}</span>
                        </p>
                        <p class="mt-1 text-xs text-gray-500">
                            {{ $stats['largest_expense']['category'] }} ·
                            {{ $stats['largest_expense']['date'] }}
                        </p>
                    @else
                        <p class="mt-1 text-2xl font-semibold text-gray-400">—</p>
                    @endif
                </div>
            </div>

            <div class="bg-white p-6 shadow-sm sm:rounded-lg space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-gray-800">{{ __('AI summary') }}</h3>
                    <form method="POST" action="{{ route('insights.generate') }}">
                        @csrf
                        <x-primary-button :disabled="! $isConfigured || ! $hasEnoughData">
                            {{ __('Generate insights') }}
                        </x-primary-button>
                    </form>
                </div>

                @if (! $isConfigured)
                    <p class="text-sm text-gray-500">
                        {{ __('Set OPENROUTER_API_KEY in .env to enable AI summaries. The figures above are calculated by the database and always accurate.') }}
                    </p>
                @elseif (! $hasEnoughData)
                    <p class="text-sm text-gray-500">
                        {{ __('Record a few more expenses this month and insights will become available.') }}
                    </p>
                @elseif ($insights === null)
                    <p class="text-sm text-gray-500">
                        {{ __('Insights have not been generated for this period yet.') }}
                    </p>
                @else
                    @if ($headline)
                        <p class="text-base text-gray-900">{{ $headline }}</p>
                    @endif

                    @forelse ($insights as $insight)
                        <div @class([
                            'rounded-lg border px-4 py-3',
                            'border-amber-200 bg-amber-50' => $insight['severity'] === 'attention',
                            'border-gray-200 bg-gray-50' => $insight['severity'] !== 'attention',
                        ])>
                            <div class="flex items-baseline justify-between gap-3">
                                <p class="text-sm font-medium text-gray-900">{{ $insight['title'] }}</p>
                                @if ($insight['category'] !== '')
                                    <span class="shrink-0 rounded-full bg-white px-2 py-0.5 text-xs text-gray-600">
                                        {{ $insight['category'] }}
                                    </span>
                                @endif
                            </div>
                            <p class="mt-1 text-sm text-gray-600">{{ $insight['detail'] }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">
                            {{ __('Not enough recorded spending to draw a conclusion this month.') }}
                        </p>
                    @endforelse
                @endif
            </div>

            @if ($stats['categories'] !== [])
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="border-b border-gray-100 px-6 py-4">
                        <h3 class="font-semibold text-gray-800">{{ __('Where it went') }}</h3>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Category') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Spent') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Share') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                    {{ $convert ? __('Change') : __('Change vs last month') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($stats['categories'] as $category)
                                <tr>
                                    <td class="px-6 py-3 text-sm text-gray-900">
                                        {{ $category['category'] }}
                                        @if ($category['is_unusual'])
                                            <span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">
                                                {{ __('unusual') }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-3 text-sm text-gray-700">
                                        {{ number_format($category['total'], 0) }}
                                        <span class="text-xs text-gray-400">{{ $category['currency'] }}</span>
                                    </td>
                                    <td class="px-6 py-3 text-sm text-gray-700">{{ number_format($category['share_of_spend_percent'], 1) }}%</td>
                                    <td @class([
                                        'px-6 py-3 text-sm',
                                        'text-rose-600' => ($category['change_percent'] ?? 0) > 0,
                                        'text-emerald-600' => ($category['change_percent'] ?? 0) < 0,
                                        'text-gray-400' => $category['change_percent'] === null,
                                    ])>
                                        @if ($category['change_percent'] === null)
                                            —
                                        @else
                                            {{ $category['change_percent'] > 0 ? '↑' : '↓' }}
                                            {{ number_format(abs($category['change_percent']), 1) }}%
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>