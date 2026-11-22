<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ __('Financial Statement') }} — Finance Tracker</title>
        @vite(['resources/css/app.css'])
        <style>
            @media print {
                @page {
                    size: A4;
                    margin: 14mm;
                }
                body {
                    background: #ffffff !important;
                    color: #09090b !important;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
                tr {
                    break-inside: avoid;
                }
            }
        </style>
    </head>
    <body class="bg-zinc-100 text-zinc-900 antialiased min-h-screen py-6 px-4 sm:px-8 print:bg-white print:p-0">
        {{-- Top Action Bar (hidden when printing) --}}
        <div class="max-w-4xl mx-auto mb-6 flex flex-wrap items-center justify-between gap-3 print:hidden">
            <a href="{{ route('transactions.index', request()->only(['type', 'category', 'date', 'from', 'to'])) }}"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg bg-white border border-zinc-300 text-xs font-medium text-zinc-700 hover:bg-zinc-50 shadow-sm">
                &larr; <span>{{ __('Back to transactions') }}</span>
            </a>

            <div class="flex items-center gap-2.5">
                <a href="{{ route('transactions.export.csv', request()->only(['type', 'category', 'date', 'from', 'to'])) }}"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg bg-white border border-zinc-300 text-xs font-medium text-zinc-700 hover:bg-zinc-50 shadow-sm">
                    <span>{{ __('Download CSV') }}</span>
                </a>
                <button type="button" onclick="window.print()"
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-xs font-semibold text-white shadow-sm">
                    <span>{{ __('Print / Save as PDF') }}</span>
                </button>
            </div>
        </div>

        {{-- Printable Statement Sheet --}}
        <div class="max-w-4xl mx-auto bg-white rounded-2xl border border-zinc-200 shadow-sm p-8 sm:p-10 print:rounded-none print:border-0 print:shadow-none print:p-0">
            {{-- Statement Header --}}
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 pb-6 border-b border-zinc-200">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-emerald-700">Finance Tracker</p>
                    <h1 class="text-2xl font-bold text-zinc-900 tracking-tight mt-1">{{ __('Financial Statement') }}</h1>
                    <p class="text-xs text-zinc-500 mt-1">
                        {{ __('Account holder:') }} <span class="font-medium text-zinc-800">{{ $user->name }}</span> ({{ $user->email }})
                    </p>
                </div>

                <div class="sm:text-right text-xs text-zinc-600 space-y-1">
                    <div>
                        <span class="text-zinc-400">{{ __('Generated on:') }}</span>
                        <span class="font-mono font-medium text-zinc-800">{{ $generatedAt->format('Y-m-d H:i') }}</span>
                    </div>
                    <div>
                        <span class="text-zinc-400">{{ __('Primary Currency') }}:</span>
                        <span class="font-mono font-semibold text-zinc-900">{{ $baseCurrency }}</span>
                    </div>
                    <div>
                        <span class="text-zinc-400">{{ __('Statement period:') }}</span>
                        <span class="font-mono font-medium text-zinc-800">
                            @if (! empty($filters['date']))
                                {{ $filters['date'] }}
                            @elseif ($from || $to)
                                {{ $from?->format('Y-m-d') ?? '...' }} &rarr; {{ $to?->format('Y-m-d') ?? '...' }}
                            @else
                                {{ __('All dates') }}
                            @endif
                        </span>
                    </div>
                </div>
            </div>

            {{-- Summary KPIs --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 my-6">
                <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4">
                    <p class="text-[11px] font-medium uppercase tracking-wider text-zinc-500">{{ __('Income') }}</p>
                    <p class="text-xl font-bold font-mono text-emerald-700 mt-1">
                        +{{ number_format($income, 2) }} <span class="text-xs font-normal text-zinc-500">{{ $baseCurrency }}</span>
                    </p>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4">
                    <p class="text-[11px] font-medium uppercase tracking-wider text-zinc-500">{{ __('Expenses') }}</p>
                    <p class="text-xl font-bold font-mono text-rose-700 mt-1">
                        −{{ number_format($expenses, 2) }} <span class="text-xs font-normal text-zinc-500">{{ $baseCurrency }}</span>
                    </p>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4">
                    <p class="text-[11px] font-medium uppercase tracking-wider text-zinc-500">{{ __('Balance') }}</p>
                    <p @class([
                        'text-xl font-bold font-mono mt-1',
                        'text-emerald-700' => $balance >= 0,
                        'text-rose-700' => $balance < 0,
                    ])>
                        {{ $balance >= 0 ? '+' : '−' }}{{ number_format(abs($balance), 2) }} <span class="text-xs font-normal text-zinc-500">{{ $baseCurrency }}</span>
                    </p>
                </div>
            </div>

            @if ($unconvertedCount > 0)
                <div class="mb-6 rounded-lg border border-amber-300 bg-amber-50 px-4 py-2.5 text-xs text-amber-900">
                    {{ trans_choice(':count transaction could not be converted to :currency and is excluded from these totals. Run finance:backfill-base-amounts once a rate is available.', $unconvertedCount, ['count' => $unconvertedCount, 'currency' => $baseCurrency]) }}
                </div>
            @endif

            {{-- Itemized Ledger Table --}}
            @if ($transactions->isEmpty())
                <div class="py-10 text-center text-sm text-zinc-500 border border-dashed border-zinc-200 rounded-xl">
                    {{ __('No transactions match the selected filters.') }}
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 text-xs">
                        <thead>
                            <tr class="border-b border-zinc-300 text-zinc-500 uppercase tracking-wider text-[10px]">
                                <th class="py-2.5 pr-3 text-left">{{ __('Date') }}</th>
                                <th class="py-2.5 px-3 text-left">{{ __('Type') }}</th>
                                <th class="py-2.5 px-3 text-left">{{ __('Category') }}</th>
                                <th class="py-2.5 px-3 text-left">{{ __('Note') }}</th>
                                <th class="py-2.5 px-3 text-right">{{ __('Amount') }}</th>
                                <th class="py-2.5 pl-3 text-right">{{ __('In base currency') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100">
                            @foreach ($transactions as $transaction)
                                <tr>
                                    <td class="py-2.5 pr-3 font-mono whitespace-nowrap text-zinc-600">
                                        {{ $transaction->occurred_at?->format('Y-m-d H:i') ?? '—' }}
                                    </td>
                                    <td class="py-2.5 px-3 whitespace-nowrap">
                                        <span @class([
                                            'font-medium',
                                            'text-emerald-700' => $transaction->type === \App\Enums\TransactionType::Income,
                                            'text-rose-700' => $transaction->type === \App\Enums\TransactionType::Expense,
                                        ])>
                                            {{ __($transaction->type->label()) }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-3 text-zinc-800">
                                        @if ($transaction->isSplit())
                                            {{ __('Split') }} ({{ $transaction->splits->count() }})
                                        @else
                                            {{ $transaction->category ?? '—' }}
                                        @endif
                                    </td>
                                    <td class="py-2.5 px-3 text-zinc-600 max-w-xs truncate">
                                        {{ $transaction->note ?? '—' }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-mono whitespace-nowrap text-zinc-900 font-medium">
                                        {{ $transaction->type->sign() > 0 ? '+' : '−' }}{{ number_format((float) $transaction->amount, 2) }} {{ $transaction->currency }}
                                    </td>
                                    <td class="py-2.5 pl-3 text-right font-mono whitespace-nowrap text-zinc-600">
                                        @if ($transaction->base_amount !== null)
                                            {{ $transaction->type->sign() > 0 ? '+' : '−' }}{{ number_format((float) $transaction->base_amount, 2) }} {{ $transaction->base_currency }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </body>
</html>
