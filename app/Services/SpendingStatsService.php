<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Computes spending statistics from frozen base-currency snapshots so the AI
 * model never performs arithmetic.
 *
 * Every figure handed to the language model is produced here. The model only
 * narrates these values, which is what keeps invented numbers out of the
 * insights.
 */
class SpendingStatsService
{
    public function __construct(
        private readonly RecurringBudgetService $recurringBudgets,
    ) {}

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
     * Every figure is expressed in the base currency. Fetches the comparison
     * window once (with splits eager-loaded) so remote database latency does
     * not multiply across redundant round-trips.
     *
     * @return array<string, mixed>
     */
    public function forUser(int $userId, ?CarbonImmutable $through = null, ?User $user = null): array
    {
        $through ??= CarbonImmutable::now();

        $user ??= User::find($userId);
        $baseCurrency = strtoupper((string) ($user?->currency ?? config('finance.base_currency')));

        $monthStart = $through->startOfMonth();
        $monthEnd = $monthStart->endOfMonth();
        $windowStart = $through->subMonthsNoOverflow(self::PERIODS + 1)->startOfMonth();

        /** @var Collection<int, Transaction> $windowExpenses */
        $windowExpenses = $this->expenseQuery($userId, $baseCurrency)
            ->with('splits')
            ->whereBetween('occurred_at', [$windowStart, $monthEnd])
            ->get();

        $totals = $this->monthlyTotalsFrom($windowExpenses);
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

        /** @var Collection<int, Transaction> $currentMonthExpenses */
        $currentMonthExpenses = $windowExpenses
            ->filter(fn (Transaction $tx): bool => $tx->occurred_at !== null
                && $tx->occurred_at->betweenIncluded($monthStart, $monthEnd))
            ->values();

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
            'categories' => $this->categoryBreakdownFrom($windowExpenses, $currentMonthExpenses, $through),
            'largest_expense' => $this->largestExpenseFrom($currentMonthExpenses),
            'transaction_count' => $currentMonthExpenses->count(),
            'budgets' => $this->budgetOverview($userId, $through, $user, $currentMonthExpenses),
        ];
    }

    /**
     * Expense totals per month, keyed by Y-m.
     *
     * @param  Collection<int, Transaction>  $expenses
     * @return array<string, float>
     */
    private function monthlyTotalsFrom(Collection $expenses): array
    {
        return $expenses
            ->groupBy(fn (Transaction $transaction): string => $transaction->occurred_at->format('Y-m'))
            ->map(fn (Collection $group): float => (float) $group->sum('base_amount'))
            ->all();
    }

    /**
     * Per-category totals for the current month, including change against the
     * previous month and a flag for unusual spending.
     *
     * @param  Collection<int, Transaction>  $windowExpenses
     * @param  Collection<int, Transaction>  $currentMonthExpenses
     * @return array<int, array<string, mixed>>
     */
    private function categoryBreakdownFrom(
        Collection $windowExpenses,
        Collection $currentMonthExpenses,
        CarbonImmutable $through,
    ): array {
        $monthStart = $through->startOfMonth();
        $previousStart = $through->subMonthsNoOverflow(1)->startOfMonth();
        $previousEnd = $monthStart->subDay()->endOfDay();
        $historyStart = $through->subMonthsNoOverflow(self::PERIODS)->startOfMonth();

        $previousMonthExpenses = $windowExpenses->filter(
            fn (Transaction $tx): bool => $tx->occurred_at !== null
                && $tx->occurred_at->betweenIncluded($previousStart, $previousEnd)
        );

        $historyExpenses = $windowExpenses->filter(
            fn (Transaction $tx): bool => $tx->occurred_at !== null
                && $tx->occurred_at->betweenIncluded($historyStart, $previousEnd)
        );

        $current = $this->categoryTotalsFrom($currentMonthExpenses);
        $previous = $this->categoryTotalsFrom($previousMonthExpenses);
        $history = $this->categoryHistoryFrom($historyExpenses);

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
     * Category totals for a collection of transactions, keyed by category and currency.
     *
     * @param  Collection<int, Transaction>  $transactions
     * @return array<string, array{category: string, currency: string, total: float}>
     */
    private function categoryTotalsFrom(Collection $transactions): array
    {
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
     * @param  Collection<int, Transaction>  $transactions
     * @return array<string, array{averages: array<int, float>}>
     */
    private function categoryHistoryFrom(Collection $transactions): array
    {
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
     * @param  Collection<int, Transaction>  $currentMonthExpenses
     * @return array<string, mixed>|null
     */
    private function largestExpenseFrom(Collection $currentMonthExpenses): ?array
    {
        /** @var Transaction|null $transaction */
        $transaction = $currentMonthExpenses
            ->sortByDesc(fn (Transaction $tx): float => (float) $tx->base_amount)
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
     * Base query for expenses belonging to a user.
     *
     * Rows are excluded unless their snapshot was taken into the base currency
     * currently in effect. A row with no snapshot has no converted amount at
     * all, and a row whose base_currency differs was converted at a time when
     * a different base applied; including either would add numbers
     * denominated in different currencies together.
     */
    private function expenseQuery(int $userId, ?string $baseCurrency = null): Builder
    {
        if ($baseCurrency === null) {
            $user = User::find($userId);
            $baseCurrency = strtoupper((string) ($user?->currency ?? config('finance.base_currency')));
        }

        return Transaction::query()
            ->where('user_id', $userId)
            ->where('type', TransactionType::Expense)
            ->whereNotNull('base_amount')
            ->where('base_currency', $baseCurrency);
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
     * @param  Collection<int, Transaction>|null  $preloadedMonthExpenses
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
     *         status: 'safe'|'warning'|'exceeded',
     *         is_recurring: bool
     *     }>
     * }
     */
    public function budgetOverview(
        int $userId,
        ?CarbonImmutable $through = null,
        ?User $user = null,
        ?Collection $preloadedMonthExpenses = null,
    ): array {
        $through ??= CarbonImmutable::now();
        $periodMonth = $through->format('Y-m');
        $user ??= User::find($userId);
        $baseCurrency = strtoupper((string) ($user?->currency ?? config('finance.base_currency')));

        $budgets = $this->recurringBudgets->ensureAndListForUserMonth($userId, $periodMonth, $user);

        if ($preloadedMonthExpenses !== null) {
            $transactions = $preloadedMonthExpenses;
        } else {
            $monthStart = $through->startOfMonth();
            $monthEnd = $through->endOfMonth();

            $transactions = $this->expenseQuery($userId, $baseCurrency)
                ->with('splits')
                ->whereBetween('occurred_at', [$monthStart, $monthEnd])
                ->get();
        }

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
                'is_recurring' => (bool) $budget->is_recurring,
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
