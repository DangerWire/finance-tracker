<?php

namespace Database\Factories;

use App\Enums\DebtStatus;
use App\Models\Debt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Debt>
 */
class DebtFactory extends Factory
{
    protected $model = Debt::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $creator = User::factory();
        $borrower = User::factory();

        return [
            'creator_id' => $creator,
            'lender_id' => $creator,
            'borrower_id' => $borrower,
            'amount' => $this->faker->randomFloat(2, 10, 500),
            'currency' => config('finance.base_currency', 'CNY'),
            'note' => $this->faker->optional()->sentence(),
            'due_date' => $this->faker->optional()->dateTimeBetween('+1 week', '+3 months')?->format('Y-m-d'),
            'status' => DebtStatus::PendingAcceptance,
        ];
    }

    /**
     * Indicate that the debt is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DebtStatus::Active,
            'accepted_at' => now(),
        ]);
    }

    /**
     * Indicate that payment was submitted.
     */
    public function paymentSubmitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DebtStatus::PaymentSubmitted,
            'accepted_at' => now()->subDay(),
            'payment_submitted_at' => now(),
        ]);
    }

    /**
     * Indicate that the debt is settled.
     */
    public function settled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DebtStatus::Settled,
            'accepted_at' => now()->subDays(2),
            'payment_submitted_at' => now()->subDay(),
            'settled_at' => now(),
        ]);
    }
}
