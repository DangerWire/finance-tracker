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
}
