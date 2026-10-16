<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\TransactionSplit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransactionSplit>
 */
class TransactionSplitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 10, 200);

        return [
            'transaction_id' => Transaction::factory(),
            'category' => fake()->randomElement(['Groceries', 'Utilities', 'Snacks', 'Household']),
            'amount' => $amount,
            'base_amount' => $amount,
            'note' => fake()->sentence(3),
        ];
    }
}
