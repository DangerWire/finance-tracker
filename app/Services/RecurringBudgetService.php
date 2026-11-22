<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

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
    public function ensureForUserMonth(int $userId, string $targetMonth): int
    {
        $priorBudgets = Budget::query()
            ->where('user_id', $userId)
            ->where('period_month', '<', $targetMonth)
            ->orderByDesc('period_month')
            ->orderByDesc('id')
            ->get();

        if ($priorBudgets->isEmpty()) {
            return 0;
        }

        /** @var array<string, Budget> $latestByCategory */
        $latestByCategory = [];

        foreach ($priorBudgets as $budget) {
            if (! isset($latestByCategory[$budget->category])) {
                $latestByCategory[$budget->category] = $budget;
            }
        }

        $recurringToCarry = array_filter(
            $latestByCategory,
            fn (Budget $budget): bool => (bool) $budget->is_recurring
        );

        if ($recurringToCarry === []) {
            return 0;
        }

        $user = User::find($userId);

        if ($user === null) {
            return 0;
        }

        $existingCategories = array_flip(
            $user->budgets()
                ->where('period_month', $targetMonth)
                ->pluck('category')
                ->all()
        );

        $created = 0;

        foreach ($recurringToCarry as $latest) {
            if (isset($existingCategories[$latest->category])) {
                continue;
            }

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

                if ($budget->wasRecentlyCreated) {
                    $created++;
                }
            } catch (UniqueConstraintViolationException) {
                // Another concurrent request created this month's budget row first.
            }
        }

        return $created;
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
