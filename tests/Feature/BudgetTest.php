<?php

namespace Tests\Feature;

use App\Actions\ConvertTransactionAmount;
use App\Enums\TransactionType;
use App\Models\Budget;
use App\Models\ExchangeRate;
use App\Models\User;
use App\Services\SpendingStatsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_budgets_page(): void
    {
        $user = User::factory()->create();

        $budget = Budget::factory()->create([
            'user_id' => $user->id,
            'category' => 'Groceries',
            'amount' => 1500.00,
            'period_month' => now()->format('Y-m'),
        ]);

        $this->actingAs($user)
            ->get(route('budgets.index'))
            ->assertOk()
            ->assertSee('Groceries')
            ->assertSee('1,500.00');
    }

    public function test_user_can_create_a_budget(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('budgets.store'), [
            'category' => 'Utilities',
            'amount' => 350.00,
            'period_month' => '2026-10',
        ]);

        $response->assertRedirect(route('budgets.index', ['month' => '2026-10']));

        $this->assertDatabaseHas('budgets', [
            'user_id' => $user->id,
            'category' => 'Utilities',
            'amount' => 350.00,
            'period_month' => '2026-10',
        ]);
    }

    public function test_user_cannot_create_duplicate_budget_for_same_category_and_month(): void
    {
        $user = User::factory()->create();

        Budget::factory()->create([
            'user_id' => $user->id,
            'category' => 'Dining Out',
            'amount' => 500.00,
            'period_month' => '2026-10',
        ]);

        $response = $this->actingAs($user)->post(route('budgets.store'), [
            'category' => 'Dining Out',
            'amount' => 800.00,
            'period_month' => '2026-10',
        ]);

        $response->assertSessionHasErrors('category');
    }

    public function test_user_can_update_their_budget(): void
    {
        $user = User::factory()->create();

        $budget = Budget::factory()->create([
            'user_id' => $user->id,
            'category' => 'Transport',
            'amount' => 200.00,
            'period_month' => '2026-10',
        ]);

        $response = $this->actingAs($user)->put(route('budgets.update', $budget), [
            'category' => 'Transport',
            'amount' => 300.00,
            'period_month' => '2026-10',
        ]);

        $response->assertRedirect(route('budgets.index', ['month' => '2026-10']));

        $this->assertDatabaseHas('budgets', [
            'id' => $budget->id,
            'amount' => 300.00,
        ]);
    }

    public function test_user_cannot_modify_another_users_budget(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        $budget = Budget::factory()->create([
            'user_id' => $owner->id,
            'category' => 'Secret',
            'amount' => 1000.00,
            'period_month' => '2026-10',
        ]);

        $this->actingAs($stranger)
            ->put(route('budgets.update', $budget), [
                'category' => 'Secret',
                'amount' => 50.00,
                'period_month' => '2026-10',
            ])
            ->assertForbidden();

        $this->actingAs($stranger)
            ->delete(route('budgets.destroy', $budget))
            ->assertForbidden();
    }

    public function test_user_can_delete_their_budget(): void
    {
        $user = User::factory()->create();

        $budget = Budget::factory()->create([
            'user_id' => $user->id,
            'category' => 'Books',
            'amount' => 100.00,
            'period_month' => '2026-10',
        ]);

        $response = $this->actingAs($user)->delete(route('budgets.destroy', $budget));

        $response->assertRedirect(route('budgets.index', ['month' => '2026-10']));
        $this->assertDatabaseMissing('budgets', ['id' => $budget->id]);
    }

    public function test_budget_overview_computes_actual_spend_in_base_currency(): void
    {
        $user = User::factory()->create();
        $convert = app(ConvertTransactionAmount::class);

        // Base currency is CNY
        config(['finance.base_currency' => 'CNY']);

        // Cache exchange rate USD -> CNY on 2026-10-05
        ExchangeRate::create([
            'base_currency' => 'USD',
            'quote_currency' => 'CNY',
            'rate_date' => '2026-10-05',
            'rate' => 7.20000000,
        ]);

        // Budget of 1000 CNY for Groceries in 2026-10
        Budget::factory()->create([
            'user_id' => $user->id,
            'category' => 'Groceries',
            'amount' => 1000.00,
            'period_month' => '2026-10',
        ]);

        // Transaction 1: 300 CNY Groceries on 2026-10-02
        $user->transactions()->create($convert->apply([
            'type' => TransactionType::Expense,
            'amount' => 300.00,
            'currency' => 'CNY',
            'category' => 'Groceries',
            'occurred_at' => '2026-10-02 12:00:00',
        ]));

        // Transaction 2: 50 USD Groceries on 2026-10-05 (50 * 7.2 = 360 CNY)
        $user->transactions()->create($convert->apply([
            'type' => TransactionType::Expense,
            'amount' => 50.00,
            'currency' => 'USD',
            'category' => 'Groceries',
            'occurred_at' => '2026-10-05 14:00:00',
        ]));

        // Transaction 3: Income (should NOT count towards expenses)
        $user->transactions()->create($convert->apply([
            'type' => TransactionType::Income,
            'amount' => 5000.00,
            'currency' => 'CNY',
            'category' => 'Groceries',
            'occurred_at' => '2026-10-05 10:00:00',
        ]));

        $statsService = app(SpendingStatsService::class);
        $overview = $statsService->budgetOverview($user->id, CarbonImmutable::parse('2026-10-15'));

        $this->assertSame(1000.00, $overview['total_budget']);
        $this->assertSame(660.00, $overview['total_spent']); // 300 + 360
        $this->assertSame(340.00, $overview['total_remaining']);
        $this->assertSame(66.0, $overview['percentage_used']);
        $this->assertSame('safe', $overview['status']);

        $item = $overview['items'][0];
        $this->assertSame('Groceries', $item['category']);
        $this->assertSame(660.00, $item['spent_amount']);
        $this->assertSame(340.00, $item['remaining_amount']);
        $this->assertSame('safe', $item['status']);
    }

    public function test_budget_status_flags_warning_and_exceeded(): void
    {
        $user = User::factory()->create();
        $convert = app(ConvertTransactionAmount::class);
        config(['finance.base_currency' => 'CNY']);

        // Budget of 100 CNY
        Budget::factory()->create([
            'user_id' => $user->id,
            'category' => 'Coffee',
            'amount' => 100.00,
            'period_month' => '2026-10',
        ]);

        // Spend 80 CNY (80% -> warning)
        $tx = $user->transactions()->create($convert->apply([
            'type' => TransactionType::Expense,
            'amount' => 80.00,
            'currency' => 'CNY',
            'category' => 'Coffee',
            'occurred_at' => '2026-10-05 09:00:00',
        ]));

        $stats = app(SpendingStatsService::class);
        $overview = $stats->budgetOverview($user->id, CarbonImmutable::parse('2026-10-15'));
        $this->assertSame('warning', $overview['items'][0]['status']);

        // Spend another 30 CNY (total 110 CNY -> exceeded)
        $user->transactions()->create($convert->apply([
            'type' => TransactionType::Expense,
            'amount' => 30.00,
            'currency' => 'CNY',
            'category' => 'Coffee',
            'occurred_at' => '2026-10-06 09:00:00',
        ]));

        $overview2 = $stats->budgetOverview($user->id, CarbonImmutable::parse('2026-10-15'));
        $this->assertSame('exceeded', $overview2['items'][0]['status']);
    }

    public function test_recurring_budget_carries_forward_into_next_months_but_never_backward(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('budgets.store'), [
            'category' => 'Groceries',
            'amount' => 1000.00,
            'period_month' => '2026-11',
            'is_recurring' => '1',
        ])->assertRedirect(route('budgets.index', ['month' => '2026-11']));

        $this->assertDatabaseHas('budgets', [
            'user_id' => $user->id,
            'category' => 'Groceries',
            'period_month' => '2026-11',
            'is_recurring' => true,
        ]);

        // Viewing an earlier month (2026-10) must NOT back-populate
        $this->actingAs($user)
            ->get(route('budgets.index', ['month' => '2026-10']))
            ->assertOk()
            ->assertDontSee('Groceries');

        $this->assertDatabaseMissing('budgets', [
            'user_id' => $user->id,
            'category' => 'Groceries',
            'period_month' => '2026-10',
        ]);

        // Viewing next month (2026-12) automatically carries forward the recurring budget
        $this->actingAs($user)
            ->get(route('budgets.index', ['month' => '2026-12']))
            ->assertOk()
            ->assertSee('Groceries')
            ->assertSee('1,000.00')
            ->assertSee('Recurring');

        $this->assertDatabaseHas('budgets', [
            'user_id' => $user->id,
            'category' => 'Groceries',
            'amount' => 1000.00,
            'period_month' => '2026-12',
            'is_recurring' => true,
        ]);
    }

    public function test_editing_recurring_budget_amount_preserves_history_and_carries_new_amount_forward(): void
    {
        $user = User::factory()->create();

        Budget::factory()->recurring()->create([
            'user_id' => $user->id,
            'category' => 'Rent',
            'amount' => 2000.00,
            'period_month' => '2026-11',
        ]);

        // Materialize 2026-12 first
        $this->actingAs($user)->get(route('budgets.index', ['month' => '2026-12']))->assertOk();

        $decemberBudget = $user->budgets()->where('period_month', '2026-12')->firstOrFail();

        // Update 2026-12 amount to 2200 while keeping recurring enabled
        $this->actingAs($user)->put(route('budgets.update', $decemberBudget), [
            'category' => 'Rent',
            'amount' => 2200.00,
            'period_month' => '2026-12',
            'is_recurring' => '1',
        ])->assertRedirect(route('budgets.index', ['month' => '2026-12']));

        // Historical 2026-11 remains untouched at 2000
        $this->assertDatabaseHas('budgets', [
            'user_id' => $user->id,
            'category' => 'Rent',
            'period_month' => '2026-11',
            'amount' => 2000.00,
        ]);

        // Next month 2027-01 inherits the updated 2200 amount
        $this->actingAs($user)
            ->get(route('budgets.index', ['month' => '2027-01']))
            ->assertOk()
            ->assertSee('2,200.00');

        $this->assertDatabaseHas('budgets', [
            'user_id' => $user->id,
            'category' => 'Rent',
            'period_month' => '2027-01',
            'amount' => 2200.00,
            'is_recurring' => true,
        ]);
    }

    public function test_disabling_recurrence_stops_future_month_generation_while_keeping_current_month(): void
    {
        $user = User::factory()->create();

        Budget::factory()->recurring()->create([
            'user_id' => $user->id,
            'category' => 'Gym',
            'amount' => 300.00,
            'period_month' => '2026-11',
        ]);

        // Materialize 2026-12
        $this->actingAs($user)->get(route('budgets.index', ['month' => '2026-12']))->assertOk();
        $decemberBudget = $user->budgets()->where('period_month', '2026-12')->firstOrFail();

        // Turn off recurrence on 2026-12
        $this->actingAs($user)->put(route('budgets.update', $decemberBudget), [
            'category' => 'Gym',
            'amount' => 300.00,
            'period_month' => '2026-12',
            'is_recurring' => '0',
        ])->assertRedirect(route('budgets.index', ['month' => '2026-12']));

        // 2026-12 still exists, but 2027-01 must NOT receive Gym
        $this->actingAs($user)
            ->get(route('budgets.index', ['month' => '2027-01']))
            ->assertOk()
            ->assertDontSee('Gym');

        $this->assertDatabaseMissing('budgets', [
            'user_id' => $user->id,
            'category' => 'Gym',
            'period_month' => '2027-01',
        ]);
    }

    public function test_deleting_recurring_budget_stops_resurrection_and_preserves_past_months(): void
    {
        $user = User::factory()->create();

        Budget::factory()->recurring()->create([
            'user_id' => $user->id,
            'category' => 'Streaming',
            'amount' => 99.00,
            'period_month' => '2026-11',
        ]);

        // Materialize 2026-12 then delete it
        $this->actingAs($user)->get(route('budgets.index', ['month' => '2026-12']))->assertOk();
        $decemberBudget = $user->budgets()->where('period_month', '2026-12')->firstOrFail();

        $this->actingAs($user)
            ->delete(route('budgets.destroy', $decemberBudget))
            ->assertRedirect(route('budgets.index', ['month' => '2026-12']));

        // Reloading 2026-12 must NOT resurrect it, and 2026-11 history remains intact
        $this->actingAs($user)
            ->get(route('budgets.index', ['month' => '2026-12']))
            ->assertOk()
            ->assertDontSee('Streaming');

        $this->assertDatabaseMissing('budgets', [
            'user_id' => $user->id,
            'category' => 'Streaming',
            'period_month' => '2026-12',
        ]);

        $this->assertDatabaseHas('budgets', [
            'user_id' => $user->id,
            'category' => 'Streaming',
            'period_month' => '2026-11',
            'amount' => 99.00,
            'is_recurring' => false,
        ]);
    }

    public function test_generate_recurring_budgets_command_is_idempotent_and_rejects_invalid_month(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Budget::factory()->recurring()->create([
            'user_id' => $userA->id,
            'category' => 'Utilities',
            'amount' => 450.00,
            'period_month' => '2026-11',
        ]);

        Budget::factory()->create([
            'user_id' => $userB->id,
            'category' => 'OneOffTrip',
            'amount' => 1200.00,
            'period_month' => '2026-11',
            'is_recurring' => false,
        ]);

        // Invalid month rejected
        $this->artisan('finance:generate-recurring-budgets', ['--month' => 'invalid'])
            ->assertExitCode(1);

        // First run generates 1 recurring budget for 2026-12 (ignoring non-recurring OneOffTrip)
        $this->artisan('finance:generate-recurring-budgets', ['--month' => '2026-12'])
            ->expectsOutputToContain('Generated 1 recurring budget(s) for 2026-12.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('budgets', [
            'user_id' => $userA->id,
            'category' => 'Utilities',
            'period_month' => '2026-12',
            'amount' => 450.00,
            'is_recurring' => true,
        ]);
        $this->assertDatabaseMissing('budgets', [
            'user_id' => $userB->id,
            'category' => 'OneOffTrip',
            'period_month' => '2026-12',
        ]);

        // Second run is idempotent -> 0 newly created
        $this->artisan('finance:generate-recurring-budgets', ['--month' => '2026-12'])
            ->expectsOutputToContain('Generated 0 recurring budget(s) for 2026-12.')
            ->assertExitCode(0);
    }
}
