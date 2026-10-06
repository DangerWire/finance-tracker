<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SpendingStatsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Figures are always reported in the base currency.
 *
 * The unconverted mode was removed along with the checkbox that controlled it,
 * so these cover the one behaviour that remains: mixed original currencies are
 * summed from their base snapshot rather than from the raw amounts.
 */
class CurrencyPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_totals_sum_the_base_snapshot(): void
    {
        config(['finance.base_currency' => 'IDR']);

        $user = User::factory()->create();
        $this->spend($user, 100, 'CNY', 'IDR', 267099, 'food');
        $this->spend($user, 50, 'IDR', 'IDR', 50, 'food');

        $stats = app(SpendingStatsService::class)->forUser($user->id);

        $this->assertSame('IDR', $stats['display_currency']);
        $this->assertSame(267149.0, $stats['current_period']['expenses']);
    }

    public function test_each_category_reports_the_base_currency(): void
    {
        config(['finance.base_currency' => 'IDR']);

        $user = User::factory()->create();
        $this->spend($user, 100, 'CNY', 'IDR', 267099, 'food');

        $stats = app(SpendingStatsService::class)->forUser($user->id);

        $this->assertSame('IDR', $stats['categories'][0]['currency']);
        $this->assertSame(267099.0, $stats['categories'][0]['total']);
    }

    public function test_the_largest_expense_is_reported_in_the_base_currency(): void
    {
        config(['finance.base_currency' => 'IDR']);

        $user = User::factory()->create();
        $this->spend($user, 100, 'CNY', 'IDR', 267099, 'housing');

        $stats = app(SpendingStatsService::class)->forUser($user->id);

        $this->assertSame(267099.0, $stats['largest_expense']['amount']);
        $this->assertSame('IDR', $stats['largest_expense']['currency']);
        // The amount the user actually entered is still available.
        $this->assertSame(100.0, $stats['largest_expense']['original_amount']);
        $this->assertSame('CNY', $stats['largest_expense']['original_currency']);
    }

    public function test_shares_add_up_across_categories(): void
    {
        $user = User::factory()->create();
        $this->spend($user, 100, 'food');
        $this->spend($user, 300, 'housing');

        $stats = app(SpendingStatsService::class)->forUser($user->id);

        $share = array_sum(array_column($stats['categories'], 'share_of_spend_percent'));

        $this->assertEqualsWithDelta(100.0, $share, 0.2);
    }

    public function test_the_page_no_longer_offers_a_conversion_toggle(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('insights.index'))
            ->assertOk()
            ->assertDontSee('prefers_base_currency', false);
    }

    public function test_the_preference_endpoint_is_gone(): void
    {
        $this->assertFalse(
            Route::has('insights.preference'),
            'The preference route should no longer exist.'
        );
    }

    public function test_the_page_always_reports_converted_figures(): void
    {
        config(['finance.base_currency' => 'CNY']);

        $user = User::factory()->create();
        $this->spend($user, 100, 'CNY', 'CNY', 100, 'food');

        $this->actingAs($user)
            ->get(route('insights.index'))
            ->assertOk()
            ->assertViewHas('stats', fn (array $stats) => $stats['display_currency'] === 'CNY');
    }

    private function spend(
        User $user,
        float $amount,
        string $currency,
        ?string $baseCurrency = null,
        ?float $baseAmount = null,
        ?string $category = null,
    ): Transaction {
        $baseCurrency ??= strtoupper((string) config('finance.base_currency'));
        $baseAmount ??= $amount;

        return Transaction::factory()->for($user)->create([
            'type' => TransactionType::Expense,
            'amount' => $amount,
            'currency' => $currency,
            'base_amount' => $baseAmount,
            'base_currency' => $baseCurrency,
            'applied_rate' => $currency === $baseCurrency ? 1 : $baseAmount / $amount,
            'category' => $category ?? 'food',
            'occurred_at' => CarbonImmutable::now()->startOfMonth()->addDay(2)->setTime(12, 0),
        ]);
    }
}
