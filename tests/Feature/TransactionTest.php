<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_transaction(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('transactions.store'), [
            'type' => 'expense',
            'amount' => 42.50,
            'currency' => 'cny',
            'occurred_at' => '2026-10-05 12:00:00',
            'category' => 'food',
            'note' => 'Lunch',
        ]);

        $response->assertRedirect(route('transactions.index'));

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'amount' => 42.50,
            'type' => 'expense',
            'currency' => 'CNY',
            'category' => 'food',
        ]);
    }

    public function test_currency_is_normalized_to_uppercase(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('transactions.store'), [
            'type' => 'income',
            'amount' => 100,
            'currency' => 'usd',
            'occurred_at' => '2026-10-05 12:00:00',
        ]);

        $this->assertDatabaseHas('transactions', ['currency' => 'USD']);
    }

    public function test_amount_must_be_greater_than_zero(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('transactions.store'), [
            'type' => 'expense',
            'amount' => 0,
            'currency' => 'CNY',
            'occurred_at' => '2026-10-05 12:00:00',
        ])->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_type_must_be_a_valid_enum_value(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('transactions.store'), [
            'type' => 'transfer',
            'amount' => 10,
            'currency' => 'CNY',
            'occurred_at' => '2026-10-05 12:00:00',
        ])->assertSessionHasErrors('type');
    }

    public function test_guests_are_redirected(): void
    {
        $this->get(route('transactions.index'))->assertRedirect(route('login'));
    }

    public function test_user_cannot_view_another_users_transaction(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $transaction = Transaction::factory()->for($owner)->create();

        $this->actingAs($other)
            ->get(route('transactions.edit', $transaction))
            ->assertForbidden();
    }

    public function test_index_only_lists_own_transactions(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Transaction::factory()->for($user)->count(2)->create();
        Transaction::factory()->for($other)->create(['note' => 'not mine']);

        $response = $this->actingAs($user)->get(route('transactions.index'));

        $response->assertOk();
        $response->assertViewHas('transactions', fn ($paginator) => $paginator->total() === 2);
    }

    public function test_user_can_view_own_transaction(): void
    {
        $user = User::factory()->create();
        $transaction = Transaction::factory()->for($user)->create(['amount' => 25.00]);

        $this->actingAs($user)
            ->get(route('transactions.show', $transaction))
            ->assertOk()
            ->assertViewHas('transaction', fn ($t) => $t->is($transaction));
    }

    public function test_user_cannot_view_another_users_transaction_detail(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $transaction = Transaction::factory()->for($owner)->create();

        $this->actingAs($other)
            ->get(route('transactions.show', $transaction))
            ->assertForbidden();
    }

    public function test_index_can_filter_by_type(): void
    {
        $user = User::factory()->create();

        Transaction::factory()->for($user)->create(['type' => 'income']);
        Transaction::factory()->for($user)->count(3)->create(['type' => 'expense']);

        $this->actingAs($user)
            ->get(route('transactions.index', ['type' => 'income']))
            ->assertOk()
            ->assertViewHas('transactions', fn ($paginator) => $paginator->total() === 1);
    }

    public function test_user_can_update_and_delete_own_transaction(): void
    {
        $user = User::factory()->create();
        $transaction = Transaction::factory()->for($user)->create(['type' => TransactionType::Expense]);

        $this->actingAs($user)->put(route('transactions.update', $transaction), [
            'type' => 'income',
            'amount' => 500,
            'currency' => 'CNY',
            'occurred_at' => '2026-10-05 12:00:00',
        ])->assertRedirect(route('transactions.index'));

        $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'type' => 'income']);

        $this->actingAs($user)
            ->delete(route('transactions.destroy', $transaction))
            ->assertRedirect(route('transactions.index'));

        $this->assertDatabaseMissing('transactions', ['id' => $transaction->id]);
    }

    public function test_summary_totals_only_count_current_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Transaction::factory()->for($user)->create(['type' => 'income', 'amount' => 100]);
        Transaction::factory()->for($user)->create(['type' => 'expense', 'amount' => 30]);
        Transaction::factory()->for($other)->create(['type' => 'expense', 'amount' => 5000]);

        $response = $this->actingAs($user)->get(route('transactions.index'));

        $response->assertViewHas('income', 100.0);
        $response->assertViewHas('expenses', 30.0);
        $response->assertViewHas('balance', 70.0);
    }
}
