<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_export_routes(): void
    {
        $this->get(route('transactions.export.csv'))->assertRedirect(route('login'));
        $this->get(route('transactions.export.statement'))->assertRedirect(route('login'));
    }

    public function test_csv_export_includes_utf8_bom_headers_filters_and_escapes_formulas(): void
    {
        $user = User::factory()->create(['currency' => 'CNY']);
        $otherUser = User::factory()->create(['currency' => 'CNY']);

        // Matching transaction with formula-like note & category to verify neutralization
        Transaction::factory()->create([
            'user_id' => $user->id,
            'type' => TransactionType::Expense,
            'amount' => 45.00,
            'currency' => 'CNY',
            'base_amount' => 45.00,
            'base_currency' => 'CNY',
            'applied_rate' => 1,
            'category' => 'Food',
            'note' => '=SUM(A1:A2)',
            'occurred_at' => CarbonImmutable::parse('2026-11-10 12:00:00'),
        ]);

        // Filtered-out income transaction
        Transaction::factory()->create([
            'user_id' => $user->id,
            'type' => TransactionType::Income,
            'amount' => 900.00,
            'currency' => 'CNY',
            'base_amount' => 900.00,
            'base_currency' => 'CNY',
            'category' => 'Salary',
            'note' => 'Excluded Income Row',
            'occurred_at' => CarbonImmutable::parse('2026-11-10 09:00:00'),
        ]);

        // Another user's transaction (must never leak)
        Transaction::factory()->create([
            'user_id' => $otherUser->id,
            'type' => TransactionType::Expense,
            'amount' => 777.00,
            'currency' => 'CNY',
            'base_amount' => 777.00,
            'base_currency' => 'CNY',
            'category' => 'Food',
            'note' => 'Other Users Lunch',
            'occurred_at' => CarbonImmutable::parse('2026-11-10 12:00:00'),
        ]);

        $response = $this->actingAs($user)
            ->get(route('transactions.export.csv', [
                'type' => 'expense',
                'from' => '2026-11-01',
                'to' => '2026-11-30',
            ]));

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();

        $this->assertTrue(str_starts_with($content, "\xEF\xBB\xBF"));
        $this->assertStringContainsString('Date,Type,Category,Note,Amount,Currency,"Base Amount","Base Currency","Applied Rate"', $content);
        $this->assertStringContainsString("'=SUM(A1:A2)", $content);
        $this->assertStringContainsString('45.00', $content);
        $this->assertStringNotContainsString('Excluded Income Row', $content);
        $this->assertStringNotContainsString('Other Users Lunch', $content);
    }

    public function test_statement_view_respects_base_currency_invariant_filters_and_user_isolation(): void
    {
        $user = User::factory()->create([
            'name' => 'Statement Owner',
            'currency' => 'CNY',
        ]);
        $otherUser = User::factory()->create(['currency' => 'CNY']);

        Transaction::factory()->create([
            'user_id' => $user->id,
            'type' => TransactionType::Income,
            'amount' => 1000.00,
            'currency' => 'CNY',
            'base_amount' => 1000.00,
            'base_currency' => 'CNY',
            'category' => 'Salary',
            'note' => 'Nov Salary',
            'occurred_at' => CarbonImmutable::parse('2026-11-05 10:00:00'),
        ]);

        Transaction::factory()->create([
            'user_id' => $user->id,
            'type' => TransactionType::Expense,
            'amount' => 250.00,
            'currency' => 'CNY',
            'base_amount' => 250.00,
            'base_currency' => 'CNY',
            'category' => 'Groceries',
            'note' => 'Market Run',
            'occurred_at' => CarbonImmutable::parse('2026-11-06 15:00:00'),
        ]);

        // Mismatched base_currency row: excluded from totals but flagged
        Transaction::factory()->create([
            'user_id' => $user->id,
            'type' => TransactionType::Expense,
            'amount' => 999.00,
            'currency' => 'USD',
            'base_amount' => 999.00,
            'base_currency' => 'USD',
            'category' => 'Travel',
            'note' => 'Unconverted Flight',
            'occurred_at' => CarbonImmutable::parse('2026-11-07 11:00:00'),
        ]);

        // Outside requested date range
        Transaction::factory()->create([
            'user_id' => $user->id,
            'type' => TransactionType::Expense,
            'amount' => 50.00,
            'currency' => 'CNY',
            'base_amount' => 50.00,
            'base_currency' => 'CNY',
            'category' => 'Coffee',
            'note' => 'October Coffee',
            'occurred_at' => CarbonImmutable::parse('2026-10-15 08:00:00'),
        ]);

        // Other user's entry
        Transaction::factory()->create([
            'user_id' => $otherUser->id,
            'type' => TransactionType::Income,
            'amount' => 5555.00,
            'currency' => 'CNY',
            'base_amount' => 5555.00,
            'base_currency' => 'CNY',
            'category' => 'Bonus',
            'note' => 'Confidential Other Row',
            'occurred_at' => CarbonImmutable::parse('2026-11-06 10:00:00'),
        ]);

        $response = $this->actingAs($user)->get(route('transactions.export.statement', [
            'from' => '2026-11-01',
            'to' => '2026-11-30',
        ]));

        $response->assertOk()
            ->assertViewHas('income', 1000.0)
            ->assertViewHas('expenses', 250.0)
            ->assertViewHas('balance', 750.0)
            ->assertViewHas('unconvertedCount', 1)
            ->assertSee('Statement Owner')
            ->assertSee('Nov Salary')
            ->assertSee('Market Run')
            ->assertSee('+1,000.00')
            ->assertSee('−250.00')
            ->assertSee('+750.00')
            ->assertDontSee('October Coffee')
            ->assertDontSee('Confidential Other Row');
    }
}
