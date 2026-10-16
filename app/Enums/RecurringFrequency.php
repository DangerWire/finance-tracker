<?php

namespace App\Enums;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * How often a recurring transaction repeats.
 *
 * Occurrences are always derived from the template's start date rather than
 * by repeatedly adding to the previous date. That distinction matters: adding
 * a month to 31 January yields 3 March, and a template anchored on the 31st
 * would then skip February, drift a day every short month, and end up
 * permanently off its intended day.
 */
enum RecurringFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Biweekly = 'biweekly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Daily',
            self::Weekly => 'Weekly',
            self::Biweekly => 'Biweekly',
            self::Monthly => 'Monthly',
            self::Quarterly => 'Quarterly',
            self::Yearly => 'Yearly',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Daily => 'Every day',
            self::Weekly => 'Every week',
            self::Biweekly => 'Every 2 weeks',
            self::Monthly => 'Every month',
            self::Quarterly => 'Every 3 months',
            self::Yearly => 'Every year',
        };
    }

    /**
     * The nth occurrence counted from the anchor, with step 0 being the anchor
     * itself.
     *
     * Day-of-month is clamped to the length of the target month rather than
     * overflowing, so an anchor on the 31st resolves to the 28th, 29th or 30th
     * in shorter months and returns to the 31st afterwards.
     */
    public function occurrenceFrom(CarbonInterface $anchor, int $steps): CarbonImmutable
    {
        $anchor = CarbonImmutable::instance($anchor)->startOfDay();

        return match ($this) {
            self::Daily => $anchor->addDays($steps),
            self::Weekly => $anchor->addWeeks($steps),
            self::Biweekly => $anchor->addWeeks($steps * 2),
            self::Monthly => $this->clampedMonth($anchor, $steps),
            self::Quarterly => $this->clampedMonth($anchor, $steps * 3),
            self::Yearly => $this->clampedMonth($anchor, $steps * 12),
        };
    }

    /**
     * The first occurrence strictly after $after.
     *
     * The step count is estimated from the elapsed period and then nudged into
     * place, which keeps this O(1) instead of walking one occurrence at a time
     * from an anchor that may be years old.
     */
    public function nextOccurrenceAfter(CarbonInterface $anchor, CarbonInterface $after): CarbonImmutable
    {
        // Accepting CarbonInterface rather than only CarbonImmutable avoids
        // making every caller convert: Eloquent's date casts hand back the
        // mutable Carbon subclass.
        $anchor = CarbonImmutable::instance($anchor)->startOfDay();
        $after = CarbonImmutable::instance($after)->startOfDay();

        $monthsApart = ($after->year - $anchor->year) * 12 + ($after->month - $anchor->month);

        $guess = match ($this) {
            self::Daily => $anchor->diffInDays($after) + 1,
            self::Weekly => intdiv($anchor->diffInDays($after), 7) + 1,
            self::Biweekly => intdiv($anchor->diffInDays($after), 14) + 1,
            self::Monthly => $monthsApart + 1,
            self::Quarterly => intdiv($monthsApart, 3) + 1,
            self::Yearly => ($after->year - $anchor->year) + 1,
        };

        $guess = max($guess, 0);

        // Nudge into place. At most a couple of steps either way, because the
        // estimate is already within one period. The bound is zero rather than
        // one: step 0 is the anchor itself, which is the correct answer when
        // the template starts after the date being asked about.
        while ($guess > 0 && $this->occurrenceFrom($anchor, $guess - 1)->gt($after)) {
            $guess--;
        }

        while ($this->occurrenceFrom($anchor, $guess)->lte($after)) {
            $guess++;
        }

        return $this->occurrenceFrom($anchor, $guess);
    }

    /**
     * Add whole months, keeping the anchor's day where the month is long
     * enough and falling back to the month's final day where it is not.
     */
    private function clampedMonth(CarbonImmutable $anchor, int $months): CarbonImmutable
    {
        $firstOfTarget = $anchor->addMonthsNoOverflow($months)->startOfMonth();
        $day = min($anchor->day, $firstOfTarget->daysInMonth);

        return $firstOfTarget->day($day);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_column(self::cases(), 'label', 'value');
    }
}
