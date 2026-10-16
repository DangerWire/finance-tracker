<?php

namespace Tests\Feature;

use App\Enums\RecurringFrequency;
use App\Enums\TransactionType;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\UpcomingBillNotification;
use App\Services\RecurringTransactionRunner;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RecurringTransactionTest extends TestCase
{
    use RefreshDatabase;

    // --- Generation -------------------------------------------------------

    public function test_it_posts_a_due_occurrence_as_a_transaction(): void
    {
        $user = User::factory()->create();

        $template = RecurringTransaction::factory()->for($user)->create([
            'starts_on' => '2026-10-01',
            'next_occurrence_on' => '2026-10-01',
            'frequency' => RecurringFrequency::Monthly,
        ]);

        app(RecurringTransactionRunner::class)->run($user, CarbonImmutable::parse('2026-10-01'));

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'recurring_transaction_id' => $template->id,
            'amount' => 25.00,
            'base_currency' => 'CNY',
        ]);

        // Compared with whereDate rather than an exact string: a date cast is
        // stored with a time component on SQLite but truncated on PostgreSQL.
        $this->assertTrue(
            Transaction::where('recurring_transaction_id', $template->id)
                ->whereDate('occurrence_on', '2026-10-01')
                ->exists(),
        );

        $this->assertSame('2026-11-01', $template->fresh()->next_occurrence_on->toDateString());
    }

    public function test_running_twice_does_not_post_the_same_occurrence_again(): void
    {
        $user = User::factory()->create();

        RecurringTransaction::factory()->for($user)->create([
            'starts_on' => '2026-10-01',
            'next_occurrence_on' => '2026-10-01',
        ]);

        $runner = app(RecurringTransactionRunner::class);
        $through = CarbonImmutable::parse('2026-10-01');

        $runner->run($user, $through);
        $runner->run($user, $through);

        // The single most important property here. A scheduler that runs twice,
        // or a user pressing "run now" after it already ran, must not double
        // the ledger.
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_the_database_rejects_a_duplicate_occurrence_even_if_the_check_is_bypassed(): void
    {
        $user = User::factory()->create();

        $template = RecurringTransaction::factory()->for($user)->create([
            'starts_on' => '2026-10-01',
            'next_occurrence_on' => '2026-10-01',
        ]);

        $this->postOccurrence($user, $template, '2026-10-01');

        // The application-level check is only an optimisation; the unique index
        // is what makes two concurrent runs safe.
        $this->expectException(QueryException::class);

        $this->postOccurrence($user, $template, '2026-10-01');
    }

    public function test_a_catch_up_creates_each_missed_occurrence(): void
    {
        $user = User::factory()->create();

        // The scheduler has not run since March, so three occurrences are owed.
        RecurringTransaction::factory()->for($user)->create([
            'starts_on' => '2026-03-01',
            'next_occurrence_on' => '2026-03-01',
            'frequency' => RecurringFrequency::Monthly,
        ]);

        app(RecurringTransactionRunner::class)->run($user, CarbonImmutable::parse('2026-05-20'));

        $this->assertDatabaseCount('transactions', 3);
    }

    public function test_a_catch_up_uses_the_occurrence_date_not_the_run_date(): void
    {
        $user = User::factory()->create();

        RecurringTransaction::factory()->for($user)->create([
            'starts_on' => '2026-03-01',
            'next_occurrence_on' => '2026-03-01',
            'frequency' => RecurringFrequency::Monthly,
        ]);

        $template = $user->recurringTransactions()->firstOrFail();

        app(RecurringTransactionRunner::class)->run($user, CarbonImmutable::parse('2026-05-20'));

        // Restamping every missed month with the catch-up date would destroy
        // the monthly history the user is trying to recover.
        $posted = Transaction::where('recurring_transaction_id', $template->id)
            ->orderBy('occurrence_on')
            ->pluck('occurrence_on')
            ->map(fn ($date): string => CarbonImmutable::parse($date)->toDateString())
            ->all();

        $this->assertSame(['2026-03-01', '2026-04-01', '2026-05-01'], $posted);
    }

    public function test_a_long_gap_is_capped_and_reported(): void
    {
        $user = User::factory()->create();

        // A daily template abandoned for a year must not create 365 rows at once.
        RecurringTransaction::factory()->for($user)->create([
            'starts_on' => '2020-01-01',
            'next_occurrence_on' => '2020-01-01',
            'frequency' => RecurringFrequency::Daily,
        ]);

        $result = app(RecurringTransactionRunner::class)->run($user, CarbonImmutable::parse('2026-10-01'));

        $this->assertSame(12, $result['posted']);
        $this->assertNotEmpty($result['capped'], 'Hitting the catch-up limit should be reported, not hidden.');
    }

    public function test_an_inactive_template_is_skipped(): void
    {
        $user = User::factory()->create();

        RecurringTransaction::factory()->for($user)->paused()->create([
            'starts_on' => '2026-10-01',
            'next_occurrence_on' => '2026-10-01',
        ]);

        app(RecurringTransactionRunner::class)->run($user, CarbonImmutable::parse('2026-10-01'));

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_occurrences_after_the_end_date_are_not_created(): void
    {
        $user = User::factory()->create();

        RecurringTransaction::factory()->for($user)->create([
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-03-31',
            'next_occurrence_on' => '2026-01-01',
            'frequency' => RecurringFrequency::Monthly,
        ]);

        app(RecurringTransactionRunner::class)->run($user, CarbonImmutable::parse('2026-06-01'));

        // January, February, March only.
        $this->assertDatabaseCount('transactions', 3);
    }

    public function test_a_template_deactivates_once_it_passes_its_end_date(): void
    {
        $user = User::factory()->create();

        $template = RecurringTransaction::factory()->for($user)->create([
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-03-31',
            'next_occurrence_on' => '2026-01-01',
            'frequency' => RecurringFrequency::Monthly,
        ]);

        app(RecurringTransactionRunner::class)->run($user, CarbonImmutable::parse('2026-06-01'));

        $this->assertFalse($template->fresh()->is_active);
    }

    public function test_a_reminder_only_template_never_posts(): void
    {
        $user = User::factory()->create();

        RecurringTransaction::factory()->for($user)->reminderOnly()->create([
            'starts_on' => '2026-10-01',
            'next_occurrence_on' => '2026-10-01',
        ]);

        app(RecurringTransactionRunner::class)->run($user, CarbonImmutable::parse('2026-10-01'));

        // A bill nobody has necessarily paid yet should not silently become a
        // transaction.
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_income_can_recur(): void
    {
        $user = User::factory()->create();

        RecurringTransaction::factory()->for($user)->create([
            'type' => TransactionType::Income,
            'starts_on' => '2026-10-01',
            'next_occurrence_on' => '2026-10-01',
        ]);

        app(RecurringTransactionRunner::class)->run($user, CarbonImmutable::parse('2026-10-01'));

        $this->assertDatabaseHas('transactions', [
            'type' => TransactionType::Income,
            'amount' => 25.00,
        ]);
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $user = User::factory()->create();

        $template = RecurringTransaction::factory()->for($user)->create([
            'starts_on' => '2026-10-01',
            'next_occurrence_on' => '2026-10-01',
        ]);

        $result = app(RecurringTransactionRunner::class)
            ->run($user, CarbonImmutable::parse('2026-10-01'), dryRun: true);

        $this->assertSame(1, $result['posted']);
        $this->assertDatabaseCount('transactions', 0);

        // The preview must not consume the schedule it describes.
        $this->assertSame('2026-10-01', $template->fresh()->next_occurrence_on->toDateString());
    }

    public function test_generated_transactions_carry_a_base_snapshot(): void
    {
        $user = User::factory()->create();

        // Without a rate the snapshot is null and the row would be excluded
        // from every total, which is the failure this project has already been
        // bitten by.
        Http::fake([
            'api.frankfurter.dev/*' => Http::response([
                ['date' => '2026-10-01', 'base' => 'USD', 'quote' => 'CNY', 'rate' => 7.1],
            ]),
        ]);

        RecurringTransaction::factory()->for($user)->create([
            'currency' => 'USD',
            'starts_on' => '2026-10-01',
            'next_occurrence_on' => '2026-10-01',
        ]);

        app(RecurringTransactionRunner::class)->run($user, CarbonImmutable::parse('2026-10-01'));

        $this->assertDatabaseHas('transactions', ['currency' => 'USD', 'base_currency' => 'CNY']);
    }

    // --- Reminders --------------------------------------------------------

    public function test_a_reminder_is_sent_inside_the_window(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        RecurringTransaction::factory()->for($user)->reminds(5)->create([
            'starts_on' => '2026-10-01',
            'next_occurrence_on' => '2026-10-10',
        ]);

        app(RecurringTransactionRunner::class)->sendReminders($user, CarbonImmutable::parse('2026-10-07'));

        Notification::assertSentTo($user, UpcomingBillNotification::class);
    }

    public function test_no_reminder_outside_the_window(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        RecurringTransaction::factory()->for($user)->reminds(3)->create([
            'starts_on' => '2026-10-01',
            'next_occurrence_on' => '2026-10-10',
        ]);

        app(RecurringTransactionRunner::class)->sendReminders($user, CarbonImmutable::parse('2026-10-01'));

        Notification::assertNothingSent();
    }

    public function test_an_occurrence_is_reminded_about_only_once(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        RecurringTransaction::factory()->for($user)->reminds(5)->create([
            'starts_on' => '2026-10-01',
            'next_occurrence_on' => '2026-10-10',
        ]);

        $runner = app(RecurringTransactionRunner::class);

        // A bill due in three days must not email once a day for three days.
        $runner->sendReminders($user, CarbonImmutable::parse('2026-10-07'));
        $runner->sendReminders($user, CarbonImmutable::parse('2026-10-08'));
        $runner->sendReminders($user, CarbonImmutable::parse('2026-10-09'));

        Notification::assertSentToTimes($user, UpcomingBillNotification::class, 1);
    }

    public function test_a_template_without_a_reminder_setting_never_emails(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        RecurringTransaction::factory()->for($user)->create([
            'remind_days_before' => null,
            'next_occurrence_on' => '2026-10-10',
        ]);

        app(RecurringTransactionRunner::class)->sendReminders($user, CarbonImmutable::parse('2026-10-09'));

        Notification::assertNothingSent();
    }

    // --- Authorization ----------------------------------------------------

    public function test_a_user_cannot_touch_another_users_template(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $template = RecurringTransaction::factory()->for($owner)->create();

        $this->actingAs($other)->get(route('recurring.show', $template))->assertForbidden();
        $this->actingAs($other)->get(route('recurring.edit', $template))->assertForbidden();
        $this->actingAs($other)->delete(route('recurring.destroy', $template))->assertForbidden();

        $this->assertDatabaseHas('recurring_transactions', ['id' => $template->id]);
    }

    public function test_guests_are_redirected(): void
    {
        $this->get(route('recurring.index'))->assertRedirect(route('login'));
    }

    public function test_another_users_occurrences_are_never_generated(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        RecurringTransaction::factory()->for($owner)->create([
            'starts_on' => '2026-10-01',
            'next_occurrence_on' => '2026-10-01',
        ]);

        app(RecurringTransactionRunner::class)->run($other, CarbonImmutable::parse('2026-10-01'));

        $this->assertDatabaseCount('transactions', 0);
    }

    /**
     * Write one occurrence directly, bypassing the runner's existence check.
     */
    private function postOccurrence(User $user, RecurringTransaction $template, string $occurrence): void
    {
        $user->transactions()->create([
            'type' => $template->type,
            'amount' => $template->amount,
            'currency' => $template->currency,
            'base_amount' => $template->amount,
            'base_currency' => 'CNY',
            'applied_rate' => 1,
            'occurred_at' => $occurrence.' 12:00:00',
            'category' => $template->category,
            'recurring_transaction_id' => $template->id,
            'occurrence_on' => $occurrence,
        ]);
    }
}
