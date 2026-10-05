<?php

namespace Database\Seeders;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Generates realistic multi-month spending for the demo user.
 *
 * The figures are expressed in the base currency with a rate of 1, because
 * these rows stand in for local spending rather than converted amounts.
 */
class SpendingDemoSeeder extends Seeder
{
    /**
     * Recurring monthly commitments in the base currency.
     *
     * @var array<string, array{int|float, array<int, int>}>
     */
    private const RECURRING = [
        'housing' => [2800, [1, 2]],
        'utilities' => [420, [8, 9]],
        'transport' => [260, [3, 26]],
    ];

    /**
     * Variable day-to-day spending.
     *
     * @var array<int, array{0: string, 1: float, 2: float, 3: array<int, string>}>
     */
    private const VARIABLE = [
        ['food', 18, 65, ['Lunch near the office', 'Groceries at the wet market', 'Dinner with friends', 'Breakfast', 'Coffee and a pastry', 'Home-cooked lunch']],
        ['transport', 8, 45, ['Metro card top-up', 'Didi to the office', 'Bike repair']],
        ['entertainment', 25, 120, ['Cinema tickets', 'Weekend trip', 'Concert', 'Streaming subscription']],
        ['utilities', 30, 90, ['Phone plan', 'Electricity top-up', 'Water bill']],
        ['health', 40, 200, ['Pharmacy', 'Dental check-up', 'Gym membership']],
        ['shopping', 50, 400, ['T-shirts', 'Household supplies', 'Headphones', 'Shoes']],
    ];

    /**
     * Recurring monthly income in the base currency.
     *
     * @var array<int, array{0: int, 1: float, 2: string}>
     */
    private const INCOME = [
        [25, 9500, 'Salary'],
        [27, 600, 'Freelance writing'],
    ];

    public function run(): void
    {
        $user = User::where('email', 'test@example.com')->first();

        if ($user === null) {
            $user = User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }

        $this->command?->info('Clearing previous demo transactions…');
        Transaction::query()->where('user_id', $user->id)->delete();

        $created = $this->seedMonths($user, 4);

        $this->command?->info("Created {$created} demo transactions.");
    }

    private function seedMonths(User $user, int $months): int
    {
        $count = 0;
        $today = CarbonImmutable::now();

        // Walk backwards from the current month so the newest month is
        // included; offset 0 is always the month we are living in.
        $start = $today->startOfMonth()->subMonthsNoOverflow($months - 1);

        foreach (range(0, $months - 1) as $offset) {
            $month = $start->addMonthsNoOverflow($offset);
            $daysInMonth = $month->daysInMonth;

            // Only generate through today for the current month.
            $lastDay = $month->format('Y-m') === $today->format('Y-m')
                ? $today->day
                : $daysInMonth;

            foreach (self::RECURRING as $category => [$amount, $days]) {
                foreach ($days as $day) {
                    if ($day > $lastDay) {
                        continue;
                    }

                    $this->create($user, TransactionType::Expense, $amount, $category, $month, $day);
                    $count++;
                }
            }

            foreach (self::VARIABLE as [$category, $min, $max, $notes]) {
                $times = random_int(3, 7);

                for ($i = 0; $i < $times; $i++) {
                    $day = random_int(1, $lastDay);

                    $this->create(
                        $user,
                        TransactionType::Expense,
                        random_int((int) $min * 100, (int) $max * 100) / 100,
                        $category,
                        $month,
                        $day,
                        $notes[array_rand($notes)],
                    );
                    $count++;
                }
            }

            foreach (self::INCOME as [$day, $amount, $note]) {
                if ($day > $lastDay) {
                    continue;
                }

                $this->create($user, TransactionType::Income, $amount, 'salary', $month, $day, $note);
                $count++;
            }
        }

        return $count;
    }

    private function create(
        User $user,
        TransactionType $type,
        float $amount,
        string $category,
        CarbonImmutable $month,
        int $day,
        ?string $note = null,
    ): void {
        $occurredAt = $month->day(min($day, $month->daysInMonth))->setTime(12, 0);

        Transaction::create([
            'user_id' => $user->id,
            'type' => $type,
            'amount' => $amount,
            'currency' => config('finance.base_currency'),
            'base_amount' => $amount,
            'base_currency' => config('finance.base_currency'),
            'applied_rate' => 1,
            'occurred_at' => $occurredAt,
            'category' => $category,
            'note' => $note,
        ]);
    }
}
