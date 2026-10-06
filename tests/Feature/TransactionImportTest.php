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
