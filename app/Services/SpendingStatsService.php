<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Budget;
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
            'budgets' => $this->budgetOverview($userId, $through),
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
        $transactions = $this->expenseQuery($userId)
            ->with('splits')
            ->whereBetween('occurred_at', [$from, $to])
            ->get();

        $totals = [];

        foreach ($transactions as $transaction) {
            $currency = $transaction->base_currency;

            if ($transaction->splits->isNotEmpty()) {
                foreach ($transaction->splits as $split) {
                    if ($split->category !== null && $split->category !== '') {
                        $key = $split->category.'|'.$currency;
                        if (! isset($totals[$key])) {
                            $totals[$key] = [
                                'category' => $split->category,
                                'currency' => $currency,
                                'total' => 0.0,
                            ];
                        }
                        $totals[$key]['total'] += (float) $split->base_amount;
                    }
                }
            } elseif ($transaction->category !== null && $transaction->category !== '') {
                $key = $transaction->category.'|'.$currency;
                if (! isset($totals[$key])) {
                    $totals[$key] = [
                        'category' => $transaction->category,
                        'currency' => $currency,
                        'total' => 0.0,
                    ];
                }
                $totals[$key]['total'] += (float) $transaction->base_amount;
            }
        }

        return $totals;
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

        $transactions = $this->expenseQuery($userId)
            ->with('splits')
            // endOfDay keeps the final day of the baseline window, which a
            // bare subDay() would cut off at midnight.
            ->whereBetween('occurred_at', [$start, $through->startOfMonth()->subDay()->endOfDay()])
            ->get();

        $rows = [];

        foreach ($transactions as $transaction) {
            $month = $transaction->occurred_at->format('Y-m');
            $currency = $transaction->base_currency;

            if ($transaction->splits->isNotEmpty()) {
                foreach ($transaction->splits as $split) {
                    if ($split->category !== null && $split->category !== '') {
                        $key = $split->category.'|'.$currency;
                        $rows[$key][$month] = ($rows[$key][$month] ?? 0.0) + (float) $split->base_amount;
                    }
                }
            } elseif ($transaction->category !== null && $transaction->category !== '') {
                $key = $transaction->category.'|'.$currency;
                $rows[$key][$month] = ($rows[$key][$month] ?? 0.0) + (float) $transaction->base_amount;
            }
        }

        $result = [];
        foreach ($rows as $key => $months) {
            $result[$key] = [
                'averages' => array_values($months),
            ];
        }

        return $result;
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

    /**
     * Compute budget vs actual spending for a user in a given month.
     *
     * @return array{
     *     month: string,
     *     currency: string,
     *     total_budget: float,
     *     total_spent: float,
     *     total_remaining: float,
     *     percentage_used: float,
     *     status: 'safe'|'warning'|'exceeded',
     *     items: array<int, array{
     *         id: int,
     *         category: string,
     *         budget_amount: float,
     *         spent_amount: float,
     *         remaining_amount: float,
     *         percentage_used: float,
     *         status: 'safe'|'warning'|'exceeded'
     *     }>
     * }
     */
    public function budgetOverview(int $userId, ?CarbonImmutable $through = null): array
    {
        $through ??= CarbonImmutable::now();
        $periodMonth = $through->format('Y-m');
        $baseCurrency = strtoupper((string) config('finance.base_currency'));

        $monthStart = $through->startOfMonth();
        $monthEnd = $through->endOfMonth();

        $budgets = Budget::query()
            ->where('user_id', $userId)
            ->where('period_month', $periodMonth)
            ->orderBy('category')
            ->get();

        $transactions = $this->expenseQuery($userId)
            ->with('splits')
            ->whereBetween('occurred_at', [$monthStart, $monthEnd])
            ->get();

        $actuals = [];

        foreach ($transactions as $transaction) {
            if ($transaction->splits->isNotEmpty()) {
                foreach ($transaction->splits as $split) {
                    if ($split->category !== null && $split->category !== '') {
                        $actuals[$split->category] = ($actuals[$split->category] ?? 0.0) + (float) $split->base_amount;
                    }
                }
            } elseif ($transaction->category !== null && $transaction->category !== '') {
                $actuals[$transaction->category] = ($actuals[$transaction->category] ?? 0.0) + (float) $transaction->base_amount;
            }
        }

        $items = [];
        $totalBudget = 0.0;
        $totalSpent = 0.0;

        foreach ($budgets as $budget) {
            $budgetAmount = round((float) $budget->amount, 2);
            $spent = round((float) ($actuals[$budget->category] ?? 0.0), 2);
            $remaining = round($budgetAmount - $spent, 2);
            $pct = $budgetAmount > 0.0 ? round(($spent / $budgetAmount) * 100, 1) : 0.0;

            $status = match (true) {
                $spent >= $budgetAmount => 'exceeded',
                $pct >= 75.0 => 'warning',
                default => 'safe',
            };

            $totalBudget += $budgetAmount;
            $totalSpent += $spent;

            $items[] = [
                'id' => $budget->id,
                'category' => $budget->category,
                'budget_amount' => $budgetAmount,
                'spent_amount' => $spent,
                'remaining_amount' => $remaining,
                'percentage_used' => $pct,
                'status' => $status,
            ];
        }

        $totalRemaining = round($totalBudget - $totalSpent, 2);
        $overallPct = $totalBudget > 0.0 ? round(($totalSpent / $totalBudget) * 100, 1) : 0.0;
        $overallStatus = match (true) {
            $totalBudget > 0.0 && $totalSpent >= $totalBudget => 'exceeded',
            $overallPct >= 75.0 => 'warning',
            default => 'safe',
        };

        return [
            'month' => $periodMonth,
            'currency' => $baseCurrency,
            'total_budget' => round($totalBudget, 2),
            'total_spent' => round($totalSpent, 2),
            'total_remaining' => $totalRemaining,
            'percentage_used' => $overallPct,
            'status' => $overallStatus,
            'items' => $items,
        ];
    }
}
