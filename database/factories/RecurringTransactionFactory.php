<?php

namespace Database\Factories;

use App\Enums\RecurringFrequency;
use App\Enums\TransactionType;
use App\Models\RecurringTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringTransaction>
 */
class RecurringTransactionFactory extends Factory
{
    public function definition(): array
    {
        $startsOn = now()->startOfMonth();

        return [
            'user_id' => User::factory(),
            'type' => TransactionType::Expense,
            'name' => 'Monthly subscription',
            'amount' => 25.00,
            'currency' => 'CNY',
            'category' => 'subscriptions',
            'note' => null,
            'frequency' => RecurringFrequency::Monthly,
            'starts_on' => $startsOn,
            'ends_on' => null,
            'next_occurrence_on' => $startsOn,
            'last_occurrence_on' => null,
            'auto_post' => true,
            'remind_days_before' => null,
            'last_reminded_occurrence_on' => null,
            'is_active' => true,
        ];
    }

    /**
     * A template that posts itself without any prompting.
     */
    public function autoPost(): static
    {
        return $this->state(fn (): array => ['auto_post' => true]);
    }

    /**
     * A template that only reminds, never posting on its own.
     */
    public function reminderOnly(): static
    {
        return $this->state(fn (): array => ['auto_post' => false]);
    }

    /**
     * A template paused by the user.
     */
    public function paused(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    /**
     * A template that asks for a reminder this many days ahead.
     */
    public function reminds(int $daysBefore = 3): static
    {
        return $this->state(fn (): array => ['remind_days_before' => $daysBefore]);
    }

    /**
     * A template anchored on a given start date.
     */
    public function starting(string $date): static
    {
        return $this->state(fn (): array => [
            'starts_on' => $date,
            'next_occurrence_on' => $date,
        ]);
    }
}
