<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\TransactionSplit;

/**
 * Resolves the categories a user has actually used.
 *
 * Categories are free text rather than a lookup table, so this is what makes
 * a category typed into one transaction appear in the dropdowns everywhere
 * else without any seeding step.
 */
class CategoryService
{
    /**
     * Get the user's distinct categories, alphabetically.
     *
     * @return array<int, string>
     */
    public function forUser(int $userId): array
    {
        $transactionCategories = Transaction::query()
            ->where('user_id', $userId)
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->pluck('category');

        $splitCategories = TransactionSplit::query()
            ->whereHas('transaction', fn ($query) => $query->where('user_id', $userId))
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->pluck('category');

        return $transactionCategories
            ->merge($splitCategories)
            ->map(fn (string $category): string => trim($category))
            ->reject(fn (string $category): bool => strtolower($category) === 'split')
            ->filter()
            ->unique()
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }
}
