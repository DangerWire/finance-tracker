<?php

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
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
            'amount' => $this->faker->randomFloat(2, 1, 500),
            'type' => $this->faker->randomElement(TransactionType::cases()),
            'currency' => 'CNY',
            'occurred_at' => now()->subDays($this->faker->numberBetween(0, 60)),
            'category' => $this->faker->randomElement(['food', 'transport', 'housing', 'entertainment', 'utilities']),
            'note' => $this->faker->optional()->sentence(),
        ];
    }
}
