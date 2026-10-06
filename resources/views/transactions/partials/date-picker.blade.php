@php
    /**
     * Date range picker for the transaction list.
     *
     * Clicking one date selects that day. Clicking a second date turns the
     * first two into a range. Clicking again starts over from the new date, so
     * no separate "clear" gesture is needed.
     *
     * Month and year navigation are plain links that carry the other filters
     * but drop the date selection: navigating is browsing, and keeping the old
     * selection would filter by dates the user can no longer see.
     *
     * @var \Carbon\CarbonImmutable $calendarMonth
     * @var \Illuminate\Support\Collection<string, int> $activityByDay
     */
    $monthStart = $calendarMonth->copy()->startOfMonth();
    $monthEnd = $monthStart->copy()->endOfMonth();
    $today = \Carbon\CarbonImmutable::now()->format('Y-m-d');

    $browseQuery = fn (string $month): string => route('transactions.index', [
        ...request()->except(['page', 'month', 'date', 'from', 'to']),
        // Keeps the filter panel open across month navigation.
        'panel' => 1,
        'month' => $month,
    ]);

    $previousMonth = $browseQuery($monthStart->copy()->subMonthNoOverflow()->format('Y-m'));
    $nextMonth = $browseQuery($monthStart->copy()->addMonthNoOverflow()->format('Y-m'));
    $previousYear = $browseQuery($monthStart->copy()->subYearNoOverflow()->format('Y-m'));
    $nextYear = $browseQuery($monthStart->copy()->addYearNoOverflow()->format('Y-m'));

    // The month dropdown is a jump control rather than a form field, so it
    // cannot drag the current date selection along with it.
    $months = [];
    for ($offset = -24; $offset <= 24; $offset++) {
        $candidate = $monthStart->copy()->addMonthsNoOverflow($offset);
        $months[] = [
            'url' => $browseQuery($candidate->format('Y-m')),
            'label' => $candidate->isoFormat('MMM YYYY'),
            'current' => $candidate->format('Y-m') === $monthStart->format('Y-m'),
        ];
    }

    // The selection is normalised into a range, so rendering only has to
    // consider two bounds whether the user picked a day or a span.
    $start = $day ?? $from ?? null;
    $end = $to ?? $day ?? null;

    $weekdayLabels = [];
    for ($offset = 0; $offset < 7; $offset++) {
        $weekdayLabels[] = $monthStart->copy()->startOfWeek(\Carbon\CarbonImmutable::MONDAY)
            ->addDays($offset)->isoFormat('ddd');
    }
@endphp

<div class="date-picker w-72 max-w-full select-none rounded-lg border border-gray-200 bg-white p-3 shadow-sm"
    data-month="{{ $monthStart->format('Y-m') }}" aria-labelledby="date-filter-label">

    <div class="flex items-center justify-between gap-1">
        <a href="{{ $previousYear }}" rel="prev-year" title="{{ __('Previous year') }}"
            class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700">&laquo;</a>
        <a href="{{ $previousMonth }}" rel="prev" title="{{ __('Previous month') }}"
            class="rounded p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-700">&lsaquo;</a>

        {{-- Fixed width on purpose: a native select sizes itself to its longest
             option, which would otherwise stretch the card far past the
             seven-column grid below. --}}
        <select aria-label="{{ __('Month') }}"
            class="w-24 cursor-pointer truncate border-0 bg-transparent p-0 text-center text-sm font-semibold text-gray-800 focus:ring-0"
            onchange="if (this.value) { window.location.href = this.value; }">
            @foreach ($months as $month)
                <option value="{{ $month['url'] }}" @selected($month['current'])>{{ $month['label'] }}</option>
            @endforeach
        </select>

        <a href="{{ $nextMonth }}" rel="next" title="{{ __('Next month') }}"
            class="rounded p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-700">&rsaquo;</a>
        <a href="{{ $nextYear }}" rel="next-year" title="{{ __('Next year') }}"
            class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700">&raquo;</a>
    </div>

    <div class="mt-2 grid grid-cols-7 text-center text-[11px] font-medium uppercase tracking-wide text-gray-400">
        @foreach ($weekdayLabels as $label)
            <span>{{ $label }}</span>
        @endforeach
    </div>

    <div class="mt-1 grid grid-cols-7 gap-0.5">
        @for ($blank = $monthStart->dayOfWeekIso - 1; $blank > 0; $blank--)
            <span aria-hidden="true" class="h-8"></span>
        @endfor

        @for ($number = 1; $number <= $monthEnd->day; $number++)
            @php
                $date = $monthStart->copy()->day($number)->format('Y-m-d');
                $isEdge = $date === $start || $date === $end;
                $inside = $start !== null && $end !== null && $date > $start && $date < $end;
            @endphp

            <button type="button" data-date="{{ $date }}" title="{{ $date }}"
                @class([
                    'relative flex h-8 items-center justify-center rounded text-sm transition',
                    'bg-indigo-600 font-semibold text-white' => $isEdge,
                    'bg-indigo-100 text-indigo-900' => $inside,
                    'text-gray-700 hover:bg-gray-200' => ! $isEdge && ! $inside,
                    'ring-1 ring-inset ring-gray-400' => $date === $today && ! $isEdge,
                ])
                aria-pressed="{{ $isEdge ? 'true' : 'false' }}">
                <span>{{ $number }}</span>

                {{-- A mark on days that actually hold transactions, so the
                     calendar doubles as a density map of the month. --}}
                @if (($activityByDay[$date] ?? 0) > 0)
                    <span @class([
                        'absolute bottom-0.5 h-1 w-1 rounded-full',
                        'bg-white' => $isEdge,
                        'bg-indigo-500' => ! $isEdge,
                    ])></span>
                @endif
            </button>
        @endfor
    </div>

    {{-- Real inputs belonging to the filter form, so the picker keeps working
         without JavaScript and the selection reaches the server. --}}
    <input type="hidden" name="date" value="{{ $day }}">
    <input type="hidden" name="from" value="{{ $from }}">
    <input type="hidden" name="to" value="{{ $to }}">

    <p class="mt-2 text-xs text-gray-500">
        @if ($start && $end && $start !== $end)
            {{ __('Click another date to start over.') }}
        @elseif ($start)
            {{ __('Click a second date for a range.') }}
        @else
            {{ __('Click a day, then a second day for a range.') }}
        @endif
    </p>

    @if ($start || $end)
        <button type="button" id="date-clear" class="mt-1 text-xs text-gray-600 hover:text-gray-900">
            {{ __('Clear dates') }}
        </button>
    @endif
</div>

@push('scripts')
    <script>
        document.querySelectorAll('.date-picker').forEach((picker) => {
            const form = document.getElementById('transaction-filters');
            const dateInput = form.querySelector('input[name="date"]');
            const fromInput = form.querySelector('input[name="from"]');
            const toInput = form.querySelector('input[name="to"]');

            // The day picked most recently. A second click pairs with it to
            // make a range; a third click discards it and starts again.
            let anchor = dateInput.value || fromInput.value || '';

            const paint = (start, end) => {
                picker.querySelectorAll('[data-date]').forEach((cell) => {
                    const value = cell.dataset.date;
                    const isEdge = value === start || value === end;
                    const inside = Boolean(start && end && value > start && value < end);

                    cell.classList.toggle('bg-indigo-600', isEdge);
                    cell.classList.toggle('text-white', isEdge);
                    cell.classList.toggle('bg-indigo-100', inside);
                    cell.classList.toggle('text-indigo-900', inside);
                    cell.setAttribute('aria-pressed', isEdge ? 'true' : 'false');
                });
            };

            picker.querySelectorAll('[data-date]').forEach((cell) => {
                cell.addEventListener('click', () => {
                    const value = cell.dataset.date;

                    if (!anchor) {
                        // First pick: that day on its own.
                        anchor = value;
                        dateInput.value = value;
                        fromInput.value = '';
                        toInput.value = '';
                        paint(value, value);
                    } else if (anchor === value) {
                        // Clicking the same day again clears the selection.
                        anchor = '';
                        dateInput.value = '';
                        fromInput.value = '';
                        toInput.value = '';
                        paint(null, null);
                    } else {
                        // Second pick: the two days become a range, ordered so
                        // the earlier one is always the start.
                        const start = anchor < value ? anchor : value;
                        const end = anchor < value ? value : anchor;

                        dateInput.value = '';
                        fromInput.value = start;
                        toInput.value = end;

                        anchor = '';
                        paint(start, end);
                    }

                    form.submit();
                });
            });

            document.getElementById('date-clear')?.addEventListener('click', () => {
                dateInput.value = '';
                fromInput.value = '';
                toInput.value = '';
                anchor = '';
                paint(null, null);
                form.submit();
            });
        });
    </script>
@endpush