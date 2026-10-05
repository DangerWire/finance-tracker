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
     * @return array<string, mixed>
     */
    public function forUser(int $userId, ?CarbonImmutable $through = null): array
    {
        $through ??= CarbonImmutable::now();

        $baseCurrency = config('finance.base_currency');

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

        $categories = $this->categoryBreakdown($userId, $through);

        return [
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
            'categories' => $categories,
            'top_categories' => array_slice($categories, 0, 3),
            'largest_expense' => $this->largestExpense($userId),
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

        $current = $this->categoryTotalsBetween($userId, $monthStart, $through);
        $previous = $this->categoryTotalsBetween($userId, $previousStart, $monthStart->subDay());

        $history = $this->categoryHistory($userId, $through);

        $rows = [];

        foreach ($current as $category => $total) {
            $previousTotal = $previous[$category] ?? 0.0;
            $averages = $history[$category] ?? [];

            $rows[] = [
                'category' => $category,
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

        return array_map(function (array $row) use ($grandTotal): array {
            $row['share_of_spend_percent'] = $grandTotal > 0
                ? round(($row['total'] / $grandTotal) * 100, 1)
                : 0.0;

            return $row;
        }, $rows);
    }

    /**
     * Category totals for a single month.
     *
     * @return array<string, float>
     */
    private function categoryTotalsBetween(int $userId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->expenseQuery($userId)
            ->whereBetween('occurred_at', [$from, $to])
            ->get(['category', 'base_amount'])
            ->filter(fn (Transaction $transaction): bool => $transaction->category !== null)
            ->groupBy('category')
            ->map(fn (Collection $group): float => (float) $group->sum('base_amount'))
            ->all();
    }

    /**
     * Monthly category totals across the compared window, used for averages.
     *
     * @return array<string, array<int, float>>
     */
    private function categoryHistory(int $userId, CarbonImmutable $through): array
    {
        // The current month is excluded from the baseline: comparing this
        // month's spend against an average that already includes it would
        // suppress the very spikes the check is meant to catch.
        $start = $through->subMonthsNoOverflow(self::PERIODS)->startOfMonth();

        return $this->expenseQuery($userId)
            ->whereBetween('occurred_at', [$start, $through->startOfMonth()->subDay()])
            ->get(['category', 'base_amount', 'occurred_at'])
            ->filter(fn (Transaction $transaction): bool => $transaction->category !== null)
            ->groupBy('category')
            ->map(fn (Collection $group): array => $group
                ->groupBy(fn (Transaction $transaction): string => $transaction->occurred_at->format('Y-m'))
                ->map(fn (Collection $month): float => (float) $month->sum('base_amount'))
                ->values()
                ->all())
            ->all();
    }

    /**
     * The single largest expense in the current month.
     *
     * @return array<string, mixed>|null
     */
    private function largestExpense(int $userId): ?array
    {
        $transaction = $this->expenseQuery($userId)
            ->where('occurred_at', '>=', CarbonImmutable::now()->startOfMonth())
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
            ->where('occurred_at', '>=', $through->startOfMonth())
            ->count();
    }

    /**
     * Base query for converted expenses belonging to a user.
     */
    private function expenseQuery(int $userId): Builder
    {
        return Transaction::query()
            ->where('user_id', $userId)
            ->where('type', TransactionType::Expense)
            ->whereNotNull('base_amount');
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
