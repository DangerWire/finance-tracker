<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Budget>
 */
class BudgetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category' => fake()->randomElement(['Groceries', 'Dining Out', 'Utilities', 'Transportation', 'Entertainment']),
            'amount' => fake()->randomFloat(2, 200, 5000),
            'period_month' => now()->format('Y-m'),
        ];
    }
}
