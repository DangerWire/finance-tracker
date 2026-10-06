<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

        $response = $this->actingAs($user)->get(route('transactions.index', ['group' => 0]));

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
            ->get(route('transactions.index', ['type' => 'income', 'group' => 0]))
            ->assertOk()
            ->assertViewHas('transactions', fn ($paginator) => $paginator->total() === 1);
    }

    public function test_transaction_in_base_currency_is_stored_without_conversion(): void
    {
        config(['finance.base_currency' => 'IDR']);

        $user = User::factory()->create();

        $this->actingAs($user)->post(route('transactions.store'), [
            'type' => 'expense',
            'amount' => 50000,
            'currency' => 'IDR',
            'occurred_at' => '2026-10-05 12:00:00',
        ]);

        $this->assertDatabaseHas('transactions', [
            'amount' => 50000,
            'base_amount' => 50000,
            'base_currency' => 'IDR',
            'applied_rate' => 1,
        ]);
    }

    public function test_foreign_currency_transaction_is_converted_on_write(): void
    {
        config(['finance.base_currency' => 'IDR']);

        Http::fake([
            'api.frankfurter.dev/*' => Http::response([
                ['date' => '2026-10-05', 'base' => 'CNY', 'quote' => 'IDR', 'rate' => 2670.99],
            ]),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)->post(route('transactions.store'), [
            'type' => 'expense',
            'amount' => 100,
            'currency' => 'CNY',
            'occurred_at' => '2026-10-05 12:00:00',
        ]);

        $this->assertDatabaseHas('transactions', [
            'amount' => 100,
            'currency' => 'CNY',
            'base_amount' => 267099,
            'base_currency' => 'IDR',
        ]);

        // The original amount is never overwritten by the converted figure.
        $this->assertDatabaseHas('transactions', ['amount' => 100, 'currency' => 'CNY']);
    }

    public function test_transaction_in_a_retired_base_currency_is_excluded_from_totals(): void
    {
        config(['finance.base_currency' => 'CNY']);

        $user = User::factory()->create();

        Transaction::factory()->for($user)->create([
            'type' => TransactionType::Expense,
            'amount' => 100,
            'currency' => 'CNY',
            'base_amount' => 100,
            'base_currency' => 'CNY',
            'applied_rate' => 1,
        ]);

        // Converted while IDR was the base currency. The figure is present but
        // denominated in IDR, so summing it into a CNY total would blend two
        // currencies into one number.
        Transaction::factory()->for($user)->create([
            'type' => TransactionType::Expense,
            'amount' => 50,
            'currency' => 'IDR',
            'base_amount' => 133750,
            'base_currency' => 'IDR',
            'applied_rate' => 2675,
        ]);

        $response = $this->actingAs($user)->get(route('transactions.index'));

        $response->assertViewHas('expenses', 100.0);
        $response->assertViewHas('baseCurrency', 'CNY');
        // The stale row is reported so the user knows a backfill is pending.
        $response->assertViewHas('unconvertedCount', 1);
    }

    public function test_totals_are_summed_from_the_base_snapshot(): void
    {
        config(['finance.base_currency' => 'IDR']);

        Http::fake([
            'api.frankfurter.dev/*' => Http::response([
                ['date' => '2026-10-05', 'base' => 'CNY', 'quote' => 'IDR', 'rate' => 2670.99],
            ]),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)->post(route('transactions.store'), [
            'type' => 'income',
            'amount' => 10,
            'currency' => 'CNY',
            'occurred_at' => '2026-10-05 12:00:00',
        ]);

        $this->actingAs($user)->post(route('transactions.store'), [
            'type' => 'expense',
            'amount' => 4,
            'currency' => 'CNY',
            'occurred_at' => '2026-10-05 12:00:00',
        ]);

        $response = $this->actingAs($user)->get(route('transactions.index'));

        $response->assertViewHas('income', 26709.9);
        $response->assertViewHas('expenses', 10683.96);
        $response->assertViewHas('balance', 16025.94);
    }

    public function test_stored_rate_is_not_recomputed_when_totals_are_read(): void
    {
        config(['finance.base_currency' => 'IDR']);

        Http::fake([
            'api.frankfurter.dev/*' => Http::response([
                ['date' => '2026-10-05', 'base' => 'CNY', 'quote' => 'IDR', 'rate' => 2670.99],
            ]),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)->post(route('transactions.store'), [
            'type' => 'income',
            'amount' => 10,
            'currency' => 'CNY',
            'occurred_at' => '2026-10-05 12:00:00',
        ]);

        // Rates move. Reading totals must still reflect the rate at entry time.
        Http::fake([
            'api.frankfurter.dev/*' => Http::response([
                ['date' => '2026-10-06', 'base' => 'CNY', 'quote' => 'IDR', 'rate' => 9999.99],
            ]),
        ]);

        $this->actingAs($user)
            ->get(route('transactions.index'))
            ->assertViewHas('income', 26709.9);
    }

    public function test_unconverted_transaction_is_excluded_from_totals(): void
    {
        config(['finance.base_currency' => 'IDR']);

        Http::fake(['api.frankfurter.dev/*' => Http::response([], 500)]);

        $user = User::factory()->create();

        $this->actingAs($user)->post(route('transactions.store'), [
            'type' => 'expense',
            'amount' => 100,
            'currency' => 'CNY',
            'occurred_at' => '2026-10-05 12:00:00',
        ]);

        $this->assertDatabaseHas('transactions', ['base_amount' => null]);

        $this->actingAs($user)
            ->get(route('transactions.index'))
            ->assertViewHas('expenses', 0.0)
            ->assertViewHas('unconvertedCount', 1);
    }

    public function test_editing_reconverts_the_base_snapshot(): void
    {
        config(['finance.base_currency' => 'IDR']);

        Http::fake([
            'api.frankfurter.dev/*' => Http::response([
                ['date' => '2026-10-05', 'base' => 'CNY', 'quote' => 'IDR', 'rate' => 2670.99],
            ]),
        ]);

        $user = User::factory()->create();
        $transaction = Transaction::factory()->for($user)->create([
            'amount' => 100,
            'currency' => 'CNY',
            'base_amount' => 100,
            'base_currency' => null,
        ]);

        $this->actingAs($user)->put(route('transactions.update', $transaction), [
            'type' => 'expense',
            'amount' => 250,
            'currency' => 'CNY',
            'occurred_at' => '2026-10-05 12:00:00',
        ]);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'amount' => 250,
            'base_amount' => 667747.5,
        ]);
    }

    public function test_category_dropdown_lists_categories_the_user_has_used(): void
    {
        $user = User::factory()->create();

        Transaction::factory()->for($user)->create(['category' => 'groceries']);
        Transaction::factory()->for($user)->create(['category' => 'commuting']);

        $this->actingAs($user)
            ->get(route('transactions.index'))
            ->assertViewHas('categories', ['commuting', 'groceries']);
    }

    public function test_category_dropdown_excludes_other_users_categories(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Transaction::factory()->for($user)->create(['category' => 'mine']);
        Transaction::factory()->for($other)->create(['category' => 'theirs']);

        $this->actingAs($user)
            ->get(route('transactions.index'))
            ->assertViewHas('categories', ['mine']);
    }

    public function test_newly_used_category_appears_in_the_dropdown(): void
    {
        config(['finance.base_currency' => 'IDR']);

        $user = User::factory()->create();

        $this->actingAs($user)->get(route('transactions.index'))
            ->assertViewHas('categories', []);

        $this->actingAs($user)->post(route('transactions.store'), [
            'type' => 'expense',
            'amount' => 100,
            'currency' => 'IDR',
            'occurred_at' => '2026-10-06 12:00:00',
            'category' => 'bubble tea',
        ]);

        // Typing a new category is all that is needed for it to be offered.
        $this->actingAs($user)
            ->get(route('transactions.index'))
            ->assertViewHas('categories', ['bubble tea']);
    }

    public function test_create_form_offers_known_categories(): void
    {
        $user = User::factory()->create();

        Transaction::factory()->for($user)->create(['category' => 'housing']);

        $this->actingAs($user)
            ->get(route('transactions.create'))
            ->assertOk()
            ->assertSee('value="housing"', false);
    }

    public function test_index_can_filter_by_category(): void
    {
        $user = User::factory()->create();

        Transaction::factory()->for($user)->count(2)->create(['category' => 'food']);
        Transaction::factory()->for($user)->create(['category' => 'transport']);

        $this->actingAs($user)
            ->get(route('transactions.index', ['category' => 'food', 'group' => 0]))
            ->assertOk()
            ->assertViewHas('transactions', fn ($paginator) => $paginator->total() === 2);
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

    public function test_it_can_filter_to_a_single_day(): void
    {
        config(['finance.base_currency' => 'CNY']);

        $user = User::factory()->create();

        $this->onDay($user, '2026-10-03', 25.00);
        $this->onDay($user, '2026-10-04', 60.00);

        $response = $this->actingAs($user)
            ->get(route('transactions.index', ['date' => '2026-10-03', 'group' => 0]));

        $response->assertOk()
            ->assertViewHas('transactions', fn ($paginator) => $paginator->total() === 1)
            ->assertViewHas('day', '2026-10-03');
    }

    public function test_a_single_day_includes_entries_later_that_day(): void
    {
        config(['finance.base_currency' => 'CNY']);

        $user = User::factory()->create();

        // Recorded at 23:30, which a bound at start of day would miss.
        Transaction::factory()->for($user)->create([
            'amount' => 25.00,
            'currency' => 'CNY',
            'base_amount' => 25.00,
            'base_currency' => 'CNY',
            'occurred_at' => '2026-10-03 23:30:00',
        ]);

        $this->actingAs($user)
            ->get(route('transactions.index', ['date' => '2026-10-03', 'group' => 0]))
            ->assertViewHas('transactions', fn ($paginator) => $paginator->total() === 1);
    }

    public function test_it_can_filter_to_a_date_range(): void
    {
        config(['finance.base_currency' => 'CNY']);

        $user = User::factory()->create();

        $this->onDay($user, '2026-10-01', 10.00);
        $this->onDay($user, '2026-10-05', 20.00);
        $this->onDay($user, '2026-10-09', 30.00);

        $this->actingAs($user)
            ->get(route('transactions.index', ['from' => '2026-10-01', 'to' => '2026-10-05', 'group' => 0]))
            ->assertOk()
            // The endpoints are inclusive, so both the 1st and the 5th count.
            ->assertViewHas('transactions', fn ($paginator) => $paginator->total() === 2);
    }

    public function test_a_single_day_takes_precedence_over_a_range(): void
    {
        config(['finance.base_currency' => 'CNY']);

        $user = User::factory()->create();

        $this->onDay($user, '2026-10-03', 25.00);
        $this->onDay($user, '2026-10-07', 60.00);

        $this->actingAs($user)
            ->get(route('transactions.index', [
                'date' => '2026-10-03',
                'from' => '2026-10-01',
                'to' => '2026-10-09',
                'group' => 0,
            ]))
            ->assertViewHas('transactions', fn ($paginator) => $paginator->total() === 1);
    }

    public function test_the_calendar_renders_every_day_of_the_month(): void
    {
        config(['finance.base_currency' => 'CNY']);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('transactions.index', ['month' => '2026-10']));

        $response->assertOk();

        // One clickable cell per day, so the picker can be operated entirely by
        // clicking rather than by typing a date.
        foreach (range(1, 31) as $day) {
            $response->assertSee('data-date="2026-10-'.str_pad((string) $day, 2, '0', STR_PAD_LEFT).'"', false);
        }
    }

    public function test_the_calendar_writes_the_selection_into_the_form(): void
    {
        config(['finance.base_currency' => 'CNY']);

        $user = User::factory()->create();

        // The picker is only useful if its selection can reach the server, so
        // the fields it drives have to be inputs of the form it submits.
        $this->actingAs($user)
            ->get(route('transactions.index'))
            ->assertOk()
            ->assertSee('name="date"', false)
            ->assertSee('name="from"', false)
            ->assertSee('name="to"', false)
            ->assertSee("cell.addEventListener('click'", false);
    }

    public function test_the_calendar_marks_a_single_selected_day(): void
    {
        config(['finance.base_currency' => 'CNY']);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('transactions.index', ['date' => '2026-10-06']))
            ->assertOk()
            ->assertSee('name="date" value="2026-10-06"', false)
            ->assertSee('value="2026-10-06"', false);
    }

    public function test_the_calendar_marks_both_ends_of_a_range(): void
    {
        config(['finance.base_currency' => 'CNY']);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('transactions.index', ['from' => '2026-10-02', 'to' => '2026-10-06']))
            ->assertOk()
            ->assertSee('name="from" value="2026-10-02"', false)
            ->assertSee('name="to" value="2026-10-06"', false);
    }

    public function test_the_calendar_opens_on_the_month_holding_the_selection(): void
    {
        config(['finance.base_currency' => 'CNY']);

        $user = User::factory()->create();

        // Otherwise the picker reopens on the current month and the chosen date
        // is nowhere on screen.
        $this->actingAs($user)
            ->get(route('transactions.index', ['date' => '2026-09-14']))
            ->assertOk()
            ->assertSee('selected>Sep 2026', false)
            ->assertSee('data-date="2026-09-14"', false);
    }

    public function test_the_calendar_can_page_to_another_month(): void
    {
        config(['finance.base_currency' => 'CNY']);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('transactions.index', ['month' => '2026-02']))
            ->assertOk()
            ->assertSee('selected>Feb 2026', false)
            ->assertSee('data-date="2026-02-28"', false)
            // February 2026 has 28 days, so a 30th must not exist.
            ->assertDontSee('data-date="2026-02-30"', false);
    }

    public function test_the_panel_stays_open_when_the_month_changes(): void
    {
        config(['finance.base_currency' => 'CNY']);

        $user = User::factory()->create();

        // Paging the calendar changes no filter, so without an explicit marker
        // the panel would collapse mid-interaction and hide the calendar.
        $response = $this->actingAs($user)->get(route('transactions.index', ['month' => '2026-11']));

        $response->assertOk();

        $this->assertPanelIsOpen($response->getContent());
    }

    public function test_month_navigation_carries_the_panel_marker(): void
    {
        config(['finance.base_currency' => 'CNY']);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('transactions.index', ['month' => '2026-10']))
            ->assertOk()
            // Every calendar link keeps the panel open across the navigation.
            ->assertSee('panel=1&amp;month=2026-11', false)
            ->assertSee('panel=1&amp;month=2026-09', false)
            ->assertSee('panel=1&amp;month=2027-10', false)
            ->assertSee('panel=1&amp;month=2025-10', false);
    }

    public function test_the_panel_stays_open_when_the_layout_changes(): void
    {
        config(['finance.base_currency' => 'CNY']);

        $user = User::factory()->create();

        // Changing the layout is also a filter-panel interaction, and it used
        // to collapse the panel because no data filter had changed.
        $response = $this->actingAs($user)->get(route('transactions.index', ['group' => 0, 'panel' => 1]));

        $response->assertOk();

        $this->assertPanelIsOpen($response->getContent());
    }

    public function test_selecting_a_date_keeps_the_panel_open(): void
    {
        config(['finance.base_currency' => 'CNY']);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('transactions.index', ['date' => '2026-10-06']));

        $response->assertOk();

        $this->assertPanelIsOpen($response->getContent());
    }

    public function test_the_panel_is_closed_by_default_and_after_a_reset(): void
    {
        config(['finance.base_currency' => 'CNY']);

        $user = User::factory()->create();

        $default = $this->actingAs($user)->get(route('transactions.index'));
        $default->assertOk();

        $this->assertPanelIsClosed($default->getContent());

        // Reset is the way back to the default view, so it collapses again and
        // drops the marker with it.
        $afterReset = $this->actingAs($user)->get(route('transactions.index', ['group' => 1]));
        $afterReset->assertOk();

        $this->assertPanelIsClosed($afterReset->getContent());
    }

    /**
     * Assert the filter disclosure is expanded, tolerating Blade's whitespace
     * between attributes.
     */
    private function assertPanelIsOpen(string $html): void
    {
        $this->assertMatchesRegularExpression(
            '#<details id="filter-panel"\s+open#',
            $html,
            'The filter panel should be open.',
        );
    }

    /**
     * Assert the filter disclosure is collapsed.
     */
    private function assertPanelIsClosed(string $html): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '#<details id="filter-panel"\s+open#',
            $html,
            'The filter panel should be closed.',
        );
    }

    public function test_the_calendar_offers_year_navigation(): void
    {
        config(['finance.base_currency' => 'CNY']);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('transactions.index', ['month' => '2026-10']))
            ->assertOk()
            // Stepping a year keeps the month and moves across the years.
            ->assertSee('month=2025-10', false)
            ->assertSee('month=2027-10', false)
            ->assertSee('month=2026-09', false)
            ->assertSee('month=2026-11', false);
    }

    public function test_calendar_navigation_drops_the_date_selection(): void
    {
        config(['finance.base_currency' => 'CNY']);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('transactions.index', [
            'month' => '2026-10',
            'date' => '2026-10-06',
        ]));

        $response->assertOk();

        // The selection itself is still applied and shown...
        $response->assertSee('name="date" value="2026-10-06"', false);

        // ...but the navigation links move to another month without carrying
        // it, so browsing does not keep filtering by a date the user can no
        // longer see highlighted.
        foreach (['2025-10', '2027-10', '2026-09', '2026-11'] as $target) {
            $response->assertSee('month='.$target.'"', false);
        }

        $this->assertDoesNotMatchRegularExpression(
            '#month=2026-11&amp;date=#',
            $response->getContent(),
            'Month navigation must not carry the date selection.',
        );
    }

    public function test_the_calendar_marks_days_that_hold_transactions(): void
    {
        config(['finance.base_currency' => 'CNY']);

        $user = User::factory()->create();
        $this->onDay($user, '2026-10-03', 25.00);

        $data = $this->actingAs($user)->get(route('transactions.index', ['month' => '2026-10']))->viewData();

        $this->assertSame(1, $data['activityByDay']['2026-10-03'] ?? null);
        $this->assertArrayNotHasKey('2026-10-04', $data['activityByDay']);
    }

    public function test_calendar_marks_ignore_the_date_filter(): void
    {
        config(['finance.base_currency' => 'CNY']);

        $user = User::factory()->create();
        $this->onDay($user, '2026-10-03', 25.00);
        $this->onDay($user, '2026-10-20', 40.00);

        $data = $this->actingAs($user)->get(route('transactions.index', [
            'month' => '2026-10',
            'from' => '2026-10-01',
            'to' => '2026-10-05',
        ]))->viewData();

        // Only one day is inside the range, but both must be marked: otherwise
        // the dots would only ever show days already selected.
        $this->assertCount(2, $data['activityByDay']);
    }

    public function test_page_scripts_are_actually_rendered(): void
    {
        config(['finance.base_currency' => 'CNY']);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('transactions.index'));

        // A pushed script the layout never renders is dropped silently, which
        // is how the filter auto-submit and the importer preview could both
        // look right in tests while doing nothing in a browser.
        $response->assertOk()
            ->assertSee('id="transaction-filters"', false)
            ->assertSee('filterForm.addEventListener', false);

        $this->assertStringContainsString(
            "@stack('scripts')",
            (string) file_get_contents(resource_path('views/layouts/app.blade.php')),
            'The layout must render the scripts stack.',
        );
    }

    public function test_it_rejects_a_malformed_date_filter(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('transactions.index', ['date' => 'not-a-date']))
            ->assertSessionHasErrors('date');
    }

    public function test_the_list_groups_transactions_by_day(): void
    {
        config(['finance.base_currency' => 'CNY']);

        $user = User::factory()->create();

        $this->onDay($user, '2026-10-03', 25.00);
        $this->onDay($user, '2026-10-03', 30.00);
        $this->onDay($user, '2026-10-04', 60.00);

        $response = $this->actingAs($user)->get(route('transactions.index'));

        $response->assertOk()->assertViewHas('grouped', true);

        $groups = $response->viewData('groups');

        $this->assertCount(2, $groups);
        $this->assertSame('2026-10-04', $groups->keys()->first());
        $this->assertSame(2, $groups['2026-10-03']['count']);
        $this->assertSame(55.0, $groups['2026-10-03']['expenses']);
        $this->assertSame(-55.0, $groups['2026-10-03']['net']);
    }

    public function test_a_days_total_counts_every_transaction_on_that_day(): void
    {
        config(['finance.base_currency' => 'CNY']);

        $user = User::factory()->create();

        $this->onDay($user, '2026-10-03', 25.00);
        $this->onDay($user, '2026-10-03', 30.00);

        // Pagination runs over dates rather than rows precisely so that a
        // group's own figures can never describe only part of the day.
        $this->actingAs($user)->get(route('transactions.index'))
            ->assertViewHas('groups', fn ($groups) => $groups['2026-10-03']['count'] === 2);
    }

    public function test_grouping_respects_the_date_filter(): void
    {
        config(['finance.base_currency' => 'CNY']);

        $user = User::factory()->create();

        $this->onDay($user, '2026-10-03', 25.00);
        $this->onDay($user, '2026-10-08', 60.00);

        $this->actingAs($user)
            ->get(route('transactions.index', ['from' => '2026-10-01', 'to' => '2026-10-04']))
            ->assertViewHas('groups', fn ($groups) => $groups->count() === 1);
    }

    public function test_flat_list_mode_is_preserved(): void
    {
        config(['finance.base_currency' => 'CNY']);

        $user = User::factory()->create();
        $this->onDay($user, '2026-10-03', 25.00);

        $this->actingAs($user)
            ->get(route('transactions.index', ['group' => 0]))
            ->assertOk()
            ->assertViewHas('grouped', false)
            ->assertViewHas('transactions', fn ($paginator) => $paginator->total() === 1);
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

    /**
     * Record one expense in the base currency on a given day.
     */
    private function onDay(User $user, string $day, float $amount): Transaction
    {
        return Transaction::factory()->for($user)->create([
            'type' => TransactionType::Expense,
            'amount' => $amount,
            'currency' => 'CNY',
            'base_amount' => $amount,
            'base_currency' => 'CNY',
            'applied_rate' => 1,
            'occurred_at' => $day.' 12:00:00',
        ]);
    }
}
