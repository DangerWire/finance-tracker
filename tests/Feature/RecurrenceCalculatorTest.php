<?php

namespace Tests\Feature;

use App\Enums\RecurringFrequency;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The date arithmetic behind recurring occurrences.
 *
 * These are the cases that go wrong quietly: a template that drifts a day
 * every short month, or skips February entirely, still produces plausible
 * looking dates for a long time before anyone notices.
 */
class RecurrenceCalculatorTest extends TestCase
{
    public function test_weekly_occurrences_advance_by_a_week(): void
    {
        $anchor = CarbonImmutable::parse('2026-01-05');

        $this->assertSame(
            '2026-01-12',
            RecurringFrequency::Weekly->occurrenceFrom($anchor, 1)->toDateString(),
        );

        $this->assertSame(
            '2026-02-02',
            RecurringFrequency::Weekly->occurrenceFrom($anchor, 4)->toDateString(),
        );
    }

    public function test_monthly_occurrence_keeps_the_anchor_day(): void
    {
        $anchor = CarbonImmutable::parse('2026-01-15');

        $this->assertSame(
            '2026-02-15',
            RecurringFrequency::Monthly->occurrenceFrom($anchor, 1)->toDateString(),
        );
    }

    public function test_a_month_end_anchor_is_clamped_rather_than_overflowing(): void
    {
        $anchor = CarbonImmutable::parse('2026-01-31');

        // The failure this guards: adding a month to 31 January yields 3 March,
        // so February is skipped and every later date is wrong.
        $this->assertSame(
            '2026-02-28',
            RecurringFrequency::Monthly->occurrenceFrom($anchor, 1)->toDateString(),
        );

        $this->assertSame(
            '2026-03-31',
            RecurringFrequency::Monthly->occurrenceFrom($anchor, 2)->toDateString(),
        );
    }

    public function test_a_leap_day_anchor_survives_a_non_leap_year(): void
    {
        $anchor = CarbonImmutable::parse('2024-02-29');

        $this->assertSame(
            '2025-02-28',
            RecurringFrequency::Yearly->occurrenceFrom($anchor, 1)->toDateString(),
        );

        // And returns to the 29th once a leap year comes round again.
        $this->assertSame(
            '2028-02-29',
            RecurringFrequency::Yearly->occurrenceFrom($anchor, 4)->toDateString(),
        );
    }

    public function test_quarterly_advances_three_months_at_a_time(): void
    {
        $anchor = CarbonImmutable::parse('2026-01-10');

        $this->assertSame(
            '2026-04-10',
            RecurringFrequency::Quarterly->occurrenceFrom($anchor, 1)->toDateString(),
        );
    }

    #[DataProvider('nextOccurrenceCases')]
    public function test_it_finds_the_first_occurrence_after_a_date(
        string $frequency,
        string $anchor,
        string $after,
        string $expected,
    ): void {
        $this->assertSame(
            $expected,
            RecurringFrequency::from($frequency)
                ->nextOccurrenceAfter(
                    CarbonImmutable::parse($anchor),
                    CarbonImmutable::parse($after),
                )
                ->toDateString(),
        );
    }

    /**
     * @return array<int, array<int, string>>
     */
    public static function nextOccurrenceCases(): array
    {
        return [
            'daily, same day' => ['daily', '2026-01-01', '2026-01-01', '2026-01-02'],
            'weekly, mid-week' => ['weekly', '2026-01-05', '2026-01-08', '2026-01-12'],
            'weekly, exactly on the day' => ['weekly', '2026-01-05', '2026-01-05', '2026-01-12'],
            'monthly, before the day' => ['monthly', '2026-01-15', '2026-01-01', '2026-01-15'],
            'monthly, after the day' => ['monthly', '2026-01-15', '2026-01-20', '2026-02-15'],
            'monthly across a short month' => ['monthly', '2026-01-31', '2026-02-01', '2026-02-28'],
            'yearly, later in the year' => ['yearly', '2020-06-01', '2026-10-01', '2027-06-01'],
            // 10 May is itself after 1 May, so that is the next one, not June's.
            'anchor far in the past' => ['monthly', '1999-03-10', '2026-05-01', '2026-05-10'],
            'anchor far in the past, later in the month' => ['monthly', '1999-03-10', '2026-05-20', '2026-06-10'],
        ];
    }

    public function test_next_occurrence_is_strictly_after_the_given_date(): void
    {
        $frequency = RecurringFrequency::Monthly;
        $anchor = CarbonImmutable::parse('2026-01-15');
        $onTheDay = CarbonImmutable::parse('2026-01-15');

        // Asking "what is next" while sitting on an occurrence must move
        // forward, or the generator would re-post the same date forever.
        $this->assertSame(
            '2026-02-15',
            $frequency->nextOccurrenceAfter($anchor, $onTheDay)->toDateString(),
        );
    }
}
