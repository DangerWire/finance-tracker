<?php

namespace App\Services;

use App\Models\Transaction;

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
        return Transaction::query()
            ->where('user_id', $userId)
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->map(fn (string $category): string => trim($category))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
