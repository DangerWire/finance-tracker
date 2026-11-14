<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Models\ExchangeRate;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_currency_options_in_settings(): void
    {
        $user = User::factory()->create([
            'currency' => 'USD',
        ]);

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertOk();
        $response->assertSee('Primary Currency');
        $response->assertSee('USD');
        $response->assertSee('IDR');
    }

    public function test_user_can_update_primary_currency_in_settings(): void
    {
        $user = User::factory()->create([
            'currency' => 'USD',
        ]);

        $response = $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'currency' => 'IDR',
            'recalculate_existing' => '0',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('profile.edit'));

        $this->assertSame('IDR', $user->fresh()->currency);
    }

    public function test_user_updating_currency_with_recalculate_updates_past_transactions(): void
    {
        $user = User::factory()->create([
            'currency' => 'USD',
        ]);

        // Historical transaction in USD
        $transaction = Transaction::factory()->for($user)->create([
            'amount' => 100,
            'currency' => 'USD',
            'base_amount' => 100,
            'base_currency' => 'USD',
            'applied_rate' => 1,
            'occurred_at' => CarbonImmutable::parse('2026-01-15 10:00:00'),
        ]);

        // Seed exchange rate for USD -> IDR
        ExchangeRate::query()->create([
            'base_currency' => 'USD',
            'quote_currency' => 'IDR',
            'rate' => 15500.0,
            'rate_date' => '2026-01-15',
        ]);

        $response = $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'currency' => 'IDR',
            'recalculate_existing' => '1',
        ]);

        $response->assertSessionHasNoErrors();

        $transaction->refresh();
        $this->assertSame('IDR', $transaction->base_currency);
        $this->assertEquals(1550000.0, (float) $transaction->base_amount);
        $this->assertEquals(15500.0, (float) $transaction->applied_rate);
    }

    public function test_transaction_create_page_shows_locked_user_currency(): void
    {
        $user = User::factory()->create([
            'currency' => 'IDR',
        ]);

        $response = $this->actingAs($user)->get(route('transactions.create'));

        $response->assertOk();
        $response->assertSee('IDR');
        $response->assertSee('Locked');
        $response->assertSee('Configured via');
    }

    public function test_transaction_stores_with_user_currency_when_omitted(): void
    {
        $user = User::factory()->create([
            'currency' => 'IDR',
        ]);

        $response = $this->actingAs($user)->post(route('transactions.store'), [
            'type' => TransactionType::Expense->value,
            'amount' => 50000,
            'occurred_at' => now()->toDateTimeString(),
            'category' => 'Food',
        ]);

        $response->assertRedirect(route('transactions.index'));

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'amount' => 50000,
            'currency' => 'IDR',
            'base_currency' => 'IDR',
            'base_amount' => 50000,
        ]);
    }
}
