<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramBotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.telegram.bot_token', '123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ');
        Config::set('services.telegram.bot_username', 'FinanceTrackerBot');
        Config::set('services.telegram.webhook_secret', 'test-webhook-secret-token');
        Config::set('insights.api_key', null);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
        ]);
    }

    public function test_user_can_generate_telegram_link_token(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('profile.telegram.link'));

        $response->assertRedirect(route('profile.edit'))
            ->assertSessionHas('status', 'telegram-link-token-generated');

        $user->refresh();
        $this->assertNotNull($user->telegram_link_token);
        $this->assertNotNull($user->telegram_link_token_expires_at);
        $this->assertTrue($user->telegram_link_token_expires_at->isFuture());
    }

    public function test_user_can_unlink_telegram_account(): void
    {
        $user = User::factory()->create([
            'telegram_chat_id' => 999888777,
            'telegram_username' => 'test_user',
            'telegram_link_token' => 'active_token',
        ]);

        $response = $this->actingAs($user)
            ->delete(route('profile.telegram.unlink'));

        $response->assertRedirect(route('profile.edit'))
            ->assertSessionHas('status', 'telegram-unlinked');

        $user->refresh();
        $this->assertNull($user->telegram_chat_id);
        $this->assertNull($user->telegram_username);
        $this->assertNull($user->telegram_link_token);
        $this->assertFalse($user->isTelegramLinked());
    }

    public function test_bot_links_account_via_start_token(): void
    {
        $user = User::factory()->create();
        $token = $user->generateTelegramLinkToken();

        $update = [
            'update_id' => 1001,
            'message' => [
                'message_id' => 1,
                'chat' => ['id' => 555666777, 'type' => 'private'],
                'from' => ['id' => 555666777, 'username' => 'sure_user', 'first_name' => 'Sure'],
                'date' => time(),
                'text' => "/start {$token}",
            ],
        ];

        $response = $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'test-webhook-secret-token')
            ->postJson(route('telegram.webhook'), $update);

        $response->assertOk()
            ->assertJson(['ok' => true]);

        $user->refresh();
        $this->assertSame(555666777, (int) $user->telegram_chat_id);
        $this->assertSame('sure_user', $user->telegram_username);
        $this->assertNull($user->telegram_link_token);
        $this->assertTrue($user->isTelegramLinked());
    }

    public function test_bot_rejects_expired_start_token(): void
    {
        $user = User::factory()->create([
            'telegram_link_token' => 'expired_token',
            'telegram_link_token_expires_at' => now()->subMinutes(10),
        ]);

        $update = [
            'update_id' => 1002,
            'message' => [
                'message_id' => 2,
                'chat' => ['id' => 444333222, 'type' => 'private'],
                'from' => ['id' => 444333222, 'username' => 'expired_user'],
                'date' => time(),
                'text' => '/start expired_token',
            ],
        ];

        $response = $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'test-webhook-secret-token')
            ->postJson(route('telegram.webhook'), $update);

        $response->assertOk();

        $user->refresh();
        $this->assertNull($user->telegram_chat_id);
    }

    public function test_bot_handles_explicit_expense_and_income_commands(): void
    {
        $user = User::factory()->create([
            'telegram_chat_id' => 888777666,
            'currency' => 'CNY',
        ]);

        // 1. /expense 45.50 Food Lunch at cafe
        $expenseUpdate = [
            'update_id' => 1003,
            'message' => [
                'message_id' => 3,
                'chat' => ['id' => 888777666, 'type' => 'private'],
                'from' => ['id' => 888777666, 'username' => 'finance_guy'],
                'text' => '/expense 45.50 Food Lunch at cafe',
            ],
        ];

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'test-webhook-secret-token')
            ->postJson(route('telegram.webhook'), $expenseUpdate)
            ->assertOk();

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'amount' => 45.50,
            'type' => TransactionType::Expense->value,
            'category' => 'Food',
            'note' => 'Lunch at cafe',
        ]);

        // 2. /income 5000 Salary November paycheck
        $incomeUpdate = [
            'update_id' => 1004,
            'message' => [
                'message_id' => 4,
                'chat' => ['id' => 888777666, 'type' => 'private'],
                'from' => ['id' => 888777666, 'username' => 'finance_guy'],
                'text' => '/income 5000 Salary November paycheck',
            ],
        ];

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'test-webhook-secret-token')
            ->postJson(route('telegram.webhook'), $incomeUpdate)
            ->assertOk();

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'amount' => 5000.00,
            'type' => TransactionType::Income->value,
            'category' => 'Salary',
            'note' => 'November paycheck',
        ]);
    }

    public function test_bot_handles_natural_language_heuristic_parsing(): void
    {
        $user = User::factory()->create([
            'telegram_chat_id' => 111222333,
            'currency' => 'CNY',
        ]);

        // "spent 35k on coffee"
        $update1 = [
            'update_id' => 1005,
            'message' => [
                'message_id' => 5,
                'chat' => ['id' => 111222333, 'type' => 'private'],
                'from' => ['id' => 111222333, 'username' => 'coffee_lover'],
                'text' => 'spent 35k on coffee',
            ],
        ];

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'test-webhook-secret-token')
            ->postJson(route('telegram.webhook'), $update1)
            ->assertOk();

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'amount' => 35000,
            'type' => TransactionType::Expense->value,
            'category' => 'Coffee',
        ]);

        // Indonesian: "gajian masuk 2.5jt dari kantor"
        $update2 = [
            'update_id' => 1006,
            'message' => [
                'message_id' => 6,
                'chat' => ['id' => 111222333, 'type' => 'private'],
                'from' => ['id' => 111222333, 'username' => 'coffee_lover'],
                'text' => 'gajian masuk 2.5jt dari kantor',
            ],
        ];

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'test-webhook-secret-token')
            ->postJson(route('telegram.webhook'), $update2)
            ->assertOk();

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'amount' => 2500000,
            'type' => TransactionType::Income->value,
        ]);
    }

    public function test_bot_handles_balance_and_recent_commands(): void
    {
        $user = User::factory()->create([
            'telegram_chat_id' => 123123123,
            'currency' => 'CNY',
        ]);

        Transaction::factory()->create([
            'user_id' => $user->id,
            'amount' => 200,
            'type' => TransactionType::Expense,
            'category' => 'Food',
            'occurred_at' => now(),
        ]);

        // /balance
        $balanceUpdate = [
            'update_id' => 1007,
            'message' => [
                'message_id' => 7,
                'chat' => ['id' => 123123123, 'type' => 'private'],
                'text' => '/balance',
            ],
        ];

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'test-webhook-secret-token')
            ->postJson(route('telegram.webhook'), $balanceUpdate)
            ->assertOk();

        // /recent
        $recentUpdate = [
            'update_id' => 1008,
            'message' => [
                'message_id' => 8,
                'chat' => ['id' => 123123123, 'type' => 'private'],
                'text' => '/recent',
            ],
        ];

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'test-webhook-secret-token')
            ->postJson(route('telegram.webhook'), $recentUpdate)
            ->assertOk();
    }

    public function test_bot_mini_app_auth_with_valid_init_data(): void
    {
        $botToken = '123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ';
        $telegramId = 777888999;

        $user = User::factory()->create([
            'telegram_chat_id' => $telegramId,
            'email' => 'tma@financetracker.local',
        ]);

        $authDate = (string) time();
        $userJson = json_encode([
            'id' => $telegramId,
            'first_name' => 'Tma',
            'username' => 'tma_user',
        ]);

        // Construct Telegram WebApp HMAC
        $params = [
            'auth_date' => $authDate,
            'user' => $userJson,
        ];
        ksort($params);

        $dataCheckString = "auth_date={$authDate}\nuser={$userJson}";
        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $hash = hash_hmac('sha256', $dataCheckString, $secretKey);

        $initData = "auth_date={$authDate}&user=".urlencode($userJson)."&hash={$hash}";

        $response = $this->postJson(route('telegram.mini-app.auth'), [
            'init_data' => $initData,
        ]);

        $response->assertOk()
            ->assertJson([
                'ok' => true,
                'redirect' => route('dashboard'),
                'user' => [
                    'email' => 'tma@financetracker.local',
                ],
            ]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_bot_mini_app_auth_rejects_invalid_signature(): void
    {
        $response = $this->postJson(route('telegram.mini-app.auth'), [
            'init_data' => 'auth_date=1700000000&user=%7B%22id%22%3A123%7D&hash=invalid_hash',
        ]);

        $response->assertStatus(401)
            ->assertJson(['error' => 'Invalid or expired Telegram signature']);

        $this->assertGuest();
    }

    public function test_webhook_rejects_unauthorized_secret_header(): void
    {
        $update = [
            'update_id' => 1009,
            'message' => [
                'message_id' => 9,
                'chat' => ['id' => 12345, 'type' => 'private'],
                'text' => '/help',
            ],
        ];

        // Missing header
        $this->postJson(route('telegram.webhook'), $update)
            ->assertStatus(401);

        // Wrong header
        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'wrong-secret')
            ->postJson(route('telegram.webhook'), $update)
            ->assertStatus(401);
    }

    public function test_start_and_help_omit_web_app_button_when_app_url_is_http_and_include_it_when_https(): void
    {
        $user = User::factory()->create([
            'telegram_chat_id' => 321321321,
        ]);

        // HTTP URL: should NOT include reply_markup / web_app button
        Config::set('app.url', 'http://localhost:8000');

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'test-webhook-secret-token')
            ->postJson(route('telegram.webhook'), [
                'update_id' => 1010,
                'message' => [
                    'message_id' => 10,
                    'chat' => ['id' => 321321321, 'type' => 'private'],
                    'text' => '/start',
                ],
            ])
            ->assertOk();

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'test-webhook-secret-token')
            ->postJson(route('telegram.webhook'), [
                'update_id' => 1011,
                'message' => [
                    'message_id' => 11,
                    'chat' => ['id' => 321321321, 'type' => 'private'],
                    'text' => '/help',
                ],
            ])
            ->assertOk();

        Http::assertSent(function ($request): bool {
            if (! str_ends_with($request->url(), '/sendMessage')) {
                return false;
            }

            $data = $request->data();

            return (int) ($data['chat_id'] ?? 0) === 321321321
                && ! array_key_exists('reply_markup', $data);
        });

        // HTTPS URL: should include web_app button in reply_markup
        Config::set('app.url', 'https://finance.example.com');

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'test-webhook-secret-token')
            ->postJson(route('telegram.webhook'), [
                'update_id' => 1012,
                'message' => [
                    'message_id' => 12,
                    'chat' => ['id' => 321321321, 'type' => 'private'],
                    'text' => '/start',
                ],
            ])
            ->assertOk();

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'test-webhook-secret-token')
            ->postJson(route('telegram.webhook'), [
                'update_id' => 1013,
                'message' => [
                    'message_id' => 13,
                    'chat' => ['id' => 321321321, 'type' => 'private'],
                    'text' => '/help',
                ],
            ])
            ->assertOk();

        Http::assertSent(function ($request): bool {
            if (! str_ends_with($request->url(), '/sendMessage')) {
                return false;
            }

            $data = $request->data();
            $markup = isset($data['reply_markup']) ? json_decode((string) $data['reply_markup'], true) : null;
            $webAppUrl = $markup['inline_keyboard'][0][0]['web_app']['url'] ?? null;

            return (int) ($data['chat_id'] ?? 0) === 321321321
                && $webAppUrl === 'https://finance.example.com';
        });
    }

    public function test_balance_command_excludes_mismatched_base_currency_transactions(): void
    {
        $user = User::factory()->create([
            'telegram_chat_id' => 654654654,
            'currency' => 'CNY',
        ]);

        // Matching base currency transaction (should be counted)
        Transaction::factory()->create([
            'user_id' => $user->id,
            'amount' => 200,
            'currency' => 'CNY',
            'base_amount' => 200,
            'base_currency' => 'CNY',
            'type' => TransactionType::Expense,
            'category' => 'Food',
            'occurred_at' => now(),
        ]);

        // Mismatched base currency transaction (must NOT be summed into CNY totals)
        Transaction::factory()->create([
            'user_id' => $user->id,
            'amount' => 50,
            'currency' => 'USD',
            'base_amount' => 50,
            'base_currency' => 'USD',
            'type' => TransactionType::Expense,
            'category' => 'Travel',
            'occurred_at' => now(),
        ]);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'test-webhook-secret-token')
            ->postJson(route('telegram.webhook'), [
                'update_id' => 1014,
                'message' => [
                    'message_id' => 14,
                    'chat' => ['id' => 654654654, 'type' => 'private'],
                    'text' => '/balance',
                ],
            ])
            ->assertOk();

        Http::assertSent(function ($request): bool {
            if (! str_ends_with($request->url(), '/sendMessage')) {
                return false;
            }

            $data = $request->data();
            $text = (string) ($data['text'] ?? '');

            return (int) ($data['chat_id'] ?? 0) === 654654654
                && str_contains($text, 'Expenses: <b>200.00 CNY</b>')
                && str_contains($text, 'Net Balance: <b>-200.00 CNY</b>')
                && str_contains($text, '1 unconverted transaction(s) excluded.');
        });
    }
}
