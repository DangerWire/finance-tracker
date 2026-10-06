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
     * Every figure is expressed in the base currency. There is deliberately no
     * unconverted mode: summing raw amounts across currencies is meaningless,
     * and reporting per-currency totals instead produced a page that could not
     * answer the question it existed to answer.
     *
     * @return array<string, mixed>
     */
    public function forUser(int $userId, ?CarbonImmutable $through = null): array
    {
        $through ??= CarbonImmutable::now();

        $baseCurrency = strtoupper((string) config('finance.base_currency'));

        $totals = $this->monthlyTotals($userId, $through);
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
            'display_currency' => $baseCurrency,
            'base_currency' => $baseCurrency,
            'periods_compared' => self::PERIODS,
            'current_period' => [
                'key' => $currentPeriod,
                'label' => $through->format('M Y'),
                'expenses' => round($currentTotals, 2),
                // Partial months make comparisons misleading, so the caller
                // is told how much of the month has actually elapsed.
                'is_partial' => $through->day < 28,
                'day_of_month' => $through->day,
            ],
            'previous_period' => [
                'label' => $through->subMonthsNoOverflow(1)->format('M Y'),
                'expenses' => round($previous, 2),
                'change_percent' => $this->percentChange($currentTotals, $previous),
            ],
            'history' => array_map(function (string $key) use ($previousTotals, $periods): array {
                $label = collect($periods)->firstWhere('key', $key)['label'] ?? $key;

                return [
                    'label' => $label,
                    'expenses' => round($previousTotals[$key], 2),
                ];
            }, array_keys($previousTotals)),
            'categories' => $this->categoryBreakdown($userId, $through),
            'largest_expense' => $this->largestExpense($userId, $through),
            'transaction_count' => $this->transactionCount($userId, $through),
        ];
    }

    /**
     * Expense totals per month, keyed by Y-m.
     *
     * @return array<string, float>
     */
    private function monthlyTotals(int $userId, CarbonImmutable $through): array
    {
        $start = $through->subMonthsNoOverflow(self::PERIODS + 1)->startOfMonth();

        return $this->expenseQuery($userId)
            ->where('occurred_at', '>=', $start)
            ->get(['occurred_at', 'base_amount'])
            ->groupBy(fn (Transaction $transaction): string => $transaction->occurred_at->format('Y-m'))
            ->map(fn (Collection $group): float => (float) $group->sum('base_amount'))
            ->all();
    }

    /**
     * Per-category totals for the current month, including change against the
     * previous month and a flag for unusual spending.
     *
     * @return array<int, array<string, mixed>>
     */
    private function categoryBreakdown(int $userId, CarbonImmutable $through): array
    {
        $monthStart = $through->startOfMonth();
        $previousStart = $through->subMonthsNoOverflow(1)->startOfMonth();

        // The period runs to the end of the month, not to the instant of the
        // query. Bounding at "now" would drop anything recorded earlier today
        // whose time of day is later than the clock, which is most of a day's
        // entries when the app's timezone is not the one the user is typing
        // in. It would also disagree with monthlyTotals, which is unbounded,
        // so the headline total and the category breakdown would not add up.
        $monthEnd = $monthStart->endOfMonth();

        // The previous month ends on its last day in full. subDay() alone
        // would land on midnight at the start of that day and quietly discard
        // everything recorded during it.
        $previousEnd = $monthStart->subDay()->endOfDay();

        $current = $this->categoryTotalsBetween($userId, $monthStart, $monthEnd);
        $previous = $this->categoryTotalsBetween($userId, $previousStart, $previousEnd);

        $history = $this->categoryHistory($userId, $through);

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

        $grandTotal = array_sum(array_column($rows, 'total'));

        return array_map(fn (array $row): array => [
            ...$row,
            // Every row is already in the base currency, so shares are
            // comparable across the whole breakdown. A spread is used rather
            // than the union operator, which would keep $row's placeholder and
            // silently report every share as zero.
            'share_of_spend_percent' => $grandTotal > 0
                ? round(($row['total'] / $grandTotal) * 100, 1)
                : 0.0,
        ], $rows);
    }

    /**
     * Category totals for a single month, keyed by category and currency.
     *
     * @return array<string, array{category: string, currency: string, total: float}>
     */
    private function categoryTotalsBetween(int $userId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->expenseQuery($userId)
            ->whereBetween('occurred_at', [$from, $to])
            ->get(['category', 'base_amount', 'base_currency'])
            ->filter(fn (Transaction $transaction): bool => $transaction->category !== null)
            ->groupBy(fn (Transaction $transaction): string => $transaction->category.'|'.$transaction->base_currency)
            ->map(function (Collection $group, string $key): array {
                [$category, $currency] = explode('|', $key, 2);

                return [
                    'category' => $category,
                    'currency' => $currency,
                    'total' => (float) $group->sum('base_amount'),
                ];
            })
            ->all();
    }

    /**
     * Monthly category totals across the compared window, used for averages.
     *
     * @return array<string, array{averages: array<int, float>}>
     */
    private function categoryHistory(int $userId, CarbonImmutable $through): array
    {
        // The current month is excluded from the baseline: comparing this
        // month's spend against an average that already includes it would
        // suppress the very spikes the check is meant to catch.
        $start = $through->subMonthsNoOverflow(self::PERIODS)->startOfMonth();

        return $this->expenseQuery($userId)
            // endOfDay keeps the final day of the baseline window, which a
            // bare subDay() would cut off at midnight.
            ->whereBetween('occurred_at', [$start, $through->startOfMonth()->subDay()->endOfDay()])
            ->get(['category', 'base_amount', 'base_currency', 'occurred_at'])
            ->filter(fn (Transaction $transaction): bool => $transaction->category !== null)
            ->groupBy(fn (Transaction $transaction): string => $transaction->category.'|'.$transaction->base_currency)
            ->map(fn (Collection $group): array => [
                'averages' => $group
                    ->groupBy(fn (Transaction $transaction): string => $transaction->occurred_at->format('Y-m'))
                    ->map(fn (Collection $month): float => (float) $month->sum('base_amount'))
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
    private function largestExpense(int $userId, CarbonImmutable $through): ?array
    {
        $transaction = $this->expenseQuery($userId)
            ->whereBetween('occurred_at', [$through->startOfMonth(), $through->startOfMonth()->endOfMonth()])
            ->orderByDesc('base_amount')
            ->first();

        if ($transaction === null) {
            return null;
        }

        return [
            'amount' => round((float) $transaction->base_amount, 2),
            'currency' => $transaction->base_currency,
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
            ->whereBetween('occurred_at', [$through->startOfMonth(), $through->startOfMonth()->endOfMonth()])
            ->count();
    }

    /**
     * Base query for expenses belonging to a user.
     *
     * Rows are excluded unless their snapshot was taken into the base currency
     * currently in effect. A row with no snapshot has no converted amount at
     * all, and a row whose base_currency differs was converted at a time when
     * a different base applied; including either would add numbers
     * denominated in different currencies together.
     */
    private function expenseQuery(int $userId): Builder
    {
        return Transaction::query()
            ->where('user_id', $userId)
            ->where('type', TransactionType::Expense)
            ->whereNotNull('base_amount')
            ->where('base_currency', strtoupper((string) config('finance.base_currency')));
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
