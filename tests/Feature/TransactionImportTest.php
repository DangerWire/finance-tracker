<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\TransactionImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TransactionImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_reach_the_import_page(): void
    {
        $this->get(route('transaction-imports.create'))->assertRedirect(route('login'));
    }

    public function test_preview_guesses_the_mapping_and_reports_counts_without_saving(): void
    {
        config(['finance.base_currency' => 'CNY']);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(
            route('transaction-imports.preview'),
            ['file' => $this->csv("Date,Amount,Note\n2026-10-01,25.00,Lunch\n2026-10-02,-40.50,Coffee\n")],
        );

        $response->assertOk()
            ->assertJsonPath('total_rows', 2)
            ->assertJsonPath('valid_count', 2)
            ->assertJsonPath('mapping.date', 'Date')
            ->assertJsonPath('mapping.amount', 'Amount');

        $this->assertSame(0, Transaction::count());
    }

    public function test_import_creates_transactions_with_a_base_snapshot(): void
    {
        config(['finance.base_currency' => 'CNY']);

        Http::fake();

        $user = User::factory()->create();

        $key = $this->preview($user, "Date,Amount,Type,Note\n2026-10-01,25.00,expense,Lunch\n2026-10-02,900.00,income,Salary\n");

        $this->actingAs($user)
            ->post(route('transaction-imports.store'), ['upload_key' => $key])
            ->assertRedirect(route('transactions.index'));

        $this->assertDatabaseCount('transactions', 2);

        // The snapshot is what keeps imported rows inside every total.
        $this->assertDatabaseHas('transactions', [
            'type' => TransactionType::Expense,
            'amount' => 25.00,
            'currency' => 'CNY',
            'base_amount' => 25.00,
            'base_currency' => 'CNY',
            'applied_rate' => 1,
        ]);

        $this->assertSame(925.0, (float) Transaction::query()->sum('base_amount'));
    }

    public function test_import_converts_foreign_currency_rows(): void
    {
        config(['finance.base_currency' => 'CNY']);

        Http::fake([
            'api.frankfurter.dev/*' => Http::response([
                ['date' => '2026-10-01', 'base' => 'USD', 'quote' => 'CNY', 'rate' => 7.1],
            ]),
        ]);

        $user = User::factory()->create();

        $key = $this->preview($user, "Date,Amount,Currency\n2026-10-01,100.00,USD\n");

        $this->actingAs($user)->post(route('transaction-imports.store'), ['upload_key' => $key]);

        $this->assertDatabaseHas('transactions', [
            'amount' => 100.00,
            'currency' => 'USD',
            'base_amount' => 710.0,
            'base_currency' => 'CNY',
        ]);
    }

    public function test_negative_amounts_become_expenses_when_a_signed_column_is_used(): void
    {
        config(['finance.base_currency' => 'CNY']);

        $user = User::factory()->create();

        $csv = "Date,Amount\n2026-10-01,-25.00\n2026-10-02,900.00\n";

        $preview = $this->actingAs($user)->post(
            route('transaction-imports.preview'),
            ['file' => $this->csv($csv)],
        )->assertOk();

        $key = $preview->json('upload_key');

        // A signed amount column carries direction in the sign.
        $this->actingAs($user)->post(route('transaction-imports.remap'), [
            'upload_key' => $key,
            'mapping' => ['date' => 'Date', 'amount' => 'Amount'],
            'signed_amount' => 'yes',
        ])->assertOk()->assertJsonPath('valid_count', 2);

        $this->actingAs($user)->post(route('transaction-imports.store'), ['upload_key' => $key]);

        $this->assertDatabaseHas('transactions', ['type' => TransactionType::Expense, 'amount' => 25.00]);
        $this->assertDatabaseHas('transactions', ['type' => TransactionType::Income, 'amount' => 900.00]);
    }

    public function test_export_furniture_is_skipped_without_failing_the_batch(): void
    {
        config(['finance.base_currency' => 'CNY']);

        $user = User::factory()->create();

        // Banks append a totals line; the row with no date is not a transaction.
        $key = $this->preview(
            $user,
            "Date,Amount\n2026-10-01,25.00\n2026-10-02,40.00\n,Total: 65.00\n",
        );

        $this->actingAs($user)
            ->post(route('transaction-imports.store'), ['upload_key' => $key])
            ->assertRedirect(route('transactions.index'));

        $this->assertDatabaseCount('transactions', 2);
    }

    public function test_a_quoted_thousands_separator_is_read_as_one_amount(): void
    {
        config(['finance.base_currency' => 'CNY']);

        $user = User::factory()->create();

        // Exports quote the amount so the comma does not split the field.
        $key = $this->preview($user, "Date,Amount\n2026-10-03,\"1,280.50\"\n");

        $this->actingAs($user)->post(route('transaction-imports.store'), ['upload_key' => $key]);

        $this->assertDatabaseHas('transactions', ['amount' => 1280.50]);
    }

    public function test_a_date_without_a_time_is_stored_at_midnight(): void
    {
        config(['finance.base_currency' => 'CNY']);

        $user = User::factory()->create();

        $key = $this->preview($user, "Date,Amount\n2026-10-03,25.00\n");

        $this->actingAs($user)->post(route('transaction-imports.store'), ['upload_key' => $key]);

        // Otherwise every import lands at whatever hour the upload happened,
        // and same-day rows no longer sort in a stable order.
        $this->assertSame(
            '2026-10-03 00:00:00',
            Transaction::firstOrFail()->occurred_at->format('Y-m-d H:i:s')
        );
    }

    public function test_skipped_rows_explain_themselves(): void
    {
        config(['finance.base_currency' => 'CNY']);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(
            route('transaction-imports.preview'),
            ['file' => $this->csv("Date,Amount\n2026-10-01,25.00\n,Total: 25.00\nnot-a-date,10.00\n")],
        );

        $response->assertOk()->assertJsonPath('valid_count', 1);

        $reasons = collect($response->json('invalid'))->pluck('errors.0');

        // A summary line must not become a transaction: "Total: 25.00" is
        // rejected rather than having its label stripped into the number.
        $this->assertTrue($reasons->contains(fn (string $r): bool => str_contains($r, 'is not an amount')));
        $this->assertTrue($reasons->contains(fn (string $r): bool => str_contains($r, 'is not a date')));
    }

    public function test_the_import_page_renders_its_script(): void
    {
        $user = User::factory()->create();

        // The preview UI is driven entirely by this script, so it has to
        // survive into the rendered HTML rather than being pushed to a stack
        // the layout does not render.
        $this->actingAs($user)
            ->get(route('transaction-imports.create'))
            ->assertOk()
            ->assertSee('remapUrl', false)
            ->assertSee('<script>', false);
    }

    public function test_an_import_can_be_removed_entirely(): void
    {
        config(['finance.base_currency' => 'CNY']);

        $user = User::factory()->create();

        $key = $this->preview($user, "Date,Amount\n2026-10-01,25.00\n2026-10-02,40.00\n");

        $this->actingAs($user)->post(route('transaction-imports.store'), ['upload_key' => $key]);

        $import = TransactionImport::firstOrFail();

        $this->assertSame(2, $import->transactions()->count());

        $this->actingAs($user)
            ->delete(route('transaction-imports.destroy', $import))
            ->assertRedirect(route('transaction-imports.index'));

        // This is the recovery path for a file imported twice.
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('transaction_imports', 0);
    }

    public function test_a_user_cannot_remove_another_users_import(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $import = TransactionImport::factory()->for($owner)->create();

        $this->actingAs($other)
            ->delete(route('transaction-imports.destroy', $import))
            ->assertForbidden();

        $this->assertDatabaseHas('transaction_imports', ['id' => $import->id]);
    }

    public function test_imported_rows_are_grouped_under_their_batch(): void
    {
        config(['finance.base_currency' => 'CNY']);

        $user = User::factory()->create();

        $key = $this->preview($user, "Date,Amount\n2026-10-01,25.00\n");

        $this->actingAs($user)->post(route('transaction-imports.store'), ['upload_key' => $key]);

        $import = TransactionImport::firstOrFail();

        $this->assertSame(1, $import->transactions()->count());
        $this->assertSame($import->id, Transaction::firstOrFail()->import_id);
    }

    public function test_storing_without_a_staged_upload_is_refused(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('transaction-imports.create'))
            ->post(route('transaction-imports.store'), ['upload_key' => 'not-a-real-key'])
            ->assertRedirect(route('transaction-imports.create'))
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_the_index_lists_only_the_users_own_imports(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        TransactionImport::factory()->for($user)->create(['filename' => 'mine.csv']);
        TransactionImport::factory()->for($other)->create(['filename' => 'theirs.csv']);

        $this->actingAs($user)
            ->get(route('transaction-imports.index'))
            ->assertOk()
            ->assertViewHas('imports', fn ($imports) => $imports->count() === 1)
            ->assertSee('mine.csv')
            ->assertDontSee('theirs.csv');
    }

    public function test_real_chinese_gb18030_and_indonesian_bank_exports_map_and_import_cleanly(): void
    {
        config(['finance.base_currency' => 'CNY']);

        $user = User::factory()->create(['currency' => 'CNY']);

        // 1. GB18030 Alipay-style CSV with long preamble
        $alipayUtf8 = implode("\r\n", [
            'Alipay Statement',
            'Account: alice@example.com',
            '------------------------------',
            '交易时间,交易分类,收/支,金额,商品说明',
            '2026-10-05 12:15:00,餐饮美食,支出,42.00,午餐',
            '2026-10-06 09:00:00,工资,收入,3000.00,月薪',
        ]);
        $alipayGb = mb_convert_encoding($alipayUtf8, 'GB18030', 'UTF-8');

        $preview1 = $this->actingAs($user)->post(
            route('transaction-imports.preview'),
            ['file' => $this->csv($alipayGb)],
        )->assertOk()
            ->assertJsonPath('valid_count', 2)
            ->assertJsonPath('mapping.date', '交易时间')
            ->assertJsonPath('mapping.amount', '金额')
            ->assertJsonPath('mapping.type', '收/支')
            ->assertJsonPath('mapping.note', '商品说明');

        $this->actingAs($user)
            ->post(route('transaction-imports.store'), ['upload_key' => $preview1->json('upload_key')])
            ->assertRedirect(route('transactions.index'));

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'type' => TransactionType::Expense->value,
            'amount' => 42.00,
            'note' => '午餐',
        ]);

        // 2. Indonesian bank export (d-m-Y + debet/kredit)
        $indoCsv = "Tanggal,Kategori,Jenis,Nominal,Keterangan\n15-10-2026,Makan,Debet,25.00,Nasi Goreng\n16-10-2026,Gaji,Kredit,500.00,Bonus\n";

        $preview2 = $this->actingAs($user)->post(
            route('transaction-imports.preview'),
            ['file' => $this->csv($indoCsv)],
        )->assertOk()
            ->assertJsonPath('valid_count', 2)
            ->assertJsonPath('mapping.date', 'Tanggal')
            ->assertJsonPath('mapping.amount', 'Nominal')
            ->assertJsonPath('mapping.note', 'Keterangan');

        $this->actingAs($user)
            ->post(route('transaction-imports.remap'), [
                'upload_key' => $preview2->json('upload_key'),
                'mapping' => [
                    'date' => 'Tanggal',
                    'amount' => 'Nominal',
                    'type' => 'Jenis',
                    'category' => 'Kategori',
                    'note' => 'Keterangan',
                ],
                'signed_amount' => 'no',
            ])->assertOk()
            ->assertJsonPath('valid_count', 2);

        $this->actingAs($user)
            ->post(route('transaction-imports.store'), ['upload_key' => $preview2->json('upload_key')])
            ->assertRedirect(route('transactions.index'));

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'type' => TransactionType::Expense->value,
            'amount' => 25.00,
            'note' => 'Nasi Goreng',
        ]);
        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'type' => TransactionType::Income->value,
            'amount' => 500.00,
            'note' => 'Bonus',
        ]);
    }

    public function test_duplicate_detection_flags_and_skips_by_default_and_allows_explicit_override(): void
    {
        config(['finance.base_currency' => 'CNY']);

        $user = User::factory()->create(['currency' => 'CNY']);

        // Import first batch cleanly
        $csv = "Date,Amount,Type,Note\n2026-10-01,25.00,expense,Lunch\n2026-10-02,900.00,income,Salary\n";
        $key1 = $this->preview($user, $csv);
        $this->actingAs($user)->post(route('transaction-imports.store'), ['upload_key' => $key1]);
        $this->assertSame(2, $user->transactions()->count());

        // Second upload contains one duplicate ("Lunch") and one new entry ("Coffee")
        $csvWithDuplicate = "Date,Amount,Type,Note\n2026-10-01,25.00,expense,Lunch\n2026-10-03,18.00,expense,Coffee\n";
        $preview = $this->actingAs($user)->post(
            route('transaction-imports.preview'),
            ['file' => $this->csv($csvWithDuplicate)],
        )->assertOk()
            ->assertJsonPath('valid_count', 1)
            ->assertJsonPath('duplicate_count', 1);

        // Default store skips duplicates -> only Coffee added (total 3)
        $this->actingAs($user)
            ->post(route('transaction-imports.store'), [
                'upload_key' => $preview->json('upload_key'),
            ])
            ->assertRedirect(route('transactions.index'));

        $this->assertSame(3, $user->transactions()->count());

        // Explicit skip_duplicates=0 imports even matching duplicate row
        $preview2 = $this->actingAs($user)->post(
            route('transaction-imports.preview'),
            ['file' => $this->csv("Date,Amount,Type,Note\n2026-10-01,25.00,expense,Lunch\n")],
        )->assertOk()
            ->assertJsonPath('valid_count', 0)
            ->assertJsonPath('duplicate_count', 1);

        $this->actingAs($user)
            ->post(route('transaction-imports.store'), [
                'upload_key' => $preview2->json('upload_key'),
                'skip_duplicates' => '0',
            ])
            ->assertRedirect(route('transactions.index'));

        $this->assertSame(4, $user->transactions()->count());
    }

    public function test_prune_stale_imports_command_removes_old_staged_files_and_keeps_fresh_ones(): void
    {
        Storage::fake('local');

        Storage::disk('local')->put('imports/stale.json', '{}');
        Storage::disk('local')->put('imports/fresh.json', '{}');

        touch(Storage::disk('local')->path('imports/stale.json'), now()->subHours(26)->getTimestamp());
        touch(Storage::disk('local')->path('imports/fresh.json'), now()->subHours(2)->getTimestamp());

        $this->artisan('finance:prune-stale-imports', ['--hours' => 24])
            ->expectsOutputToContain('Pruned 1 stale import file(s)')
            ->assertExitCode(0);

        Storage::disk('local')->assertMissing('imports/stale.json');
        Storage::disk('local')->assertExists('imports/fresh.json');
    }

    /**
     * Upload a file and return the staging key the response handed back.
     */
    private function preview(User $user, string $contents): string
    {
        return $this->actingAs($user)
            ->post(route('transaction-imports.preview'), ['file' => $this->csv($contents)])
            ->assertOk()
            ->json('upload_key');
    }

    private function csv(string $contents): UploadedFile
    {
        Storage::fake('local');

        return UploadedFile::fake()->createWithContent('statement.csv', $contents);
    }
}
