<?php

namespace App\Models;

use App\Enums\RecurringFrequency;
use App\Enums\TransactionType;
use Carbon\CarbonImmutable;
use Database\Factories\RecurringTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A template that describes a transaction repeating on a schedule.
 *
 * The template stores no amounts of its own beyond the one to repeat; the
 * transactions it produces are ordinary rows, linked back through
 * recurring_transaction_id so they can be traced, and so the pair of
 * (template, occurrence date) can be made unique.
 */
#[Fillable([
    'user_id',
    'type',
    'name',
    'amount',
    'currency',
    'category',
    'note',
    'frequency',
    'starts_on',
    'ends_on',
    'next_occurrence_on',
    'last_occurrence_on',
    'auto_post',
    'remind_days_before',
    'last_reminded_occurrence_on',
    'is_active',
])]
class RecurringTransaction extends Model
{
    /** @use HasFactory<RecurringTransactionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'frequency' => RecurringFrequency::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'next_occurrence_on' => 'date',
            'last_occurrence_on' => 'date',
            'last_reminded_occurrence_on' => 'date',
            'auto_post' => 'boolean',
            'is_active' => 'boolean',
            'remind_days_before' => 'integer',
        ];
    }

    /**
     * The user that owns this template.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The transactions this template has produced.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * The first occurrence falling on or after the given day.
     *
     * Always derived from starts_on so the day-of-month anchor survives short
     * months.
     */
    public function firstOccurrenceOnOrAfter(CarbonImmutable $day): CarbonImmutable
    {
        return $this->frequency->nextOccurrenceAfter($this->starts_on, $day->subDay());
    }

    /**
     * Every occurrence from the cursor up to and including $through.
     *
     * A scheduler that has not run for a week should not quietly skip the
     * weeks it missed: a missed rent payment is a real event. The count is
     * capped so that a template left inactive for years cannot flood the
     * ledger on its next run.
     *
     * @return array<int, CarbonImmutable>
     */
    public function dueOccurrences(CarbonImmutable $through, int $limit = 12): array
    {
        $occurrences = [];
        $cursor = CarbonImmutable::parse($this->next_occurrence_on)->startOfDay();

        while ($cursor->lte($through) && count($occurrences) < $limit) {
            if ($this->ends_on !== null && $cursor->gt($this->ends_on->endOfDay())) {
                break;
            }

            $occurrences[] = $cursor;
            $cursor = $this->frequency->nextOccurrenceAfter($this->starts_on, $cursor);
        }

        return $occurrences;
    }

    /**
     * Move the cursor past $occurrence.
     */
    public function advanceTo(CarbonImmutable $occurrence): self
    {
        $this->last_occurrence_on = $occurrence;
        $this->next_occurrence_on = $this->frequency->nextOccurrenceAfter($this->starts_on, $occurrence);

        // A template that has run past its end date is finished with.
        if ($this->ends_on !== null && $this->next_occurrence_on->gt($this->ends_on->endOfDay())) {
            $this->is_active = false;
        }

        return $this;
    }

    /**
     * Whether the template has run past its end date.
     */
    public function hasEnded(CarbonImmutable $on): bool
    {
        return $this->ends_on !== null && $on->gt($this->ends_on->endOfDay());
    }

    /**
     * Whether an occurrence already has a transaction against it.
     */
    public function hasOccurrenceBeenPosted(CarbonImmutable $occurrence): bool
    {
        return $this->transactions()
            ->whereDate('occurrence_on', $occurrence->toDateString())
            ->exists();
    }

    /**
     * Whole days from $on until the next occurrence.
     *
     * Positive means the occurrence is still ahead; negative means it has
     * already passed. The order of the operands matters: diffInDays reports
     * positive when the second argument is later, so $on has to come first.
     */
    public function daysUntilNextOccurrence(CarbonImmutable $on): int
    {
        return (int) $on->startOfDay()->diffInDays(
            $this->next_occurrence_on->startOfDay(),
            false,
        );
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Templates with an occurrence waiting to be processed.
     *
     * Compared with whereDate rather than a raw string comparison: SQLite
     * stores date columns with a time component, so '2026-10-01 00:00:00'
     * against a bare '2026-10-01' sorts the wrong way there while behaving
     * correctly on PostgreSQL, which truncates on insert.
     *
     * Templates whose end date has already passed are deliberately included.
     * A scheduler that was down while a subscription was still active should
     * still create the occurrences that fell inside its window;
     * dueOccurrences stops at ends_on.
     *
     * @param  Builder<self>  $query
     */
    public function scopeDue(Builder $query, CarbonImmutable $through): Builder
    {
        return $query->active()
            ->whereDate('next_occurrence_on', '<=', $through->toDateString());
    }

    /**
     * Templates that might need reminding today.
     *
     * This narrows to a coarse window of active templates carrying a reminder
     * setting. Whether a given template's own window has actually been reached
     * is decided in the runner, because remind_days_before differs per
     * template and expressing that as one SQL condition would need a
     * per-row interval calculation the test database cannot run.
     *
     * @param  Builder<self>  $query
     */
    public function scopeNeedingReminder(Builder $query, CarbonImmutable $on, int $maxWindow = 30): Builder
    {
        return $query->active()
            ->whereNotNull('remind_days_before')
            ->whereDate('next_occurrence_on', '>=', $on->toDateString())
            ->whereDate('next_occurrence_on', '<=', $on->addDays($maxWindow)->toDateString())
            ->where(function (Builder $query): void {
                // Not yet reminded about the occurrence now pending, which is
                // what stops a bill due in a week emailing every day for a week.
                $query->whereNull('last_reminded_occurrence_on')
                    ->orWhereColumn('last_reminded_occurrence_on', '<', 'next_occurrence_on');
            });
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeUpcoming(Builder $query, CarbonImmutable $on, int $days = 14): Builder
    {
        return $query->active()
            ->whereDate('next_occurrence_on', '>=', $on->toDateString())
            ->whereDate('next_occurrence_on', '<=', $on->addDays($days)->toDateString())
            ->orderBy('next_occurrence_on');
    }
}
