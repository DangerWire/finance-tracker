<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SpendingStatsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversion_is_on_by_default(): void
    {
        $user = User::factory()->create();

        $this->assertTrue((bool) $user->fresh()->prefers_base_currency);
    }

    public function test_user_can_turn_conversion_off(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('insights.preference'), ['prefers_base_currency' => '0'])
            ->assertRedirect(route('insights.index'));

        $this->assertFalse((bool) $user->fresh()->prefers_base_currency);
    }

    public function test_user_can_turn_conversion_back_on(): void
    {
        $user = User::factory()->create(['prefers_base_currency' => false]);

        $this->actingAs($user)
            ->put(route('insights.preference'), ['prefers_base_currency' => '1']);

        $this->assertTrue((bool) $user->fresh()->prefers_base_currency);
    }

    public function test_converted_mode_sums_the_base_snapshot(): void
    {
        $user = User::factory()->create();
        $this->spend($user, 100, 'CNY', 'IDR', 267099, 'food');
        $this->spend($user, 50, 'IDR', 'IDR', 50, 'food');

        $stats = app(SpendingStatsService::class)->forUser($user->id, inBaseCurrency: true);

        $this->assertSame('converted', $stats['mode']);
        $this->assertSame('IDR', $stats['display_currency']);
        $this->assertSame(267149.0, $stats['current_period']['expenses']);
        $this->assertSame([], $stats['currencies']);
        $this->assertSame('IDR', $stats['categories'][0]['currency']);
    }

    public function test_original_mode_keeps_recorded_currencies_separate(): void
    {
        $user = User::factory()->create();
        $this->spend($user, 100, 'CNY', 'IDR', 267099, 'food');
        $this->spend($user, 50, 'IDR', 'IDR', 50, 'transport');

        $stats = app(SpendingStatsService::class)->forUser($user->id, inBaseCurrency: false);

        $this->assertSame('original', $stats['mode']);
        $this->assertNull($stats['display_currency']);

        $currencies = collect($stats['currencies'])->pluck('currency')->all();

        $this->assertEqualsCanonicalizing(['CNY', 'IDR'], $currencies);

        $totals = collect($stats['currencies'])->keyBy('currency');

        $this->assertSame(100.0, $totals['CNY']['total']);
        $this->assertSame(50.0, $totals['IDR']['total']);

        // No single combined total is claimed across currencies.
        $this->assertNull($stats['current_period']['expenses']);
    }

    public function test_original_mode_labels_each_category_with_its_currency(): void
    {
        $user = User::factory()->create();
        $this->spend($user, 100, 'CNY', 'IDR', 267099, 'food');
        $this->spend($user, 100, 'IDR', 'IDR', 100, 'food');

        $stats = app(SpendingStatsService::class)->forUser($user->id, inBaseCurrency: false);

        $rows = collect($stats['categories'])->keyBy(fn (array $row): string => $row['category'].'|'.$row['currency']);

        $this->assertSame(100.0, $rows['food|CNY']['total']);
        $this->assertSame(100.0, $rows['food|IDR']['total']);

        // Shares are per currency, never blended across them.
        $this->assertSame(100.0, $rows['food|CNY']['share_of_spend_percent']);
        $this->assertSame(100.0, $rows['food|IDR']['share_of_spend_percent']);
    }

    public function test_original_mode_reports_the_largest_expense_in_its_own_currency(): void
    {
        $user = User::factory()->create();
        $this->spend($user, 100, 'CNY', 'IDR', 267099, 'food');
        $this->spend($user, 90000, 'IDR', 'IDR', 90000, 'housing');

        $stats = app(SpendingStatsService::class)->forUser($user->id, inBaseCurrency: false);

        $this->assertSame(90000.0, $stats['largest_expense']['amount']);
        $this->assertSame('IDR', $stats['largest_expense']['currency']);
    }

    public function test_page_reflects_the_stored_preference(): void
    {
        $user = User::factory()->create(['prefers_base_currency' => false]);
        $this->spend($user, 100, 'CNY', 'IDR', 267099, 'food');

        $this->actingAs($user)
            ->get(route('insights.index'))
            ->assertOk()
            ->assertViewHas('convert', false);

        $user->update(['prefers_base_currency' => true]);

        $this->actingAs($user)
            ->get(route('insights.index'))
            ->assertOk()
            ->assertViewHas('convert', true);
    }

    private function spend(User $user, float $amount, string $currency, string $baseCurrency, float $baseAmount, string $category): Transaction
    {
        return Transaction::factory()->for($user)->create([
            'type' => TransactionType::Expense,
            'amount' => $amount,
            'currency' => $currency,
            'base_amount' => $baseAmount,
            'base_currency' => $baseCurrency,
            'applied_rate' => $currency === $baseCurrency ? 1 : $baseAmount / $amount,
            'category' => $category,
            'occurred_at' => CarbonImmutable::now()->startOfMonth()->addDay(2)->setTime(12, 0),
        ]);
    }
}
