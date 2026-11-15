<?php

namespace Tests\Feature;

use App\Enums\DebtStatus;
use App\Models\Debt;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebtAgreementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_debts_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('debts.index'))
            ->assertOk()
            ->assertSee('Peer-to-peer debts');
    }

    public function test_user_can_propose_loan_to_friend_when_lending(): void
    {
        $user = User::factory()->create();
        $friend = User::factory()->create();

        Friendship::factory()->accepted()->create([
            'user_id' => $user->id,
            'friend_id' => $friend->id,
        ]);

        $this->actingAs($user)
            ->post(route('debts.store'), [
                'friend_id' => $friend->id,
                'type' => 'lent',
                'amount' => '150.00',
                'currency' => 'CNY',
                'note' => 'Concert ticket',
                'due_date' => now()->addMonth()->format('Y-m-d'),
            ])
            ->assertRedirect(route('debts.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('debts', [
            'creator_id' => $user->id,
            'lender_id' => $user->id,
            'borrower_id' => $friend->id,
            'amount' => 150.00,
            'currency' => 'CNY',
            'note' => 'Concert ticket',
            'status' => DebtStatus::PendingAcceptance->value,
        ]);
    }

    public function test_user_can_propose_loan_to_friend_when_borrowing(): void
    {
        $user = User::factory()->create();
        $friend = User::factory()->create();

        Friendship::factory()->accepted()->create([
            'user_id' => $friend->id,
            'friend_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->post(route('debts.store'), [
                'friend_id' => $friend->id,
                'type' => 'borrowed',
                'amount' => '75.50',
                'currency' => 'CNY',
                'note' => 'Dinner share',
            ])
            ->assertRedirect(route('debts.index'));

        $this->assertDatabaseHas('debts', [
            'creator_id' => $user->id,
            'lender_id' => $friend->id,
            'borrower_id' => $user->id,
            'amount' => 75.50,
            'currency' => 'CNY',
            'status' => DebtStatus::PendingAcceptance->value,
        ]);
    }

    public function test_user_cannot_create_debt_with_non_friend(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($user)
            ->post(route('debts.store'), [
                'friend_id' => $stranger->id,
                'type' => 'lent',
                'amount' => '50.00',
                'currency' => 'CNY',
            ])
            ->assertSessionHasErrors('friend_id');

        $this->assertDatabaseCount('debts', 0);
    }

    public function test_counterparty_can_accept_loan_proposal(): void
    {
        $lender = User::factory()->create();
        $borrower = User::factory()->create();

        $debt = Debt::factory()->create([
            'creator_id' => $lender->id,
            'lender_id' => $lender->id,
            'borrower_id' => $borrower->id,
            'status' => DebtStatus::PendingAcceptance,
        ]);

        $this->actingAs($borrower)
            ->post(route('debts.accept', $debt))
            ->assertRedirect(route('debts.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('debts', [
            'id' => $debt->id,
            'status' => DebtStatus::Active->value,
        ]);
        $this->assertNotNull($debt->fresh()->accepted_at);
    }

    public function test_creator_cannot_accept_own_loan_proposal(): void
    {
        $lender = User::factory()->create();
        $borrower = User::factory()->create();

        $debt = Debt::factory()->create([
            'creator_id' => $lender->id,
            'lender_id' => $lender->id,
            'borrower_id' => $borrower->id,
            'status' => DebtStatus::PendingAcceptance,
        ]);

        $this->actingAs($lender)
            ->post(route('debts.accept', $debt))
            ->assertForbidden();

        $this->assertSame(DebtStatus::PendingAcceptance, $debt->fresh()->status);
    }

    public function test_counterparty_can_decline_loan_proposal(): void
    {
        $lender = User::factory()->create();
        $borrower = User::factory()->create();

        $debt = Debt::factory()->create([
            'creator_id' => $lender->id,
            'lender_id' => $lender->id,
            'borrower_id' => $borrower->id,
            'status' => DebtStatus::PendingAcceptance,
        ]);

        $this->actingAs($borrower)
            ->post(route('debts.decline', $debt))
            ->assertRedirect(route('debts.index'));

        $this->assertDatabaseHas('debts', [
            'id' => $debt->id,
            'status' => DebtStatus::Declined->value,
        ]);
    }

    public function test_unrelated_user_cannot_accept_or_decline_loan_proposal(): void
    {
        $lender = User::factory()->create();
        $borrower = User::factory()->create();
        $intruder = User::factory()->create();

        $debt = Debt::factory()->create([
            'creator_id' => $lender->id,
            'lender_id' => $lender->id,
            'borrower_id' => $borrower->id,
            'status' => DebtStatus::PendingAcceptance,
        ]);

        $this->actingAs($intruder)
            ->post(route('debts.accept', $debt))
            ->assertForbidden();

        $this->actingAs($intruder)
            ->post(route('debts.decline', $debt))
            ->assertForbidden();
    }
}
