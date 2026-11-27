<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;

/**
 * Carries recurring monthly budgets forward into newer months.
 *
 * For each category, only the most recent budget strictly prior to the target
 * month decides whether recurrence is active and what amount to carry forward.
 * That preserves historical months when a user edits a future limit, and stops
 * recurrence cleanly as soon as a user turns it off on a later month.
 */
class RecurringBudgetService
{
    /**
     * Ensure all active recurring budgets from earlier months exist for a user in the target month (YYYY-MM).
     */
    public function ensureForUserMonth(int $userId, string $targetMonth, ?User $user = null): int
    {
        return $this->syncForUserMonth($userId, $targetMonth, $user)['created'];
    }

    /**
     * Ensure recurring budgets exist for the target month and return the month's budgets sorted by category
     * using a single read query in the common steady state.
     *
     * @return Collection<int, Budget>
     */
    public function ensureAndListForUserMonth(int $userId, string $targetMonth, ?User $user = null): Collection
    {
        return $this->syncForUserMonth($userId, $targetMonth, $user)['budgets'];
    }

    /**
     * Load budgets up to $targetMonth once, materialize missing recurring rows, and return both count and list.
     *
     * @return array{created: int, budgets: Collection<int, Budget>}
     */
    private function syncForUserMonth(int $userId, string $targetMonth, ?User $user = null): array
    {
        $allBudgets = Budget::query()
            ->where('user_id', $userId)
            ->where('period_month', '<=', $targetMonth)
            ->orderByDesc('period_month')
            ->orderByDesc('id')
            ->get();

        /** @var Collection<int, Budget> $currentBudgets */
        $currentBudgets = $allBudgets
            ->filter(fn (Budget $budget): bool => $budget->period_month === $targetMonth)
            ->values();

        /** @var array<string, Budget> $latestPriorByCategory */
        $latestPriorByCategory = [];

        foreach ($allBudgets as $budget) {
            if ($budget->period_month >= $targetMonth) {
                continue;
            }

            if (! isset($latestPriorByCategory[$budget->category])) {
                $latestPriorByCategory[$budget->category] = $budget;
            }
        }

        $recurringToCarry = array_filter(
            $latestPriorByCategory,
            fn (Budget $budget): bool => (bool) $budget->is_recurring
        );

        if ($recurringToCarry === []) {
            return [
                'created' => 0,
                'budgets' => $currentBudgets->sortBy('category')->values(),
            ];
        }

        $existingCategories = array_flip($currentBudgets->pluck('category')->all());
        $missing = array_filter(
            $recurringToCarry,
            fn (Budget $latest): bool => ! isset($existingCategories[$latest->category])
        );

        if ($missing === []) {
            return [
                'created' => 0,
                'budgets' => $currentBudgets->sortBy('category')->values(),
            ];
        }

        $user ??= User::find($userId);

        if ($user === null) {
            return [
                'created' => 0,
                'budgets' => $currentBudgets->sortBy('category')->values(),
            ];
        }

        $created = 0;

        foreach ($missing as $latest) {
            try {
                $budget = $user->budgets()->firstOrCreate(
                    [
                        'category' => $latest->category,
                        'period_month' => $targetMonth,
                    ],
                    [
                        'amount' => $latest->amount,
                        'is_recurring' => true,
                    ]
                );

                $currentBudgets->push($budget);

                if ($budget->wasRecentlyCreated) {
                    $created++;
                }
            } catch (UniqueConstraintViolationException) {
                // Another concurrent request created this month's budget row first.
                $existing = $user->budgets()
                    ->where('category', $latest->category)
                    ->where('period_month', $targetMonth)
                    ->first();

                if ($existing !== null) {
                    $currentBudgets->push($existing);
                }
            }
        }

        return [
            'created' => $created,
            'budgets' => $currentBudgets->unique('id')->sortBy('category')->values(),
        ];
    }

    /**
     * Materialize recurring budgets for every user who has active recurring budgets prior to $targetMonth.
     */
    public function ensureForAllUsersMonth(string $targetMonth): int
    {
        $userIds = Budget::query()
            ->where('is_recurring', true)
            ->where('period_month', '<', $targetMonth)
            ->distinct()
            ->pluck('user_id');

        $totalCreated = 0;

        foreach ($userIds as $userId) {
            $totalCreated += $this->ensureForUserMonth((int) $userId, $targetMonth);
        }

        return $totalCreated;
    }
}
