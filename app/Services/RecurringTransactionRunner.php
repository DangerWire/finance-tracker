<?php

namespace App\Services;

use App\Actions\ConvertTransactionAmount;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\UpcomingBillNotification;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Turns due recurring occurrences into transactions.
 *
 * The one property this has to guarantee is that an occurrence is posted at
 * most once. The scheduler may run twice, a user may press "run now" after
 * it has already run, and two overlapping runs can both be in flight. The
 * unique index on (recurring_transaction_id, occurrence_on) is what actually
 * enforces that; the existence check here only avoids raising the exception in
 * the ordinary case.
 */
class RecurringTransactionRunner
{
    /**
     * How many missed occurrences a single run will create.
     *
     * A scheduler that has been down should not skip the bills it missed, but a
     * template left inactive for years must not flood the ledger either.
     */
    private const CATCH_UP_LIMIT = 12;

    public function __construct(private readonly ConvertTransactionAmount $convert) {}

    /**
     * Process every due occurrence for a user.
     *
     * @return array{posted: int, skipped: int, skipped_details: array<int, array{recurring_id: int, name: string, reason: string}>, capped: array<int, int>}
     */
    public function run(User $user, CarbonImmutable $through, bool $dryRun = false): array
    {
        $posted = 0;
        $skipped = 0;
        $skippedDetails = [];
        $capped = [];

        $templates = $user->recurringTransactions()
            ->due($through)
            ->get();

        foreach ($templates as $template) {
            $occurrences = $template->dueOccurrences($through);

            // Whether the cap actually bit, rather than just being reached.
            if ($occurrences !== []
                && $template->frequency->nextOccurrenceAfter($template->starts_on, end($occurrences))->lte($through)) {
                // Occurrences have been dropped on the floor. Worth surfacing
                // rather than hiding, because the totals are then incomplete
                // and nothing else would say so.
                $capped[] = $template->id;
            }

            foreach ($occurrences as $occurrence) {
                $result = $this->postOccurrence($template, $occurrence, $dryRun);

                if ($result === 'posted') {
                    $posted++;
                } else {
                    $skipped++;
                    $skippedDetails[] = [
                        'recurring_id' => $template->id,
                        'name' => $template->name,
                        'reason' => $result,
                    ];
                }
            }
        }

        return [
            'posted' => $posted,
            'skipped' => $skipped,
            'skipped_details' => $skippedDetails,
            'capped' => $capped,
        ];
    }

    /**
     * Post a single occurrence.
     *
     * @return 'posted'|'already_posted'|'reminder_only'|'failed'
     */
    public function postOccurrence(RecurringTransaction $template, CarbonImmutable $occurrence, bool $dryRun = false): string
    {
        if ($template->hasOccurrenceBeenPosted($occurrence)) {
            $this->advance($template, $occurrence, $dryRun);

            return 'already_posted';
        }

        // A reminder-only template tracks a bill without posting it, which is
        // the honest behaviour for something nobody has necessarily paid.
        if (! $template->auto_post) {
            $this->advance($template, $occurrence, $dryRun);

            return 'reminder_only';
        }

        if ($dryRun) {
            return 'posted';
        }

        $attributes = $this->convert->apply([
            'type' => $template->type,
            'amount' => $template->amount,
            'currency' => $template->currency,
            // The occurrence date, not today. A run catching up on missed
            // occurrences must not restamp them all with the catch-up date.
            'occurred_at' => $occurrence->setTime(12, 0)->format('Y-m-d H:i:s'),
            'category' => $template->category,
            'note' => $template->note,
        ]);

        try {
            // The unique index is the real guard against a concurrent run.
            // Created through the relation rather than Transaction::create,
            // because user_id is not mass assignable and would be silently
            // dropped, producing a row with no owner.
            $template->user->transactions()->create([
                ...$attributes,
                'recurring_transaction_id' => $template->id,
                'occurrence_on' => $occurrence->toDateString(),
            ]);
        } catch (Throwable $exception) {
            // A unique violation means another run got there first, which is
            // the outcome we wanted anyway. Anything else is worth surfacing.
            if (! $this->isDuplicateOccurrence($exception)) {
                report($exception);

                return 'failed';
            }
        }

        $this->advance($template, $occurrence, $dryRun);

        return 'posted';
    }

    /**
     * Send due-soon reminders.
     *
     * @return array<int, string> names of templates reminded
     */
    public function sendReminders(User $user, CarbonImmutable $on, bool $dryRun = false): array
    {
        $reminded = [];

        $templates = $user->recurringTransactions()->needingReminder($on)->get();

        foreach ($templates as $template) {
            $daysAway = $template->daysUntilNextOccurrence($on);

            // daysUntilNextOccurrence is signed, so a template whose occurrence
            // is today reads zero and one already passed reads negative.
            if ($daysAway > $template->remind_days_before || $daysAway < 0) {
                continue;
            }

            $reminded[] = $template->name;

            if ($dryRun) {
                continue;
            }

            $template->user->notify(new UpcomingBillNotification($template, $daysAway));

            $template->forceFill([
                'last_reminded_occurrence_on' => $template->next_occurrence_on,
            ])->save();
        }

        return $reminded;
    }

    /**
     * Move the template's cursor, but only when a transaction was written.
     *
     * For a dry run the cursor is left alone so the preview cannot consume the
     * schedule it is describing.
     */
    private function advance(RecurringTransaction $template, CarbonImmutable $occurrence, bool $dryRun): void
    {
        if ($dryRun) {
            return;
        }

        $template->advanceTo($occurrence)->save();
    }

    /**
     * Whether the failure was the unique index rejecting a second posting.
     */
    private function isDuplicateOccurrence(Throwable $exception): bool
    {
        $sqlState = $exception instanceof QueryException
            ? $exception->getCode()
            : null;

        if ($sqlState === '23505') {
            return true;
        }

        return str_contains($exception->getMessage(), 'transactions_recurring_occurrence_unique');
    }

    /**
     * Templates that are due for a user, for display purposes.
     *
     * @return Collection<int, RecurringTransaction>
     */
    public function upcoming(User $user, CarbonImmutable $on, int $days = 14): Collection
    {
        return $user->recurringTransactions()->upcoming($on, $days)->get();
    }
}
