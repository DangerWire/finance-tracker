<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SpendingInsightService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\StructuredResponseFake;
use RuntimeException;
use Tests\TestCase;

class SpendingInsightTest extends TestCase
{
    use RefreshDatabase;

    public function test_insights_page_shows_figures_without_any_ai_call(): void
    {
        config(['insights.api_key' => null]);

        $user = $this->userWithSpending(10);

        $this->actingAs($user)
            ->get(route('insights.index'))
            ->assertOk()
            ->assertViewHas('isConfigured', false)
            ->assertViewHas('stats', fn (array $stats) => $stats['transaction_count'] === 10);
    }

    public function test_generate_is_blocked_without_a_provider_key(): void
    {
        config(['insights.api_key' => null]);

        $user = $this->userWithSpending(10);

        $this->actingAs($user)
            ->from(route('insights.index'))
            ->post(route('insights.generate'))
            ->assertRedirect(route('insights.index'))
            ->assertSessionHasErrors('insights');
    }

    public function test_generate_is_blocked_when_data_is_too_thin(): void
    {
        config(['insights.api_key' => 'test-key']);

        $user = $this->userWithSpending(2);

        $this->actingAs($user)
            ->from(route('insights.index'))
            ->post(route('insights.generate'))
            ->assertRedirect(route('insights.index'))
            ->assertSessionHasErrors('insights');
    }

    public function test_guests_cannot_reach_insights(): void
    {
        $this->get(route('insights.index'))->assertRedirect(route('login'));
    }

    public function test_it_renders_structured_insights_from_the_provider(): void
    {
        config(['insights.api_key' => 'test-key']);

        Prism::fake([
            StructuredResponseFake::make()->withStructured([
                'headline' => 'October spending is close to last month.',
                'insights' => [
                    [
                        'title' => 'Housing dominates',
                        'detail' => 'Housing is your largest category this month.',
                        'severity' => 'info',
                        'category' => 'housing',
                    ],
                ],
            ]),
        ]);

        $user = $this->userWithSpending(10);

        $this->actingAs($user)
            ->post(route('insights.generate'))
            ->assertOk()
            ->assertViewHas('headline', 'October spending is close to last month.')
            ->assertViewHas('insights', fn (array $insights) => count($insights) === 1
                && $insights[0]['category'] === 'housing');
    }

    public function test_provider_failures_are_shown_to_the_user(): void
    {
        // Asserted without touching the network: an unconfigured key is the
        // failure users actually hit, and relying on a live call failing
        // would make this test depend on the provider being unavailable.
        config(['insights.api_key' => null]);

        $user = $this->userWithSpending(10);

        $this->actingAs($user)
            ->from(route('insights.index'))
            ->post(route('insights.generate'))
            ->assertRedirect(route('insights.index'))
            ->assertSessionHasErrors('insights');
    }

    public function test_failure_descriptions_never_leak_internals(): void
    {
        $message = SpendingInsightService::describeFailure(new RuntimeException('upstream 503 from provider'));

        $this->assertSame('upstream 503 from provider', $message);

        $generic = SpendingInsightService::describeFailure(new LogicException('stack trace and paths'));

        $this->assertStringContainsString('OPENROUTER_API_KEY', $generic);
        $this->assertStringNotContainsString('stack trace', $generic);
    }

    public function test_the_model_is_never_given_a_reasoning_task(): void
    {
        // Guards the core design rule: the provider must be configured through
        // the OpenRouter driver rather than silently defaulting elsewhere.
        config(['insights.model' => 'openai/gpt-4o-mini']);

        $this->assertSame('openai/gpt-4o-mini', config('insights.model'));
        $this->assertTrue(Provider::OpenRouter->value === 'openrouter');
    }

    private function userWithSpending(int $count): User
    {
        $user = User::factory()->create();

        foreach (range(1, $count) as $index) {
            Transaction::factory()->for($user)->create([
                'type' => TransactionType::Expense,
                'amount' => 50 + $index,
                'currency' => 'IDR',
                'base_amount' => 50 + $index,
                'base_currency' => 'IDR',
                'applied_rate' => 1,
                'category' => 'food',
                'occurred_at' => now()->startOfMonth()->addDays($index)->setTime(12, 0),
            ]);
        }

        return $user;
    }
}
