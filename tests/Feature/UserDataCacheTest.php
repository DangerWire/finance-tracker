<?php

namespace Tests\Feature;

use App\Enums\DebtStatus;
use App\Enums\RecurringFrequency;
use App\Enums\TransactionType;
use App\Models\Budget;
use App\Models\Debt;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\TransactionImport;
use App\Models\TransactionSplit;
use App\Models\User;
use App\Services\UserDataCache;
use Carbon\CarbonImmutable;
use Illuminate\Cache\FileStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class UserDataCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_is_served_from_cache_with_zero_dashboard_queries_on_repeat_visit(): void
    {
        $user = User::factory()->create(['currency' => 'CNY']);

        $user->transactions()->create([
            'type' => TransactionType::Income,
            'amount' => 1000,
            'currency' => 'CNY',
            'base_amount' => 1000,
            'base_currency' => 'CNY',
            'applied_rate' => 1,
            'category' => 'Salary',
            'occurred_at' => now(),
        ]);

        // First visit populates the cache.
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('1,000.00');

        // Second visit should serve dashboard data from cache without querying transactions, budgets, or debts.
        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('1,000.00');

        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        foreach ($queries as $sql) {
            $this->assertStringNotContainsString('transactions', $sql);
            $this->assertStringNotContainsString('budgets', $sql);
            $this->assertStringNotContainsString('debts', $sql);
        }
    }

    public function test_creating_or_deleting_a_transaction_invalidates_the_users_cache_only(): void
    {
        $alice = User::factory()->create(['currency' => 'CNY']);
        $bob = User::factory()->create(['currency' => 'CNY']);
        $cache = app(UserDataCache::class);

        $this->actingAs($alice)->get(route('dashboard'))->assertOk();
        $aliceVersionBefore = $cache->version($alice->id);
        $bobVersionBefore = $cache->version($bob->id);

        $tx = $alice->transactions()->create([
            'type' => TransactionType::Expense,
            'amount' => 125,
            'currency' => 'CNY',
            'base_amount' => 125,
            'base_currency' => 'CNY',
            'applied_rate' => 1,
            'category' => 'Food',
            'occurred_at' => now(),
        ]);

        $this->assertGreaterThan($aliceVersionBefore, $cache->version($alice->id));
        $this->assertSame($bobVersionBefore, $cache->version($bob->id));

        $this->actingAs($alice)->get(route('dashboard'))->assertOk()->assertSee('125.00');

        $versionAfterCreate = $cache->version($alice->id);
        $tx->delete();

        $this->assertGreaterThan($versionAfterCreate, $cache->version($alice->id));
        $this->actingAs($alice)->get(route('dashboard'))->assertOk()->assertDontSee('125.00');
    }

    public function test_budget_and_debt_changes_invalidate_all_affected_users_caches(): void
    {
        $lender = User::factory()->create(['currency' => 'CNY']);
        $borrower = User::factory()->create(['currency' => 'CNY']);
        $cache = app(UserDataCache::class);

        $lenderVersion = $cache->version($lender->id);
        $borrowerVersion = $cache->version($borrower->id);

        Debt::create([
            'creator_id' => $lender->id,
            'lender_id' => $lender->id,
            'borrower_id' => $borrower->id,
            'amount' => 250,
            'currency' => 'CNY',
            'status' => DebtStatus::Active,
        ]);

        $this->assertGreaterThan($lenderVersion, $cache->version($lender->id));
        $this->assertGreaterThan($borrowerVersion, $cache->version($borrower->id));

        $budgetVersion = $cache->version($lender->id);
        Budget::factory()->for($lender)->create([
            'category' => 'Coffee',
            'amount' => 300,
            'period_month' => now()->format('Y-m'),
        ]);

        $this->assertGreaterThan($budgetVersion, $cache->version($lender->id));
    }

    public function test_deleting_an_import_batch_invalidates_cached_dashboard_totals(): void
    {
        $user = User::factory()->create(['currency' => 'CNY']);

        $import = TransactionImport::create([
            'user_id' => $user->id,
            'filename' => 'statement.csv',
            'imported_count' => 1,
        ]);

        Transaction::factory()->for($user)->create([
            'import_id' => $import->id,
            'type' => TransactionType::Expense,
            'amount' => 444.00,
            'currency' => 'CNY',
            'base_amount' => 444.00,
            'base_currency' => 'CNY',
            'applied_rate' => 1,
            'occurred_at' => now(),
        ]);

        // Warm cache with imported transaction visible.
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('444.00');

        // Bulk-delete the import batch through the controller.
        $this->actingAs($user)->delete(route('transaction-imports.destroy', $import))
            ->assertRedirect(route('transaction-imports.index'));

        // Dashboard must immediately reflect the removed amount, not stale cache.
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertDontSee('444.00');
    }

    public function test_dashboard_serves_cached_payload_from_file_store_with_serializable_classes_disabled(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 10:00:00'));

        $this->withIsolatedFileCache(function (string $cacheDir): void {
            $user = User::factory()->create(['currency' => 'CNY']);
            $friend = User::factory()->create(['currency' => 'CNY']);

            Transaction::factory()->for($user)->create([
                'type' => TransactionType::Income,
                'amount' => 2500.00,
                'currency' => 'CNY',
                'base_amount' => 2500.00,
                'base_currency' => 'CNY',
                'applied_rate' => 1,
                'category' => 'Salary',
                'note' => 'June payroll',
                'occurred_at' => '2026-06-10 09:00:00',
            ]);

            Transaction::factory()->for($user)->create([
                'type' => TransactionType::Expense,
                'amount' => 50.00,
                'currency' => 'EUR',
                'base_amount' => 400.00,
                'base_currency' => 'CNY',
                'applied_rate' => 8,
                'category' => 'Travel',
                'note' => 'Airport train',
                'occurred_at' => '2026-06-12 14:30:00',
            ]);

            $splitTransaction = Transaction::factory()->for($user)->create([
                'type' => TransactionType::Expense,
                'amount' => 300.00,
                'currency' => 'CNY',
                'base_amount' => 300.00,
                'base_currency' => 'CNY',
                'applied_rate' => 1,
                'category' => 'Split',
                'note' => 'Supermarket run',
                'occurred_at' => '2026-06-14 18:15:00',
            ]);

            TransactionSplit::factory()->for($splitTransaction)->create([
                'category' => 'Groceries',
                'amount' => 200.00,
                'base_amount' => 200.00,
                'note' => 'Weekly produce',
            ]);

            TransactionSplit::factory()->for($splitTransaction)->create([
                'category' => 'Household',
                'amount' => 100.00,
                'base_amount' => 100.00,
                'note' => 'Supplies',
            ]);

            Budget::factory()->for($user)->create([
                'category' => 'Groceries',
                'amount' => 1000.00,
                'period_month' => '2026-06',
            ]);

            RecurringTransaction::factory()->for($user)->create([
                'type' => TransactionType::Expense,
                'name' => 'Fiber Internet',
                'amount' => 120.00,
                'currency' => 'CNY',
                'category' => 'Utilities',
                'frequency' => RecurringFrequency::Monthly,
                'is_active' => true,
                'starts_on' => '2026-05-20',
                'next_occurrence_on' => '2026-06-20',
            ]);

            Debt::factory()->active()->create([
                'creator_id' => $user->id,
                'lender_id' => $user->id,
                'borrower_id' => $friend->id,
                'amount' => 500.00,
                'currency' => 'CNY',
            ]);

            Debt::factory()->create([
                'creator_id' => $friend->id,
                'lender_id' => $friend->id,
                'borrower_id' => $user->id,
                'amount' => 80.00,
                'currency' => 'CNY',
                'status' => DebtStatus::PendingAcceptance,
            ]);

            $assertDashboardContent = function ($response) use ($splitTransaction): void {
                $response->assertOk()
                    ->assertSee('+1,800.00')
                    ->assertSee('+2,500.00')
                    ->assertSee('−700.00')
                    ->assertSee('June payroll')
                    ->assertSee('Airport train')
                    ->assertSee('−50.00')
                    ->assertSee('−400.00')
                    ->assertSee('Supermarket run')
                    ->assertSee('Split')
                    ->assertSee('(2)')
                    ->assertSee(route('transactions.show', $splitTransaction->id))
                    ->assertSee(route('transactions.edit', $splitTransaction->id))
                    ->assertSee('Groceries')
                    ->assertSee('200 / 1,000')
                    ->assertSee('Fiber Internet')
                    ->assertSee('Jun 20 · Monthly')
                    ->assertSee('120.00')
                    ->assertSee('+500.00')
                    ->assertSee('Pending action (1)');
            };

            // First load populates the real file cache store.
            $assertDashboardContent($this->actingAs($user)->get(route('dashboard')));

            $this->assertCacheDirectoryContainsOnlyPlainValues($cacheDir);

            // Second load must unserialize cleanly with serializable_classes => false and zero dashboard DB queries.
            DB::enableQueryLog();
            DB::flushQueryLog();

            $assertDashboardContent($this->actingAs($user)->get(route('dashboard')));

            $queries = array_column(DB::getQueryLog(), 'query');
            DB::disableQueryLog();

            foreach ($queries as $sql) {
                $this->assertStringNotContainsString('transactions', $sql);
                $this->assertStringNotContainsString('transaction_splits', $sql);
                $this->assertStringNotContainsString('budgets', $sql);
                $this->assertStringNotContainsString('recurring_transactions', $sql);
                $this->assertStringNotContainsString('debts', $sql);
            }
        });
    }

    public function test_transactions_index_serves_cached_summary_and_calendar_from_file_store_in_grouped_and_flat_views(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 10:00:00'));

        $this->withIsolatedFileCache(function (string $cacheDir): void {
            $user = User::factory()->create(['currency' => 'CNY']);

            Transaction::factory()->for($user)->create([
                'type' => TransactionType::Income,
                'amount' => 1500.00,
                'currency' => 'CNY',
                'base_amount' => 1500.00,
                'base_currency' => 'CNY',
                'applied_rate' => 1,
                'category' => 'Salary',
                'note' => 'Mid-month pay',
                'occurred_at' => '2026-06-10 09:00:00',
            ]);

            Transaction::factory()->for($user)->create([
                'type' => TransactionType::Expense,
                'amount' => 220.00,
                'currency' => 'CNY',
                'base_amount' => 220.00,
                'base_currency' => 'CNY',
                'applied_rate' => 1,
                'category' => 'Dining',
                'note' => 'Team dinner',
                'occurred_at' => '2026-06-12 18:30:00',
            ]);

            $splitTransaction = Transaction::factory()->for($user)->create([
                'type' => TransactionType::Expense,
                'amount' => 300.00,
                'currency' => 'CNY',
                'base_amount' => 300.00,
                'base_currency' => 'CNY',
                'applied_rate' => 1,
                'category' => 'Split',
                'note' => 'Weekend market',
                'occurred_at' => '2026-06-15 12:00:00',
            ]);

            TransactionSplit::factory()->for($splitTransaction)->create([
                'category' => 'Groceries',
                'amount' => 180.00,
                'base_amount' => 180.00,
            ]);

            TransactionSplit::factory()->for($splitTransaction)->create([
                'category' => 'Coffee',
                'amount' => 120.00,
                'base_amount' => 120.00,
            ]);

            $assertTransactionsView = function ($response): void {
                $response->assertOk()
                    ->assertSee('+1,500.00')
                    ->assertSee('−520.00')
                    ->assertSee('+980.00')
                    ->assertSee('Mid-month pay')
                    ->assertSee('Team dinner')
                    ->assertSee('Weekend market')
                    ->assertViewHas('activityByDay', function (mixed $activity): bool {
                        return $activity instanceof Collection
                            && $activity->get('2026-06-10') === 1
                            && $activity->get('2026-06-12') === 1
                            && $activity->get('2026-06-15') === 1;
                    });
            };

            // Grouped view: first visit populates file cache; second visit reads cached summary, calendar, and categories.
            $assertTransactionsView($this->actingAs($user)->get(route('transactions.index', ['group' => 1, 'panel' => 1])));

            $this->assertCacheDirectoryContainsOnlyPlainValues($cacheDir);

            DB::enableQueryLog();
            DB::flushQueryLog();

            $assertTransactionsView($this->actingAs($user)->get(route('transactions.index', ['group' => 1, 'panel' => 1])));

            $groupedHitQueries = array_column(DB::getQueryLog(), 'query');
            DB::disableQueryLog();

            foreach ($groupedHitQueries as $sql) {
                $this->assertStringNotContainsString('COALESCE(SUM(CASE', $sql);
                $this->assertStringNotContainsString('count(*) as total', $sql);
            }

            // Flat view: repeat visits also reuse the file-cached summary and calendar without unserialization errors.
            $assertTransactionsView($this->actingAs($user)->get(route('transactions.index', ['group' => 0, 'panel' => 1])));
            $assertTransactionsView($this->actingAs($user)->get(route('transactions.index', ['group' => 0, 'panel' => 1])));

            $this->assertCacheDirectoryContainsOnlyPlainValues($cacheDir);
        });
    }

    public function test_budgets_and_insights_serve_cached_payloads_from_file_store_with_serializable_classes_disabled(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 10:00:00'));

        $this->withIsolatedFileCache(function (string $cacheDir): void {
            $user = User::factory()->create(['currency' => 'CNY']);

            Budget::factory()->for($user)->create([
                'category' => 'Dining',
                'amount' => 800.00,
                'period_month' => '2026-06',
            ]);

            Transaction::factory()->for($user)->create([
                'type' => TransactionType::Expense,
                'amount' => 250.00,
                'currency' => 'CNY',
                'base_amount' => 250.00,
                'base_currency' => 'CNY',
                'applied_rate' => 1,
                'category' => 'Dining',
                'occurred_at' => '2026-06-11 19:00:00',
            ]);

            $this->actingAs($user)->get(route('budgets.index', ['month' => '2026-06']))->assertOk()->assertSee('Dining');
            $this->actingAs($user)->get(route('budgets.index', ['month' => '2026-06']))->assertOk()->assertSee('Dining');

            $this->actingAs($user)->get(route('insights.index'))->assertOk()->assertSee('Dining');
            $this->actingAs($user)->get(route('insights.index'))->assertOk()->assertSee('Dining');

            $this->assertCacheDirectoryContainsOnlyPlainValues($cacheDir);
        });
    }

    /**
     * Execute a callback using the real file cache store backed by an isolated temporary directory.
     *
     * @param  callable(string): void  $callback
     */
    private function withIsolatedFileCache(callable $callback): void
    {
        $this->assertFalse(config('cache.serializable_classes'));

        $tempCacheDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'finance-tracker-cache-'.bin2hex(random_bytes(8));
        File::makeDirectory($tempCacheDir, 0777, true);

        $previousDefault = config('cache.default');
        $previousPath = config('cache.stores.file.path');
        $previousLockPath = config('cache.stores.file.lock_path');

        config([
            'cache.default' => 'file',
            'cache.stores.file.path' => $tempCacheDir,
            'cache.stores.file.lock_path' => $tempCacheDir,
        ]);
        Cache::purge('file');
        Cache::setDefaultDriver('file');

        $this->assertInstanceOf(FileStore::class, Cache::getStore());

        try {
            $callback($tempCacheDir);
        } finally {
            Cache::purge('file');
            config([
                'cache.default' => $previousDefault,
                'cache.stores.file.path' => $previousPath,
                'cache.stores.file.lock_path' => $previousLockPath,
            ]);
            Cache::setDefaultDriver($previousDefault);
            File::deleteDirectory($tempCacheDir);
        }
    }

    /**
     * Assert that every cached payload written to the file store unserializes cleanly
     * with allowed_classes => false and contains no PHP objects or __PHP_Incomplete_Class instances.
     */
    private function assertCacheDirectoryContainsOnlyPlainValues(string $cacheDir): void
    {
        $files = File::allFiles($cacheDir);
        $this->assertNotEmpty($files, 'Expected at least one cache file to be written to the isolated file store.');

        foreach ($files as $file) {
            $raw = File::get($file->getPathname());
            $value = unserialize(substr($raw, 10), ['allowed_classes' => false]);
            $this->assertValueContainsNoObjects($value, $file->getFilename());
        }
    }

    private function assertValueContainsNoObjects(mixed $value, string $context): void
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $this->assertValueContainsNoObjects($item, "{$context}[{$key}]");
            }

            return;
        }

        $this->assertFalse(
            is_object($value),
            sprintf('Cached payload at %s contained serialized object of class %s.', $context, is_object($value) ? $value::class : 'none')
        );
    }
}
