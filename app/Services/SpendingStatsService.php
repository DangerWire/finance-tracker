<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Computes spending statistics in SQL so the model never performs arithmetic.
 *
 * Every figure handed to the language model is produced here. The model only
 * narrates these values, which is what keeps invented numbers out of the
 * insights.
 */
class SpendingStatsService
{
    /**
     * Number of trailing periods to compare against the current one.
     */
    private const PERIODS = 6;

    /**
     * A category is flagged as unusual when the period total exceeds this
     * multiple of its own average across the compared periods.
     */
    private const OUTLIER_MULTIPLIER = 2.0;

    /**
     * Build the full statistics payload for a user.
     *
     * When $inBaseCurrency is true every figure is expressed in the base currency.
     * When it is false the original amounts are used and, because summing
     * across currencies is meaningless, totals are reported per currency.
     *
     * @return array<string, mixed>
     */
    public function forUser(int $userId, ?CarbonImmutable $through = null, bool $inBaseCurrency = true): array
    {
        $through ??= CarbonImmutable::now();

        $baseCurrency = config('finance.base_currency');

        $amountColumn = $inBaseCurrency ? 'base_amount' : 'amount';
        $currencyColumn = $inBaseCurrency ? 'base_currency' : 'currency';

        $totals = $this->monthlyTotals($userId, $through, $inBaseCurrency);
        $currentPeriod = $through->format('Y-m');

        $periods = [];
        $previousTotals = [];

        for ($i = 1; $i <= self::PERIODS; $i++) {
            $key = $through->subMonthsNoOverflow($i)->format('Y-m');
            $periods[] = ['key' => $key, 'label' => $through->subMonthsNoOverflow($i)->format('M Y')];
            $previousTotals[$key] = $totals[$key] ?? 0.0;
        }

        $currentTotals = $totals[$currentPeriod] ?? 0.0;
        $previous = $previousTotals[array_key_first($previousTotals)] ?? 0.0;

        return [
            'mode' => $inBaseCurrency ? 'converted' : 'original',
            'display_currency' => $inBaseCurrency ? $baseCurrency : null,
            'base_currency' => $baseCurrency,
            'periods_compared' => self::PERIODS,
            'current_period' => [
                'key' => $currentPeriod,
                'label' => $through->format('M Y'),
                // Null when not converting: summing raw amounts across
                // currencies would produce a meaningless figure.
                'expenses' => $inBaseCurrency ? round($currentTotals, 2) : null,
                // Partial months make comparisons misleading, so the caller
                // is told how much of the month has actually elapsed.
                'is_partial' => $through->day < 28,
                'day_of_month' => $through->day,
            ],
            'previous_period' => [
                'label' => $through->subMonthsNoOverflow(1)->format('M Y'),
                'expenses' => round($previous, 2),
                'change_percent' => $inBaseCurrency ? $this->percentChange($currentTotals, $previous) : null,
            ],
            // Only meaningful when every row shares one currency.
            'currencies' => $inBaseCurrency
                ? []
                : $this->totalsByCurrency($userId, $through, $currencyColumn, $amountColumn),
            'history' => array_map(function (string $key) use ($previousTotals, $periods): array {
                $label = collect($periods)->firstWhere('key', $key)['label'] ?? $key;

                return [
                    'label' => $label,
                    'expenses' => round($previousTotals[$key], 2),
                ];
            }, array_keys($previousTotals)),
            'categories' => $this->categoryBreakdown($userId, $through, $inBaseCurrency),
            'largest_expense' => $this->largestExpense($userId, $inBaseCurrency),
            'transaction_count' => $this->transactionCount($userId, $through),
        ];
    }

    /**
     * Expense totals for the current month, keyed by currency.
     *
     * @return array<int, array{currency: string, total: float}>
     */
    private function totalsByCurrency(int $userId, CarbonImmutable $through, string $currencyColumn, string $amountColumn): array
    {
        $rows = $this->expenseQuery($userId, false)
            ->where('occurred_at', '>=', $through->startOfMonth())
            ->get([$currencyColumn, $amountColumn]);

        return $rows
            ->groupBy($currencyColumn)
            ->map(fn (Collection $group, string $currency): array => [
                'currency' => $currency,
                'total' => round((float) $group->sum($amountColumn), 2),
            ])
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /**
     * Expense totals per month, keyed by Y-m.
     *
     * @return array<string, float>
     */
    private function monthlyTotals(int $userId, CarbonImmutable $through, bool $inBaseCurrency = true): array
    {
        $start = $through->subMonthsNoOverflow(self::PERIODS + 1)->startOfMonth();
        $amountColumn = $inBaseCurrency ? 'base_amount' : 'amount';

        return $this->expenseQuery($userId, $inBaseCurrency)
            ->where('occurred_at', '>=', $start)
            ->get(['occurred_at', $amountColumn])
            ->groupBy(fn (Transaction $transaction): string => $transaction->occurred_at->format('Y-m'))
            ->map(fn (Collection $group): float => (float) $group->sum($amountColumn))
            ->all();
    }

    /**
     * Per-category totals for the current month, including change against the
     * previous month and a flag for unusual spending.
     *
     * @return array<int, array<string, mixed>>
     */
    private function categoryBreakdown(int $userId, CarbonImmutable $through, bool $inBaseCurrency = true): array
    {
        $monthStart = $through->startOfMonth();
        $previousStart = $through->subMonthsNoOverflow(1)->startOfMonth();

        $amountColumn = $inBaseCurrency ? 'base_amount' : 'amount';
        $currencyColumn = $inBaseCurrency ? 'base_currency' : 'currency';

        $current = $this->categoryTotalsBetween($userId, $monthStart, $through, $amountColumn, $currencyColumn);
        $previous = $this->categoryTotalsBetween($userId, $previousStart, $monthStart->subDay(), $amountColumn, $currencyColumn);

        $history = $this->categoryHistory($userId, $through, $amountColumn, $currencyColumn);

        $rows = [];

        foreach ($current as $key => $entry) {
            $category = $entry['category'];
            $total = $entry['total'];
            $previousTotal = $previous[$key]['total'] ?? 0.0;
            $averages = $history[$key]['averages'] ?? [];

            $rows[] = [
                'category' => $category,
                'currency' => $entry['currency'],
                'total' => round($total, 2),
                'share_of_spend_percent' => 0.0,
                'previous_total' => round($previousTotal, 2),
                'change_percent' => $this->percentChange($total, $previousTotal),
                'average_total' => $averages === [] ? null : round(array_sum($averages) / count($averages), 2),
                'is_unusual' => $averages !== []
                    && $total > (array_sum($averages) / count($averages)) * self::OUTLIER_MULTIPLIER,
            ];
        }

        usort($rows, fn (array $a, array $b): int => $b['total'] <=> $a['total']);

        // Shares are only comparable within a single currency.
        $grandTotalByCurrency = [];

        foreach ($rows as $row) {
            $grandTotalByCurrency[$row['currency']] = ($grandTotalByCurrency[$row['currency']] ?? 0.0) + $row['total'];
        }

        return array_map(function (array $row) use ($grandTotalByCurrency): array {
            $grandTotal = $grandTotalByCurrency[$row['currency']] ?? 0.0;

            $row['share_of_spend_percent'] = $grandTotal > 0
                ? round(($row['total'] / $grandTotal) * 100, 1)
                : 0.0;

            return $row;
        }, $rows);
    }

    /**
     * Category totals for a single month, keyed by category and currency.
     *
     * @return array<string, array{category: string, currency: string, total: float}>
     */
    private function categoryTotalsBetween(
        int $userId,
        CarbonImmutable $from,
        CarbonImmutable $to,
        string $amountColumn = 'base_amount',
        string $currencyColumn = 'base_currency',
    ): array {
        return $this->expenseQuery($userId)
            ->whereBetween('occurred_at', [$from, $to])
            ->get(['category', $amountColumn, $currencyColumn])
            ->filter(fn (Transaction $transaction): bool => $transaction->category !== null)
            ->groupBy(fn (Transaction $transaction): string => $transaction->category.'|'.$transaction->{$currencyColumn})
            ->map(function (Collection $group, string $key) use ($amountColumn, $currencyColumn): array {
                [$category, $currency] = explode('|', $key, 2);
                $first = $group->first();

                return [
                    'category' => $category,
                    'currency' => (string) $first->{$currencyColumn},
                    'total' => (float) $group->sum($amountColumn),
                ];
            })
            ->all();
    }

    /**
     * Monthly category totals across the compared window, used for averages.
     *
     * @return array<string, array{averages: array<int, float>}>
     */
    private function categoryHistory(
        int $userId,
        CarbonImmutable $through,
        string $amountColumn = 'base_amount',
        string $currencyColumn = 'base_currency',
    ): array {
        // The current month is excluded from the baseline: comparing this
        // month's spend against an average that already includes it would
        // suppress the very spikes the check is meant to catch.
        $start = $through->subMonthsNoOverflow(self::PERIODS)->startOfMonth();

        return $this->expenseQuery($userId)
            ->whereBetween('occurred_at', [$start, $through->startOfMonth()->subDay()])
            ->get(['category', $amountColumn, $currencyColumn, 'occurred_at'])
            ->filter(fn (Transaction $transaction): bool => $transaction->category !== null)
            ->groupBy(fn (Transaction $transaction): string => $transaction->category.'|'.$transaction->{$currencyColumn})
            ->map(fn (Collection $group): array => [
                'averages' => $group
                    ->groupBy(fn (Transaction $transaction): string => $transaction->occurred_at->format('Y-m'))
                    ->map(fn (Collection $month): float => (float) $month->sum($amountColumn))
                    ->values()
                    ->all(),
            ])
            ->all();
    }

    /**
     * The single largest expense in the current month.
     *
     * @return array<string, mixed>|null
     */
    private function largestExpense(int $userId, bool $inBaseCurrency = true): ?array
    {
        $amountColumn = $inBaseCurrency ? 'base_amount' : 'amount';
        $currencyColumn = $inBaseCurrency ? 'base_currency' : 'currency';

        $transaction = $this->expenseQuery($userId, $inBaseCurrency)
            ->where('occurred_at', '>=', CarbonImmutable::now()->startOfMonth())
            ->orderByDesc($amountColumn)
            ->first();

        if ($transaction === null) {
            return null;
        }

        return [
            'amount' => round((float) $transaction->{$amountColumn}, 2),
            'currency' => $transaction->{$currencyColumn},
            'original_amount' => (float) $transaction->amount,
            'original_currency' => $transaction->currency,
            'category' => $transaction->category,
            'note' => $transaction->note,
            'date' => $transaction->occurred_at->format('Y-m-d'),
        ];
    }

    /**
     * How many expenses the user recorded this month.
     */
    private function transactionCount(int $userId, CarbonImmutable $through): int
    {
        return $this->expenseQuery($userId)
            ->where('occurred_at', '>=', $through->startOfMonth())
            ->count();
    }

    /**
     * Base query for expenses belonging to a user.
     *
     * When converting, rows without a base snapshot are excluded because they
     * cannot contribute to a converted total.
     */
    private function expenseQuery(int $userId, bool $inBaseCurrency = true): Builder
    {
        return Transaction::query()
            ->where('user_id', $userId)
            ->where('type', TransactionType::Expense)
            ->when($inBaseCurrency, fn (Builder $query) => $query->whereNotNull('base_amount'));
    }

    /**
     * Percentage change, or null when there is no meaningful baseline.
     */
    private function percentChange(float $current, float $previous): ?float
    {
        if ($previous <= 0.0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
