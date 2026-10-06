<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SpendingStatsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpendingStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sums_categories_for_the_current_month(): void
    {
        $user = User::factory()->create();

        $this->expense($user, 100, 'food', '2026-10-02');
        $this->expense($user, 50, 'food', '2026-10-03');
        $this->expense($user, 25, 'transport', '2026-10-04');

        $stats = app(SpendingStatsService::class)->forUser($user->id, CarbonImmutable::parse('2026-10-15'));

        $categories = collect($stats['categories'])->keyBy('category');

        $this->assertSame(150.0, $categories['food']['total']);
        $this->assertSame(25.0, $categories['transport']['total']);
        $this->assertSame(175.0, $stats['current_period']['expenses']);
    }

    public function test_it_computes_category_share_of_spend(): void
    {
        $user = User::factory()->create();

        $this->expense($user, 300, 'housing', '2026-10-02');
        $this->expense($user, 100, 'food', '2026-10-03');

        $stats = app(SpendingStatsService::class)->forUser($user->id, CarbonImmutable::parse('2026-10-15'));

        $categories = collect($stats['categories'])->keyBy('category');

        $this->assertSame(75.0, $categories['housing']['share_of_spend_percent']);
        $this->assertSame(25.0, $categories['food']['share_of_spend_percent']);
    }

    public function test_it_computes_month_over_month_change(): void
    {
        $user = User::factory()->create();

        $this->expense($user, 100, 'food', '2026-09-10');
        $this->expense($user, 150, 'food', '2026-10-10');

        $stats = app(SpendingStatsService::class)->forUser($user->id, CarbonImmutable::parse('2026-10-15'));

        $this->assertSame(150.0, $stats['current_period']['expenses']);
        $this->assertSame(100.0, $stats['previous_period']['expenses']);
        $this->assertSame(50.0, $stats['previous_period']['change_percent']);
    }

    public function test_change_percent_is_null_when_there_is_no_baseline(): void
    {
        $user = User::factory()->create();

        $this->expense($user, 150, 'food', '2026-10-10');

        $stats = app(SpendingStatsService::class)->forUser($user->id, CarbonImmutable::parse('2026-10-15'));

        $this->assertNull($stats['previous_period']['change_percent']);
    }

    public function test_it_flags_spending_above_twice_the_category_average(): void
    {
        $user = User::factory()->create();

        // Establish a small baseline for this category.
        $this->expense($user, 100, 'food', '2026-06-05');
        $this->expense($user, 100, 'food', '2026-07-05');
        $this->expense($user, 100, 'food', '2026-08-05');
        $this->expense($user, 100, 'food', '2026-09-05');
        $this->expense($user, 900, 'food', '2026-10-05');

        $stats = app(SpendingStatsService::class)->forUser($user->id, CarbonImmutable::parse('2026-10-15'));

        $food = collect($stats['categories'])->firstWhere('category', 'food');

        $this->assertTrue($food['is_unusual']);
        $this->assertSame(900.0, $food['total']);
        $this->assertSame(100.0, $food['average_total']);
    }

    public function test_income_is_excluded_from_spending_statistics(): void
    {
        $user = User::factory()->create();

        Transaction::factory()->for($user)->create([
            'type' => TransactionType::Income,
            'amount' => 100000,
            'base_amount' => 100000,
            'occurred_at' => '2026-10-02 10:00:00',
        ]);

        $this->expense($user, 100, 'food', '2026-10-03');

        $stats = app(SpendingStatsService::class)->forUser($user->id, CarbonImmutable::parse('2026-10-15'));

        $this->assertSame(100.0, $stats['current_period']['expenses']);
    }

    public function test_it_excludes_other_users_spending(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->expense($user, 100, 'food', '2026-10-03');
        $this->expense($other, 9999, 'food', '2026-10-03');

        $stats = app(SpendingStatsService::class)->forUser($user->id, CarbonImmutable::parse('2026-10-15'));

        $this->assertSame(100.0, $stats['current_period']['expenses']);
        $this->assertSame(1, $stats['transaction_count']);
    }

    public function test_it_excludes_unconverted_transactions(): void
    {
        $user = User::factory()->create();

        $this->expense($user, 100, 'food', '2026-10-03');

        // Saved without the factory's base snapshot to represent a row whose
        // rate could not be resolved.
        $unconverted = Transaction::factory()->for($user)->create([
            'type' => TransactionType::Expense,
            'amount' => 500,
            'occurred_at' => '2026-10-04 10:00:00',
        ]);
        $unconverted->forceFill(['base_amount' => null, 'base_currency' => null])->save();

        $stats = app(SpendingStatsService::class)->forUser($user->id, CarbonImmutable::parse('2026-10-15'));

        $this->assertSame(100.0, $stats['current_period']['expenses']);
    }

    public function test_it_excludes_transactions_converted_into_a_retired_base_currency(): void
    {
        config(['finance.base_currency' => 'CNY']);

        $user = User::factory()->create();

        $this->expense($user, 100, 'food', '2026-10-03');

        // A row converted while IDR was the base currency still has a base
        // amount, so a null check alone would let it into a CNY total.
        $stale = Transaction::factory()->for($user)->create([
            'type' => TransactionType::Expense,
            'amount' => 50,
            'currency' => 'IDR',
            'base_amount' => 133750,
            'base_currency' => 'IDR',
            'applied_rate' => 2675,
            'category' => 'food',
            'occurred_at' => '2026-10-04 10:00:00',
        ]);

        $stats = app(SpendingStatsService::class)->forUser($user->id, CarbonImmutable::parse('2026-10-15'));

        $this->assertSame(100.0, $stats['current_period']['expenses']);
        $this->assertCount(1, $stats['categories']);
        $this->assertSame(1, $stats['transaction_count']);
        $this->assertNotSame(133750.0, $stats['largest_expense']['amount']);
    }

    public function test_it_includes_a_transaction_recorded_later_today(): void
    {
        $user = User::factory()->create();

        // The query runs at the start of the day, but the user records an
        // expense at 20:00. Bounding the period at "now" would drop it, which
        // is what silently hid categories recorded in the evening.
        $this->expense($user, 100, 'utang', '2026-10-06');

        $stats = app(SpendingStatsService::class)->forUser(
            $user->id,
            CarbonImmutable::parse('2026-10-06 00:00:00'),
        );

        $categories = collect($stats['categories'])->keyBy('category');

        $this->assertTrue($categories->has('utang'));
        $this->assertSame(100.0, $categories['utang']['total']);
        $this->assertSame(100.0, $stats['current_period']['expenses']);
    }

    public function test_category_totals_agree_with_the_headline_total(): void
    {
        $user = User::factory()->create();

        $this->expense($user, 100, 'food', '2026-10-06');
        $this->expense($user, 250, 'housing', '2026-10-06');
        $this->expense($user, 25, 'transport', '2026-10-02');

        $stats = app(SpendingStatsService::class)->forUser(
            $user->id,
            CarbonImmutable::parse('2026-10-06 09:00:00'),
        );

        // A breakdown that does not add up to the headline figure is the
        // symptom of the two using different period boundaries.
        $this->assertSame(
            $stats['current_period']['expenses'],
            round(array_sum(array_column($stats['categories'], 'total')), 2),
        );
    }

    public function test_it_keeps_the_whole_final_day_of_the_previous_month(): void
    {
        $user = User::factory()->create();

        // Recorded at midday on the last day of September.
        $this->expense($user, 75, 'food', '2026-09-30');

        // The category has to appear this month too, because the breakdown is
        // built from the current month and carries the previous total on it.
        $this->expense($user, 50, 'food', '2026-10-02');

        $stats = app(SpendingStatsService::class)->forUser($user->id, CarbonImmutable::parse('2026-10-15'));

        $food = collect($stats['categories'])->firstWhere('category', 'food');

        // Bounding the previous month at subDay() would cut this off at
        // midnight and report the month as 35 short.
        $this->assertSame(75.0, $food['previous_total']);
    }

    public function test_it_flags_a_partial_month(): void
    {
        $user = User::factory()->create();

        $this->expense($user, 100, 'food', '2026-10-03');

        $partial = app(SpendingStatsService::class)->forUser($user->id, CarbonImmutable::parse('2026-10-06'));
        $complete = app(SpendingStatsService::class)->forUser($user->id, CarbonImmutable::parse('2026-09-30'));

        $this->assertTrue($partial['current_period']['is_partial']);
        $this->assertSame(6, $partial['current_period']['day_of_month']);
        $this->assertFalse($complete['current_period']['is_partial']);
    }

    public function test_it_reports_the_largest_expense(): void
    {
        $user = User::factory()->create();

        $this->expense($user, 100, 'food', '2026-10-03');
        $this->expense($user, 850, 'housing', '2026-10-09');

        $stats = app(SpendingStatsService::class)->forUser($user->id, CarbonImmutable::parse('2026-10-15'));

        $this->assertSame(850.0, $stats['largest_expense']['amount']);
        $this->assertSame('housing', $stats['largest_expense']['category']);
    }

    public function test_it_handles_a_user_with_no_expenses(): void
    {
        $user = User::factory()->create();

        $stats = app(SpendingStatsService::class)->forUser($user->id, CarbonImmutable::parse('2026-10-15'));

        $this->assertSame(0.0, $stats['current_period']['expenses']);
        $this->assertSame([], $stats['categories']);
        $this->assertNull($stats['largest_expense']);
    }

    /**
     * Records an expense already converted into the configured base currency.
     */
    private function expense(User $user, float $amount, string $category, string $date): Transaction
    {
        $baseCurrency = config('finance.base_currency');

        return Transaction::factory()->for($user)->create([
            'type' => TransactionType::Expense,
            'amount' => $amount,
            'currency' => $baseCurrency,
            'base_amount' => $amount,
            'base_currency' => $baseCurrency,
            'applied_rate' => 1,
            'category' => $category,
            'occurred_at' => $date.' 12:00:00',
        ]);
    }
}
