<?php

namespace Tests\Feature;

use App\Enums\DebtStatus;
use App\Enums\TransactionType;
use App\Models\Budget;
use App\Models\Debt;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_dashboard_renders_empty_state_for_new_user(): void
    {
        $user = User::factory()->create(['currency' => 'CNY']);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('No transactions yet.')
            ->assertSee('+0.00')
            ->assertSee('No active recurring schedules.')
            ->assertSee('No budgets set for this month.');
    }

    public function test_dashboard_computes_total_balance_and_monthly_totals_using_base_currency_only(): void
    {
        $user = User::factory()->create(['currency' => 'CNY']);
        $now = CarbonImmutable::now();
        $inCurrentMonth = $now->startOfMonth()->addHours(10);
        $inPreviousMonth = $now->subMonthsNoOverflow(1)->startOfMonth()->addDays(5);

        // Prior month income: +1000 CNY -> affects Total Balance, NOT Monthly Income
        Transaction::factory()->create([
            'user_id' => $user->id,
            'type' => TransactionType::Income,
            'amount' => 1000.00,
            'currency' => 'CNY',
            'base_amount' => 1000.00,
            'base_currency' => 'CNY',
            'category' => 'Salary',
            'occurred_at' => $inPreviousMonth,
        ]);

        // Current month income: +500 CNY (entered as foreign EUR converted to 500 CNY base_amount)
        Transaction::factory()->create([
            'user_id' => $user->id,
            'type' => TransactionType::Income,
            'amount' => 65.00,
            'currency' => 'EUR',
            'base_amount' => 500.00,
            'base_currency' => 'CNY',
            'applied_rate' => 7.6923,
            'category' => 'Freelance',
            'occurred_at' => $inCurrentMonth,
        ]);

        // Current month expense: -150 CNY
        Transaction::factory()->create([
            'user_id' => $user->id,
            'type' => TransactionType::Expense,
            'amount' => 150.00,
            'currency' => 'CNY',
            'base_amount' => 150.00,
            'base_currency' => 'CNY',
            'category' => 'Groceries',
            'occurred_at' => $inCurrentMonth,
        ]);

        // Mismatched base_currency row: 999 USD -> MUST be excluded from totals and flagged
        Transaction::factory()->create([
            'user_id' => $user->id,
            'type' => TransactionType::Expense,
            'amount' => 999.00,
            'currency' => 'USD',
            'base_amount' => 999.00,
            'base_currency' => 'USD',
            'category' => 'Travel',
            'occurred_at' => $inCurrentMonth,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()
            // Total Balance = (1000 + 500) - 150 = +1,350.00 CNY
            ->assertViewHas('totalBalance', 1350.0)
            ->assertSee('+1,350.00')
            // Monthly Income = +500.00 CNY
            ->assertViewHas('monthlyIncome', 500.0)
            ->assertSee('+500.00')
            // Monthly Expenses = 150.00 CNY (USD 999 excluded!)
            ->assertViewHas('monthlyExpenses', 150.0)
            ->assertSee('−150.00')
            // Monthly Net = 500 - 150 = +350.00 CNY (Savings rate: 70.0%)
            ->assertViewHas('monthlyNet', 350.0)
            ->assertSee('+350.00')
            ->assertSee('Savings rate: 70.0%')
            // Unconverted count warning
            ->assertViewHas('unconvertedCount', 1)
            ->assertSee('1 transaction could not be converted to CNY and is excluded from these totals.');
    }

    public function test_dashboard_lists_recent_transactions_newest_first_capped_at_seven(): void
    {
        $user = User::factory()->create(['currency' => 'CNY']);
        $anchor = CarbonImmutable::now()->startOfDay();

        for ($i = 1; $i <= 9; $i++) {
            Transaction::factory()->create([
                'user_id' => $user->id,
                'type' => TransactionType::Expense,
                'amount' => 10 * $i,
                'currency' => 'CNY',
                'base_amount' => 10 * $i,
                'base_currency' => 'CNY',
                'category' => 'Food',
                'note' => "Ledger Entry #{$i}",
                'occurred_at' => $anchor->subDays(10 - $i),
            ]);
        }

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();

        // Newest 7 (Entries 3..9) must appear; oldest 2 (Entries 1..2) must not
        for ($i = 3; $i <= 9; $i++) {
            $response->assertSee("Ledger Entry #{$i}");
        }

        $response->assertDontSee('Ledger Entry #1')
            ->assertDontSee('Ledger Entry #2');
    }

    public function test_dashboard_displays_budgets_debts_and_upcoming_recurring_summaries(): void
    {
        $user = User::factory()->create(['currency' => 'CNY']);
        $friend = User::factory()->create();
        $now = CarbonImmutable::now();

        Budget::factory()->create([
            'user_id' => $user->id,
            'category' => 'Dining Out',
            'amount' => 1000.00,
            'period_month' => $now->format('Y-m'),
        ]);

        Transaction::factory()->create([
            'user_id' => $user->id,
            'type' => TransactionType::Expense,
            'amount' => 400.00,
            'currency' => 'CNY',
            'base_amount' => 400.00,
            'base_currency' => 'CNY',
            'category' => 'Dining Out',
            'occurred_at' => $now->startOfMonth()->addHours(9),
        ]);

        RecurringTransaction::factory()->create([
            'user_id' => $user->id,
            'name' => 'Fiber Internet',
            'amount' => 120.00,
            'currency' => 'CNY',
            'is_active' => true,
            'next_occurrence_on' => $now->addDays(4)->toDateString(),
        ]);

        // Active lent debt (+300)
        Debt::factory()->active()->create([
            'creator_id' => $user->id,
            'lender_id' => $user->id,
            'borrower_id' => $friend->id,
            'amount' => 300.00,
            'currency' => 'CNY',
        ]);

        // Pending proposal awaiting user acceptance (requires action!)
        Debt::factory()->create([
            'creator_id' => $friend->id,
            'lender_id' => $friend->id,
            'borrower_id' => $user->id,
            'amount' => 80.00,
            'currency' => 'CNY',
            'status' => DebtStatus::PendingAcceptance,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Dining Out')
            ->assertSee('400 / 1,000')
            ->assertSee('Fiber Internet')
            ->assertSee('120.00')
            ->assertSee('+300.00')
            ->assertSee('Pending action (1)')
            ->assertSee('1 loan proposal(s) or payment confirmation(s) require your attention');
    }

    public function test_dashboard_isolates_data_per_user(): void
    {
        $user = User::factory()->create(['currency' => 'CNY']);
        $otherUser = User::factory()->create(['currency' => 'CNY']);
        $thirdUser = User::factory()->create(['currency' => 'CNY']);
        $now = CarbonImmutable::now();

        Transaction::factory()->create([
            'user_id' => $otherUser->id,
            'type' => TransactionType::Income,
            'amount' => 9876.00,
            'currency' => 'CNY',
            'base_amount' => 9876.00,
            'base_currency' => 'CNY',
            'category' => 'SecretBonus',
            'note' => 'Other user confidential note',
            'occurred_at' => $now->startOfMonth()->addHours(5),
        ]);

        Budget::factory()->create([
            'user_id' => $otherUser->id,
            'category' => 'PrivateYacht',
            'amount' => 5000.00,
            'period_month' => $now->format('Y-m'),
        ]);

        RecurringTransaction::factory()->create([
            'user_id' => $otherUser->id,
            'name' => 'Other User Gym',
            'is_active' => true,
            'next_occurrence_on' => $now->addDays(2)->toDateString(),
        ]);

        Debt::factory()->active()->create([
            'creator_id' => $otherUser->id,
            'lender_id' => $otherUser->id,
            'borrower_id' => $thirdUser->id,
            'amount' => 4321.00,
            'currency' => 'CNY',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()
            ->assertDontSee('SecretBonus')
            ->assertDontSee('Other user confidential note')
            ->assertDontSee('PrivateYacht')
            ->assertDontSee('Other User Gym')
            ->assertDontSee('4,321.00')
            ->assertDontSee('9,876.00')
            ->assertViewHas('totalBalance', 0.0);
    }
}
