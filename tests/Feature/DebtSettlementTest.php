<?php

namespace Tests\Feature;

use App\Actions\SettleDebtAction;
use App\Enums\DebtStatus;
use App\Enums\TransactionType;
use App\Models\Debt;
use App\Models\ExchangeRate;
use App\Models\Transaction;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebtSettlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_borrower_can_submit_payment(): void
    {
        $lender = User::factory()->create();
        $borrower = User::factory()->create();

        $debt = Debt::factory()->active()->create([
            'creator_id' => $lender->id,
            'lender_id' => $lender->id,
            'borrower_id' => $borrower->id,
        ]);

        $this->actingAs($borrower)
            ->post(route('debts.pay', $debt))
            ->assertRedirect(route('debts.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('debts', [
            'id' => $debt->id,
            'status' => DebtStatus::PaymentSubmitted->value,
        ]);
        $this->assertNotNull($debt->fresh()->payment_submitted_at);
    }

    public function test_lender_cannot_submit_payment(): void
    {
        $lender = User::factory()->create();
        $borrower = User::factory()->create();

        $debt = Debt::factory()->active()->create([
            'creator_id' => $lender->id,
            'lender_id' => $lender->id,
            'borrower_id' => $borrower->id,
        ]);

        $this->actingAs($lender)
            ->post(route('debts.pay', $debt))
            ->assertForbidden();

        $this->assertSame(DebtStatus::Active, $debt->fresh()->status);
    }

    public function test_lender_can_dispute_submitted_payment(): void
    {
        $lender = User::factory()->create();
        $borrower = User::factory()->create();

        $debt = Debt::factory()->paymentSubmitted()->create([
            'creator_id' => $lender->id,
            'lender_id' => $lender->id,
            'borrower_id' => $borrower->id,
        ]);

        $this->actingAs($lender)
            ->post(route('debts.dispute', $debt))
            ->assertRedirect(route('debts.index'))
            ->assertSessionHas('status');

        $fresh = $debt->fresh();
        $this->assertSame(DebtStatus::Active, $fresh->status);
        $this->assertNull($fresh->payment_submitted_at);
    }

    public function test_borrower_cannot_dispute_payment(): void
    {
        $lender = User::factory()->create();
        $borrower = User::factory()->create();

        $debt = Debt::factory()->paymentSubmitted()->create([
            'creator_id' => $lender->id,
            'lender_id' => $lender->id,
            'borrower_id' => $borrower->id,
        ]);

        $this->actingAs($borrower)
            ->post(route('debts.dispute', $debt))
            ->assertForbidden();
    }

    public function test_lender_confirming_payment_triggers_atomic_dual_ledger_settlement(): void
    {
        $lender = User::factory()->create(['name' => 'Alice', 'currency' => 'CNY']);
        $borrower = User::factory()->create(['name' => 'Bob', 'currency' => 'CNY']);

        $debt = Debt::factory()->paymentSubmitted()->create([
            'creator_id' => $lender->id,
            'lender_id' => $lender->id,
            'borrower_id' => $borrower->id,
            'amount' => 200.00,
            'currency' => 'CNY',
            'note' => 'Weekend trip hotel',
        ]);

        $this->actingAs($lender)
            ->post(route('debts.confirm', $debt))
            ->assertRedirect(route('debts.index'))
            ->assertSessionHas('status');

        $freshDebt = $debt->fresh();
        $this->assertSame(DebtStatus::Settled, $freshDebt->status);
        $this->assertNotNull($freshDebt->settled_at);
        $this->assertNotNull($freshDebt->lender_transaction_id);
        $this->assertNotNull($freshDebt->borrower_transaction_id);

        // Verify Lender transaction (+ Income)
        $lenderTx = Transaction::find($freshDebt->lender_transaction_id);
        $this->assertNotNull($lenderTx);
        $this->assertSame($lender->id, $lenderTx->user_id);
        $this->assertSame(TransactionType::Income, $lenderTx->type);
        $this->assertEquals(200.00, $lenderTx->amount);
        $this->assertSame('CNY', $lenderTx->currency);
        $this->assertSame('Debt Repayment', $lenderTx->category);
        $this->assertStringContainsString('Bob', $lenderTx->note);
        $this->assertEquals(200.00, $lenderTx->base_amount);

        // Verify Borrower transaction (- Expense)
        $borrowerTx = Transaction::find($freshDebt->borrower_transaction_id);
        $this->assertNotNull($borrowerTx);
        $this->assertSame($borrower->id, $borrowerTx->user_id);
        $this->assertSame(TransactionType::Expense, $borrowerTx->type);
        $this->assertEquals(200.00, $borrowerTx->amount);
        $this->assertSame('CNY', $borrowerTx->currency);
        $this->assertSame('Debt Settlement', $borrowerTx->category);
        $this->assertStringContainsString('Alice', $borrowerTx->note);
        $this->assertEquals(200.00, $borrowerTx->base_amount);
    }

    public function test_settle_action_throws_exception_if_debt_not_payment_submitted(): void
    {
        $lender = User::factory()->create();
        $borrower = User::factory()->create();

        $debt = Debt::factory()->active()->create([
            'creator_id' => $lender->id,
            'lender_id' => $lender->id,
            'borrower_id' => $borrower->id,
        ]);

        $this->expectException(DomainException::class);

        app(SettleDebtAction::class)->execute($debt);
    }

    public function test_settlement_converts_amounts_when_parties_have_different_currencies(): void
    {
        $lender = User::factory()->create(['name' => 'Alice', 'currency' => 'CNY']);
        $borrower = User::factory()->create(['name' => 'Bob', 'currency' => 'USD']);

        ExchangeRate::query()->create([
            'base_currency' => 'USD',
            'quote_currency' => 'CNY',
            'rate_date' => now()->toDateString(),
            'rate' => 7.20000000,
        ]);

        $debt = Debt::factory()->paymentSubmitted()->create([
            'creator_id' => $lender->id,
            'lender_id' => $lender->id,
            'borrower_id' => $borrower->id,
            'amount' => 100.00,
            'currency' => 'USD',
            'note' => 'Cross-border loan',
        ]);

        $this->actingAs($lender)
            ->post(route('debts.confirm', $debt))
            ->assertRedirect(route('debts.index'));

        $freshDebt = $debt->fresh();
        $this->assertSame(DebtStatus::Settled, $freshDebt->status);

        $lenderTx = Transaction::find($freshDebt->lender_transaction_id);
        $this->assertNotNull($lenderTx);
        $this->assertSame('USD', $lenderTx->currency);
        $this->assertEquals(100.00, $lenderTx->amount);
        $this->assertSame('CNY', $lenderTx->base_currency);
        $this->assertEquals(720.00, $lenderTx->base_amount);
        $this->assertEquals(7.20000000, $lenderTx->applied_rate);

        $borrowerTx = Transaction::find($freshDebt->borrower_transaction_id);
        $this->assertNotNull($borrowerTx);
        $this->assertSame('USD', $borrowerTx->currency);
        $this->assertEquals(100.00, $borrowerTx->amount);
        $this->assertSame('USD', $borrowerTx->base_currency);
        $this->assertEquals(100.00, $borrowerTx->base_amount);
        $this->assertEquals(1.0, $borrowerTx->applied_rate);
    }

    public function test_unrelated_user_cannot_confirm_or_dispute_payment(): void
    {
        $lender = User::factory()->create();
        $borrower = User::factory()->create();
        $intruder = User::factory()->create();

        $debt = Debt::factory()->paymentSubmitted()->create([
            'creator_id' => $lender->id,
            'lender_id' => $lender->id,
            'borrower_id' => $borrower->id,
        ]);

        $this->actingAs($intruder)
            ->post(route('debts.confirm', $debt))
            ->assertForbidden();

        $this->actingAs($intruder)
            ->post(route('debts.dispute', $debt))
            ->assertForbidden();
    }
}
