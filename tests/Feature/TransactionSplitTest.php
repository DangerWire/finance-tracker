<?php

namespace Tests\Feature;

use App\Actions\ConvertTransactionAmount;
use App\Enums\TransactionType;
use App\Models\Budget;
use App\Models\ExchangeRate;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CategoryService;
use App\Services\SpendingStatsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionSplitTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_split_transaction(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('transactions.store'), [
            'type' => TransactionType::Expense->value,
            'amount' => 150.00,
            'currency' => 'EUR',
            'occurred_at' => '2026-10-15 14:00',
            'note' => 'Supermarket run',
            'splits' => [
                [
                    'category' => 'Groceries',
                    'amount' => 100.00,
                    'note' => 'Weekly food',
                ],
                [
                    'category' => 'Household',
                    'amount' => 50.00,
                    'note' => 'Cleaning supplies',
                ],
            ],
        ]);

        $response->assertRedirect(route('transactions.index'));

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'amount' => 150.00,
            'category' => 'Split',
            'note' => 'Supermarket run',
        ]);

        $transaction = Transaction::where('user_id', $user->id)->firstOrFail();
        $this->assertTrue($transaction->isSplit());
        $this->assertCount(2, $transaction->splits);

        $this->assertDatabaseHas('transaction_splits', [
            'transaction_id' => $transaction->id,
            'category' => 'Groceries',
            'amount' => 100.00,
            'note' => 'Weekly food',
        ]);

        $this->assertDatabaseHas('transaction_splits', [
            'transaction_id' => $transaction->id,
            'category' => 'Household',
            'amount' => 50.00,
            'note' => 'Cleaning supplies',
        ]);
    }

    public function test_split_transaction_fails_validation_with_fewer_than_two_categories(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('transactions.store'), [
            'type' => TransactionType::Expense->value,
            'amount' => 100.00,
            'currency' => 'EUR',
            'occurred_at' => '2026-10-15 14:00',
            'splits' => [
                [
                    'category' => 'Groceries',
                    'amount' => 100.00,
                ],
            ],
        ]);

        $response->assertSessionHasErrors('splits');
        $this->assertDatabaseEmpty('transactions');
    }

    public function test_split_transaction_fails_validation_when_amounts_do_not_equal_total(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('transactions.store'), [
            'type' => TransactionType::Expense->value,
            'amount' => 100.00,
            'currency' => 'EUR',
            'occurred_at' => '2026-10-15 14:00',
            'splits' => [
                [
                    'category' => 'Groceries',
                    'amount' => 60.00,
                ],
                [
                    'category' => 'Snacks',
                    'amount' => 30.00,
                ],
            ],
        ]);

        $response->assertSessionHasErrors('splits');
        $this->assertDatabaseEmpty('transactions');
    }

    public function test_user_can_update_a_split_transaction(): void
    {
        $user = User::factory()->create();

        $transaction = Transaction::factory()->create([
            'user_id' => $user->id,
            'type' => TransactionType::Expense,
            'amount' => 100.00,
            'currency' => 'EUR',
            'category' => 'General',
        ]);

        $response = $this->actingAs($user)->put(route('transactions.update', $transaction), [
            'type' => TransactionType::Expense->value,
            'amount' => 120.00,
            'currency' => 'EUR',
            'occurred_at' => $transaction->occurred_at->format('Y-m-d H:i'),
            'splits' => [
                [
                    'category' => 'Dining',
                    'amount' => 80.00,
                    'note' => 'Dinner with team',
                ],
                [
                    'category' => 'Transport',
                    'amount' => 40.00,
                    'note' => 'Taxi home',
                ],
            ],
        ]);

        $response->assertRedirect(route('transactions.index'));

        $transaction->refresh();
        $this->assertEquals(120.00, $transaction->amount);
        $this->assertEquals('Split', $transaction->category);
        $this->assertCount(2, $transaction->splits);
        $this->assertEquals(['Dining', 'Transport'], $transaction->splits->pluck('category')->all());
    }

    public function test_budget_overview_and_spending_stats_attribute_spending_to_split_categories(): void
    {
        $user = User::factory()->create();
        $baseCurrency = strtoupper((string) config('finance.base_currency'));
        $periodMonth = '2026-10';
        $occurredAt = CarbonImmutable::parse('2026-10-10 12:00:00');

        Budget::factory()->create([
            'user_id' => $user->id,
            'category' => 'Groceries',
            'amount' => 200.00,
            'period_month' => $periodMonth,
        ]);

        Budget::factory()->create([
            'user_id' => $user->id,
            'category' => 'Household',
            'amount' => 100.00,
            'period_month' => $periodMonth,
        ]);

        // Create transaction with splits
        $convert = app(ConvertTransactionAmount::class);
        $attributes = $convert->apply([
            'type' => TransactionType::Expense,
            'amount' => 150.00,
            'currency' => $baseCurrency,
            'occurred_at' => $occurredAt,
            'category' => 'Split',
        ]);

        $transaction = $user->transactions()->create($attributes);
        $transaction->splits()->createMany([
            [
                'category' => 'Groceries',
                'amount' => 90.00,
                'base_amount' => 90.00,
            ],
            [
                'category' => 'Household',
                'amount' => 60.00,
                'base_amount' => 60.00,
            ],
        ]);

        $service = app(SpendingStatsService::class);
        $overview = $service->budgetOverview($user->id, $occurredAt);

        $groceriesItem = collect($overview['items'])->firstWhere('category', 'Groceries');
        $householdItem = collect($overview['items'])->firstWhere('category', 'Household');

        $this->assertNotNull($groceriesItem);
        $this->assertEquals(90.00, $groceriesItem['spent_amount']);
        $this->assertEquals(110.00, $groceriesItem['remaining_amount']);

        $this->assertNotNull($householdItem);
        $this->assertEquals(60.00, $householdItem['spent_amount']);
        $this->assertEquals(40.00, $householdItem['remaining_amount']);

        // Check categoryBreakdown in forUser
        $stats = $service->forUser($user->id, $occurredAt);
        $breakdown = collect($stats['categories']);

        $this->assertNotNull($breakdown->firstWhere('category', 'Groceries'));
        $this->assertEquals(90.00, $breakdown->firstWhere('category', 'Groceries')['total']);
        $this->assertNotNull($breakdown->firstWhere('category', 'Household'));
        $this->assertEquals(60.00, $breakdown->firstWhere('category', 'Household')['total']);
    }

    public function test_category_service_includes_categories_from_splits(): void
    {
        $user = User::factory()->create();

        $transaction = Transaction::factory()->create([
            'user_id' => $user->id,
            'category' => 'Split',
        ]);

        $transaction->splits()->create([
            'category' => 'UniqueSplitCategory',
            'amount' => 25.00,
            'base_amount' => 25.00,
        ]);

        $service = app(CategoryService::class);
        $categories = $service->forUser($user->id);

        $this->assertContains('UniqueSplitCategory', $categories);
        $this->assertNotContains('Split', $categories);
    }

    public function test_split_transaction_converts_base_amount_correctly_with_exchange_rates(): void
    {
        $user = User::factory()->create();
        $baseCurrency = strtoupper((string) config('finance.base_currency'));

        ExchangeRate::query()->create([
            'base_currency' => 'USD',
            'quote_currency' => $baseCurrency,
            'rate' => 7.20000000,
            'rate_date' => '2026-10-10',
        ]);

        $response = $this->actingAs($user)->post(route('transactions.store'), [
            'type' => TransactionType::Expense->value,
            'amount' => 100.00,
            'currency' => 'USD',
            'occurred_at' => '2026-10-10 12:00',
            'splits' => [
                [
                    'category' => 'Books',
                    'amount' => 33.33,
                ],
                [
                    'category' => 'Software',
                    'amount' => 66.67,
                ],
            ],
        ]);

        $response->assertRedirect(route('transactions.index'));

        $transaction = Transaction::where('user_id', $user->id)->firstOrFail();
        $this->assertCount(2, $transaction->splits);

        $splitBaseSum = $transaction->splits->sum('base_amount');
        $this->assertEquals($transaction->base_amount, $splitBaseSum);
    }
}
