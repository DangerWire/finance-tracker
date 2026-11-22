<?php

namespace Tests\Feature;

use App\Enums\DebtStatus;
use App\Enums\TransactionType;
use App\Models\Budget;
use App\Models\Debt;
use App\Models\Transaction;
use App\Models\TransactionImport;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\StructuredResponseFake;
use Tests\TestCase;

class TelegramAdvancedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        Config::set('services.telegram.bot_token', '123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ');
        Config::set('services.telegram.bot_username', 'FinanceTrackerBot');
        Config::set('services.telegram.webhook_secret', 'test-webhook-secret-token');
        Config::set('insights.api_key', null);

        Http::swap(new HttpFactory);

        Http::fake([
            'https://api.telegram.org/bot*/getMe' => Http::response([
                'ok' => true,
                'result' => ['id' => 123, 'is_bot' => true, 'username' => 'CojavpnBot'],
            ], 200),
            'https://api.telegram.org/bot*/getWebhookInfo' => Http::response([
                'ok' => true,
                'result' => ['url' => '', 'pending_update_count' => 0],
            ], 200),
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 99]], 200),
        ]);
    }

    public function test_budgets_command_renders_empty_and_populated_progress(): void
    {
        $user = User::factory()->create([
            'telegram_chat_id' => 500001,
            'currency' => 'CNY',
        ]);

        // Empty state first
        $this->postWebhook([
            'update_id' => 2001,
            'message' => [
                'message_id' => 1,
                'chat' => ['id' => 500001, 'type' => 'private'],
                'text' => '/budgets',
            ],
        ])->assertOk();

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return str_ends_with($request->url(), '/sendMessage')
                && (int) ($data['chat_id'] ?? 0) === 500001
                && str_contains((string) ($data['text'] ?? ''), 'No budgets set for this month yet');
        });

        // Populated budgets
        $now = CarbonImmutable::now();
        Budget::factory()->create([
            'user_id' => $user->id,
            'category' => 'Groceries',
            'amount' => 1000.00,
            'period_month' => $now->format('Y-m'),
        ]);

        Transaction::factory()->create([
            'user_id' => $user->id,
            'type' => TransactionType::Expense,
            'category' => 'Groceries',
            'amount' => 850.00,
            'currency' => 'CNY',
            'base_amount' => 850.00,
            'base_currency' => 'CNY',
            'occurred_at' => $now->startOfMonth()->addHours(10),
        ]);

        $this->postWebhook([
            'update_id' => 2002,
            'message' => [
                'message_id' => 2,
                'chat' => ['id' => 500001, 'type' => 'private'],
                'text' => '/budgets',
            ],
        ])->assertOk();

        Http::assertSent(function ($request): bool {
            $data = $request->data();
            $text = (string) ($data['text'] ?? '');

            return str_ends_with($request->url(), '/sendMessage')
                && (int) ($data['chat_id'] ?? 0) === 500001
                && str_contains($text, 'Groceries')
                && str_contains($text, '850.00 / 1,000.00 CNY')
                && str_contains($text, '85.0%');
        });
    }

    public function test_proactive_budget_alerts_fire_at_80_and_100_percent_without_duplicates_via_bot_and_web(): void
    {
        $user = User::factory()->create([
            'telegram_chat_id' => 500002,
            'currency' => 'CNY',
        ]);

        $month = CarbonImmutable::now()->format('Y-m');
        Budget::factory()->create([
            'user_id' => $user->id,
            'category' => 'Dining',
            'amount' => 100.00,
            'period_month' => $month,
        ]);

        // 1. Web POST creates 80 CNY Dining expense -> triggers 80% warning alert once
        $this->actingAs($user)
            ->post(route('transactions.store'), [
                'type' => TransactionType::Expense->value,
                'amount' => '80.00',
                'currency' => 'CNY',
                'occurred_at' => CarbonImmutable::now()->format('Y-m-d\TH:i'),
                'category' => 'Dining',
                'note' => 'First dinner',
            ])
            ->assertRedirect(route('transactions.index'));

        // 2. Second Web POST (85 CNY -> 85%) stays at 'warning' level -> MUST NOT duplicate warning alert!
        $this->actingAs($user)
            ->post(route('transactions.store'), [
                'type' => TransactionType::Expense->value,
                'amount' => '5.00',
                'currency' => 'CNY',
                'occurred_at' => CarbonImmutable::now()->format('Y-m-d\TH:i'),
                'category' => 'Dining',
                'note' => 'Snack',
            ])
            ->assertRedirect(route('transactions.index'));

        // 3. Telegram bot /expense pushes total to 105% -> triggers 100%+ exceeded alert
        $this->postWebhook([
            'update_id' => 2003,
            'message' => [
                'message_id' => 3,
                'chat' => ['id' => 500002, 'type' => 'private'],
                'text' => '/expense 20 Dining Coffee',
            ],
        ])->assertOk();

        $warningAlerts = 0;
        $exceededAlerts = 0;

        Http::assertSent(function ($request) use (&$warningAlerts, &$exceededAlerts): bool {
            if (! str_ends_with($request->url(), '/sendMessage')) {
                return true;
            }
            $data = $request->data();
            $text = (string) ($data['text'] ?? '');

            if (str_contains($text, 'Budget Warning Alert')) {
                $warningAlerts++;
            }
            if (str_contains($text, 'Budget Exceeded Alert!')) {
                $exceededAlerts++;
            }

            return true;
        });

        $this->assertSame(1, $warningAlerts);
        $this->assertSame(1, $exceededAlerts);
    }

    public function test_debts_command_displays_inline_actions_and_callback_workflow_settles_debt(): void
    {
        $lender = User::factory()->create([
            'name' => 'Alice',
            'telegram_chat_id' => 600001,
            'currency' => 'CNY',
        ]);
        $borrower = User::factory()->create([
            'name' => 'Bob',
            'telegram_chat_id' => 600002,
            'currency' => 'CNY',
        ]);

        // Alice proposed lending 250 CNY to Bob (PendingAcceptance)
        $debt = Debt::factory()->create([
            'creator_id' => $lender->id,
            'lender_id' => $lender->id,
            'borrower_id' => $borrower->id,
            'amount' => 250.00,
            'currency' => 'CNY',
            'note' => 'Concert ticket',
            'status' => DebtStatus::PendingAcceptance,
        ]);

        // Bob runs /debts -> should receive Accept/Decline buttons
        $this->postWebhook([
            'update_id' => 2004,
            'message' => [
                'message_id' => 10,
                'chat' => ['id' => 600002, 'type' => 'private'],
                'text' => '/debts',
            ],
        ])->assertOk();

        Http::assertSent(function ($request) use ($debt): bool {
            if (! str_ends_with($request->url(), '/sendMessage')) {
                return false;
            }
            $data = $request->data();
            $markup = isset($data['reply_markup']) ? json_decode((string) $data['reply_markup'], true) : [];
            $callbackData = $markup['inline_keyboard'][0][0]['callback_data'] ?? '';

            return (int) ($data['chat_id'] ?? 0) === 600002
                && $callbackData === "debt:accept:{$debt->id}";
        });

        // Bob accepts via callback_query
        $this->postWebhook([
            'update_id' => 2005,
            'callback_query' => [
                'id' => 'cb_1',
                'from' => ['id' => 600002, 'username' => 'bob'],
                'message' => ['message_id' => 50, 'chat' => ['id' => 600002, 'type' => 'private']],
                'data' => "debt:accept:{$debt->id}",
            ],
        ])->assertOk();

        $this->assertSame(DebtStatus::Active, $debt->fresh()->status);

        // Unauthorized: Bob tries to confirm receipt (only lender Alice can confirm) -> ignored
        $this->postWebhook([
            'update_id' => 2006,
            'callback_query' => [
                'id' => 'cb_unauth',
                'from' => ['id' => 600002, 'username' => 'bob'],
                'message' => ['message_id' => 50, 'chat' => ['id' => 600002, 'type' => 'private']],
                'data' => "debt:confirm:{$debt->id}",
            ],
        ])->assertOk();

        $this->assertSame(DebtStatus::Active, $debt->fresh()->status);

        // Bob marks as paid via callback_query
        $this->postWebhook([
            'update_id' => 2007,
            'callback_query' => [
                'id' => 'cb_2',
                'from' => ['id' => 600002, 'username' => 'bob'],
                'message' => ['message_id' => 51, 'chat' => ['id' => 600002, 'type' => 'private']],
                'data' => "debt:pay:{$debt->id}",
            ],
        ])->assertOk();

        $this->assertSame(DebtStatus::PaymentSubmitted, $debt->fresh()->status);

        // Alice disputes first, returning to Active
        $this->postWebhook([
            'update_id' => 2008,
            'callback_query' => [
                'id' => 'cb_3',
                'from' => ['id' => 600001, 'username' => 'alice'],
                'message' => ['message_id' => 52, 'chat' => ['id' => 600001, 'type' => 'private']],
                'data' => "debt:dispute:{$debt->id}",
            ],
        ])->assertOk();

        $this->assertSame(DebtStatus::Active, $debt->fresh()->status);

        // Bob marks paid again, and Alice confirms receipt -> triggers SettleDebtAction
        $this->postWebhook([
            'update_id' => 2009,
            'callback_query' => [
                'id' => 'cb_4',
                'from' => ['id' => 600002],
                'message' => ['message_id' => 53, 'chat' => ['id' => 600002, 'type' => 'private']],
                'data' => "debt:pay:{$debt->id}",
            ],
        ])->assertOk();

        $this->postWebhook([
            'update_id' => 2010,
            'callback_query' => [
                'id' => 'cb_5',
                'from' => ['id' => 600001],
                'message' => ['message_id' => 54, 'chat' => ['id' => 600001, 'type' => 'private']],
                'data' => "debt:confirm:{$debt->id}",
            ],
        ])->assertOk();

        $settled = $debt->fresh();
        $this->assertSame(DebtStatus::Settled, $settled->status);
        $this->assertNotNull($settled->lender_transaction_id);
        $this->assertNotNull($settled->borrower_transaction_id);

        $this->assertDatabaseHas('transactions', [
            'id' => $settled->lender_transaction_id,
            'user_id' => $lender->id,
            'type' => TransactionType::Income->value,
            'amount' => 250.00,
        ]);
        $this->assertDatabaseHas('transactions', [
            'id' => $settled->borrower_transaction_id,
            'user_id' => $borrower->id,
            'type' => TransactionType::Expense->value,
            'amount' => 250.00,
        ]);

        // Also test decline flow cleanly
        $declinable = Debt::factory()->create([
            'creator_id' => $lender->id,
            'lender_id' => $lender->id,
            'borrower_id' => $borrower->id,
            'amount' => 75.00,
            'currency' => 'CNY',
            'status' => DebtStatus::PendingAcceptance,
        ]);

        $this->postWebhook([
            'update_id' => 2011,
            'callback_query' => [
                'id' => 'cb_6',
                'from' => ['id' => 600002],
                'message' => ['message_id' => 55, 'chat' => ['id' => 600002, 'type' => 'private']],
                'data' => "debt:decline:{$declinable->id}",
            ],
        ])->assertOk();

        $this->assertSame(DebtStatus::Declined, $declinable->fresh()->status);
    }

    public function test_photo_receipt_scanning_logs_transaction_with_gemini_vision(): void
    {
        Config::set('insights.api_key', 'test-gemini-key');

        $user = User::factory()->create([
            'telegram_chat_id' => 700001,
            'currency' => 'CNY',
        ]);

        Http::swap(new HttpFactory);

        Http::fake([
            'https://api.telegram.org/bot*/getFile*' => Http::response([
                'ok' => true,
                'result' => [
                    'file_id' => 'photo_large_id',
                    'file_size' => 2048,
                    'file_path' => 'photos/receipt_01.jpg',
                ],
            ], 200),
            'https://api.telegram.org/file/bot*' => Http::response('fake-jpeg-bytes', 200, [
                'Content-Type' => 'image/jpeg',
            ]),
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
        ]);

        Prism::fake([
            StructuredResponseFake::make()->withStructured([
                'type' => 'expense',
                'amount' => 68.50,
                'category' => 'Food & Dining',
                'note' => 'Starbucks: Latte & Croissant',
                'occurred_date' => '2026-11-21',
            ]),
        ]);

        $this->postWebhook([
            'update_id' => 2012,
            'message' => [
                'message_id' => 20,
                'chat' => ['id' => 700001, 'type' => 'private'],
                'caption' => 'Morning coffee',
                'photo' => [
                    ['file_id' => 'photo_small_id', 'width' => 90, 'height' => 90],
                    ['file_id' => 'photo_large_id', 'width' => 800, 'height' => 600],
                ],
            ],
        ])->assertOk();

        $transaction = Transaction::where('user_id', $user->id)->firstOrFail();

        $this->assertSame(68.50, (float) $transaction->amount);
        $this->assertSame(TransactionType::Expense, $transaction->type);
        $this->assertSame('Food & Dining', $transaction->category);
        $this->assertSame('Starbucks: Latte & Croissant', $transaction->note);
        $this->assertSame('2026-11-21', $transaction->occurred_at->format('Y-m-d'));
        $this->assertSame(68.50, (float) $transaction->base_amount);
        $this->assertSame('CNY', $transaction->base_currency);

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return str_ends_with($request->url(), '/sendMessage')
                && (int) ($data['chat_id'] ?? 0) === 700001
                && str_contains((string) ($data['text'] ?? ''), 'Receipt Scanned');
        });
    }

    public function test_telegram_webhook_command_info_set_and_delete(): void
    {
        $this->artisan('telegram:webhook', ['action' => 'info'])
            ->expectsOutputToContain('Bot Identity: @CojavpnBot')
            ->assertExitCode(0);

        // HTTP URL rejected cleanly
        $this->artisan('telegram:webhook', [
            'action' => 'set',
            '--url' => 'http://localhost:8000/api/telegram/webhook',
        ])->assertExitCode(1);

        // HTTPS URL succeeds
        $this->artisan('telegram:webhook', [
            'action' => 'set',
            '--url' => 'https://finance.example.com/api/telegram/webhook',
        ])->expectsOutputToContain('Telegram webhook registered at: https://finance.example.com/api/telegram/webhook')
            ->assertExitCode(0);

        $this->artisan('telegram:webhook', ['action' => 'delete'])
            ->expectsOutputToContain('Telegram webhook removed successfully.')
            ->assertExitCode(0);
    }

    public function test_import_command_and_non_csv_document_rejection(): void
    {
        $user = User::factory()->create([
            'telegram_chat_id' => 800001,
            'currency' => 'CNY',
        ]);

        // /import shows guide
        $this->postWebhook([
            'update_id' => 2020,
            'message' => [
                'message_id' => 30,
                'chat' => ['id' => 800001, 'type' => 'private'],
                'text' => '/import',
            ],
        ])->assertOk();

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return str_ends_with($request->url(), '/sendMessage')
                && (int) ($data['chat_id'] ?? 0) === 800001
                && str_contains((string) ($data['text'] ?? ''), 'CSV Bulk Import via Telegram');
        });

        // Uploading a PDF document is rejected cleanly
        $this->postWebhook([
            'update_id' => 2021,
            'message' => [
                'message_id' => 31,
                'chat' => ['id' => 800001, 'type' => 'private'],
                'document' => [
                    'file_id' => 'pdf_file_id',
                    'file_name' => 'statement.pdf',
                    'mime_type' => 'application/pdf',
                    'file_size' => 1024,
                ],
            ],
        ])->assertOk();

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return str_ends_with($request->url(), '/sendMessage')
                && (int) ($data['chat_id'] ?? 0) === 800001
                && str_contains((string) ($data['text'] ?? ''), 'Unsupported File Format');
        });
    }

    public function test_csv_document_upload_preview_confirm_force_cancel_and_ownership_guard(): void
    {
        Storage::fake('local');
        config(['finance.base_currency' => 'CNY']);

        $owner = User::factory()->create([
            'telegram_chat_id' => 800010,
            'currency' => 'CNY',
        ]);
        $stranger = User::factory()->create([
            'telegram_chat_id' => 800011,
            'currency' => 'CNY',
        ]);

        // Pre-existing transaction that will be flagged as duplicate
        Transaction::factory()->create([
            'user_id' => $owner->id,
            'type' => TransactionType::Expense,
            'amount' => 25.00,
            'currency' => 'CNY',
            'base_amount' => 25.00,
            'base_currency' => 'CNY',
            'category' => 'Food',
            'note' => 'Lunch',
            'occurred_at' => CarbonImmutable::parse('2026-10-01 00:00:00'),
        ]);

        $csvBody = "Date,Amount,Type,Category,Note\n2026-10-01,25.00,expense,Food,Lunch\n2026-10-02,900.00,income,Salary,October Pay\n";

        Http::swap(new HttpFactory);
        Http::fake([
            'https://api.telegram.org/bot*/getFile*' => Http::response([
                'ok' => true,
                'result' => [
                    'file_id' => 'csv_doc_id',
                    'file_size' => strlen($csvBody),
                    'file_path' => 'documents/october.csv',
                ],
            ], 200),
            'https://api.telegram.org/file/bot*' => Http::response($csvBody, 200, [
                'Content-Type' => 'text/csv',
            ]),
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 88]], 200),
        ]);

        // 1. Upload CSV -> stages preview without creating transactions yet
        $this->postWebhook([
            'update_id' => 2022,
            'message' => [
                'message_id' => 40,
                'chat' => ['id' => 800010, 'type' => 'private'],
                'document' => [
                    'file_id' => 'csv_doc_id',
                    'file_name' => 'october.csv',
                    'mime_type' => 'text/csv',
                    'file_size' => strlen($csvBody),
                ],
            ],
        ])->assertOk();

        // Only the 1 pre-existing row exists until user confirms
        $this->assertSame(1, $owner->transactions()->count());
        $stagedFiles = Storage::disk('local')->files('imports');
        $this->assertCount(1, $stagedFiles);
        $stagingKey = pathinfo($stagedFiles[0], PATHINFO_FILENAME);

        Http::assertSent(function ($request) use ($stagingKey): bool {
            if (! str_ends_with($request->url(), '/sendMessage')) {
                return false;
            }
            $data = $request->data();
            $text = (string) ($data['text'] ?? '');
            $markup = isset($data['reply_markup']) ? json_decode((string) $data['reply_markup'], true) : [];

            return (int) ($data['chat_id'] ?? 0) === 800010
                && str_contains($text, 'CSV Import Preview')
                && str_contains($text, 'Ready to import: <b>1</b>')
                && str_contains($text, 'Duplicates detected: <b>1</b>')
                && ($markup['inline_keyboard'][0][0]['callback_data'] ?? '') === "import:confirm:{$stagingKey}"
                && ($markup['inline_keyboard'][1][0]['callback_data'] ?? '') === "import:force:{$stagingKey}"
                && ($markup['inline_keyboard'][2][0]['callback_data'] ?? '') === "import:cancel:{$stagingKey}";
        });

        // 2. Stranger cannot confirm owner's staged import
        $this->postWebhook([
            'update_id' => 2023,
            'callback_query' => [
                'id' => 'cb_stranger',
                'from' => ['id' => 800011],
                'message' => ['message_id' => 88, 'chat' => ['id' => 800011, 'type' => 'private']],
                'data' => "import:confirm:{$stagingKey}",
            ],
        ])->assertOk();

        $this->assertSame(1, $owner->transactions()->count());
        Storage::disk('local')->assertExists($stagedFiles[0]);

        // 3. Owner confirms -> imports ONLY the 1 new row (skips duplicate), creates TransactionImport batch, clears staged file
        $this->postWebhook([
            'update_id' => 2024,
            'callback_query' => [
                'id' => 'cb_confirm',
                'from' => ['id' => 800010],
                'message' => ['message_id' => 88, 'chat' => ['id' => 800010, 'type' => 'private']],
                'data' => "import:confirm:{$stagingKey}",
            ],
        ])->assertOk();

        $this->assertSame(2, $owner->transactions()->count());
        $this->assertDatabaseHas('transactions', [
            'user_id' => $owner->id,
            'type' => TransactionType::Income->value,
            'amount' => 900.00,
            'base_amount' => 900.00,
            'base_currency' => 'CNY',
            'note' => 'October Pay',
        ]);
        $this->assertSame(1, TransactionImport::where('user_id', $owner->id)->count());
        Storage::disk('local')->assertMissing($stagedFiles[0]);

        // 4. Upload again and test force import (includes duplicates)
        $this->postWebhook([
            'update_id' => 2025,
            'message' => [
                'message_id' => 41,
                'chat' => ['id' => 800010, 'type' => 'private'],
                'document' => [
                    'file_id' => 'csv_doc_id',
                    'file_name' => 'october.csv',
                    'mime_type' => 'text/csv',
                    'file_size' => strlen($csvBody),
                ],
            ],
        ])->assertOk();

        $stagedFiles2 = Storage::disk('local')->files('imports');
        $key2 = pathinfo($stagedFiles2[0], PATHINFO_FILENAME);

        $this->postWebhook([
            'update_id' => 2026,
            'callback_query' => [
                'id' => 'cb_force',
                'from' => ['id' => 800010],
                'message' => ['message_id' => 89, 'chat' => ['id' => 800010, 'type' => 'private']],
                'data' => "import:force:{$key2}",
            ],
        ])->assertOk();

        // Both rows force-imported -> 2 + 2 = 4 total
        $this->assertSame(4, $owner->transactions()->count());
        Storage::disk('local')->assertMissing($stagedFiles2[0]);

        // 5. Upload again and test cancel
        $this->postWebhook([
            'update_id' => 2027,
            'message' => [
                'message_id' => 42,
                'chat' => ['id' => 800010, 'type' => 'private'],
                'document' => [
                    'file_id' => 'csv_doc_id',
                    'file_name' => 'october.csv',
                    'mime_type' => 'text/csv',
                    'file_size' => strlen($csvBody),
                ],
            ],
        ])->assertOk();

        $stagedFiles3 = Storage::disk('local')->files('imports');
        $key3 = pathinfo($stagedFiles3[0], PATHINFO_FILENAME);

        $this->postWebhook([
            'update_id' => 2028,
            'callback_query' => [
                'id' => 'cb_cancel',
                'from' => ['id' => 800010],
                'message' => ['message_id' => 90, 'chat' => ['id' => 800010, 'type' => 'private']],
                'data' => "import:cancel:{$key3}",
            ],
        ])->assertOk();

        $this->assertSame(4, $owner->transactions()->count());
        Storage::disk('local')->assertMissing($stagedFiles3[0]);
    }

    /**
     * @param  array<string, mixed>  $update
     */
    private function postWebhook(array $update)
    {
        return $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'test-webhook-secret-token')
            ->postJson(route('telegram.webhook'), $update);
    }
}
