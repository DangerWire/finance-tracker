@php
    /**
     * Date range picker for the transaction list.
     *
     * Clicking one date selects that day. Clicking a second date turns the
     * first two into a range. Clicking again starts over from the new date, so
     * no separate "clear" gesture is needed.
     *
     * The grid is rendered separately because a month fetched over the network
     * replaces it wholesale. Everything the browser must keep — the date inputs
     * that drive the form, and the clear button — deliberately sits OUTSIDE the
     * element that gets swapped.
     *
     * @var \Carbon\CarbonImmutable $calendarMonth
     * @var \Illuminate\Support\Collection<string, int> $activityByDay
     */
    $hasSelection = (bool) ($day ?? $from ?? $to);
@endphp

<div class="w-72 max-w-full select-none rounded-lg border border-gray-200 bg-white p-3 shadow-sm"
    aria-labelledby="date-filter-label">

    <div class="date-picker" data-month="{{ $calendarMonth->format('Y-m') }}">
        @include('transactions.partials.date-picker-grid')
    </div>

    {{-- Real inputs belonging to the filter form, so the picker keeps working
         without JavaScript and the selection reaches the server. Outside the
         picker on purpose: a month swap must not destroy them. --}}
    <input type="hidden" name="date" value="{{ $day }}">
    <input type="hidden" name="from" value="{{ $from }}">
    <input type="hidden" name="to" value="{{ $to }}">

    @if ($hasSelection)
        <button type="button" id="date-clear" class="mt-1 text-xs text-gray-600 hover:text-gray-900">
            {{ __('Clear dates') }}
        </button>
    @endif
</div>

@push('scripts')
    <script>
        // Located by the surrounding card, since the picker element itself is
        // replaced on every month swap.
        document.querySelectorAll('.date-picker').forEach((picker) => {
            const card = picker.parentElement;
            const form = document.getElementById('transaction-filters');
            const dateInput = form.querySelector('input[name="date"]');
            const fromInput = form.querySelector('input[name="from"]');
            const toInput = form.querySelector('input[name="to"]');

            // Where a month fetch is served from.
            const calendarPath = @json(route('transactions.calendar'));

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

            // Show or hide the clear button to match the live selection.
            const syncClear = () => {
                const button = card.querySelector('#date-clear');

                if (!button) {
                    return;
                }

                const active = Boolean(dateInput.value || fromInput.value || toInput.value);
                button.hidden = !active;
            };

            // Fetch a month and swap the grid in place.
            //
            // This only ever changes which month the calendar draws: the month
            // is not a filter, so the list beneath it is unaffected and there is
            // nothing to reload. Previously every arrow press re-rendered the
            // whole page for an identical result.
            const swapMonth = async (href) => {
                const url = new URL(href, location.origin);
                url.pathname = calendarPath;

                try {
                    const response = await fetch(url, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    });

                    const body = await response.text();

                    // A page instead of a fragment means the endpoint did not
                    // match. Swapping that in would destroy the picker, so fall
                    // back to navigating rather than rendering it.
                    if (!response.ok || body.includes('<html')) {
                        console.warn(
                            '[calendar] fragment fetch returned a page; navigating instead',
                            { url: String(url), status: response.status },
                        );
                        location.href = href;
                        return;
                    }

                    // Only the grid is replaced. The date inputs are outside it
                    // and keep their values, so an active filter survives a
                    // change of month.
                    picker.innerHTML = body;
                    picker.dataset.month = url.searchParams.get('month') ?? picker.dataset.month;

                    syncClear();
                } catch (error) {
                    console.warn('[calendar] fragment fetch failed', { url: String(url), error });
                    location.href = href;
                }
            };

            // Delegated rather than bound per cell, because the cells are
            // replaced wholesale by a month fetch and any directly attached
            // handler would go with them.
            picker.addEventListener('click', (event) => {
                const cell = event.target.closest('[data-date]');

                if (cell) {
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

                    syncClear();
                    form.submit();
                    return;
                }

                const nav = event.target.closest('[data-calendar-nav]');

                if (nav) {
                    event.preventDefault();
                    swapMonth(nav.href);
                }
            });

            card.addEventListener('click', (event) => {
                if (!event.target.closest('#date-clear')) {
                    return;
                }

                dateInput.value = '';
                fromInput.value = '';
                toInput.value = '';
                anchor = '';
                paint(null, null);
                syncClear();
                form.submit();
            });

            picker.addEventListener('change', (event) => {
                const select = event.target.closest('[data-calendar-month-select]');

                if (select) {
                    // Handled here rather than by the form's own change
                    // listener, so it navigates the calendar instead of
                    // submitting and reloading the page.
                    event.stopPropagation();
                    swapMonth(select.value);
                }
            });
        });
    </script>
@endpush