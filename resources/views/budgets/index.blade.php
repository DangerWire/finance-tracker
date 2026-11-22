<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-zinc-100 leading-tight">
                    {{ __('Monthly budgets') }}
                </h2>
                <div class="flex items-center gap-2 mt-1.5 text-xs text-zinc-400">
                    <a href="{{ route('budgets.index', ['month' => $prevMonth]) }}"
                        class="inline-flex items-center justify-center w-6 h-6 rounded-md bg-zinc-900 border border-white/[0.07] text-zinc-300 hover:text-white hover:bg-zinc-800 transition-colors"
                        title="{{ __('Previous month') }}">
                        &larr;
                    </a>
                    <span class="font-medium text-zinc-200 px-1">{{ $monthLabel }}</span>
                    <a href="{{ route('budgets.index', ['month' => $nextMonth]) }}"
                        class="inline-flex items-center justify-center w-6 h-6 rounded-md bg-zinc-900 border border-white/[0.07] text-zinc-300 hover:text-white hover:bg-zinc-800 transition-colors"
                        title="{{ __('Next month') }}">
                        &rarr;
                    </a>
                </div>
            </div>

            <div class="flex items-center gap-3" x-data>
                <x-primary-button type="button" @click="$dispatch('open-budget-modal', { isEditing: false })" class="gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span>{{ __('Add budget') }}</span>
                </x-primary-button>
            </div>
        </div>
    </x-slot>

    <div class="py-8" x-data="{
        showModal: false,
        isEditing: false,
        budgetId: null,
        category: '',
        amount: '',
        isRecurring: false,
        periodMonth: '{{ $currentMonth }}',
        openModal(detail) {
            this.isEditing = detail.isEditing;
            if (this.isEditing) {
                this.budgetId = detail.id;
                this.category = detail.category;
                this.amount = detail.budget_amount;
                this.isRecurring = Boolean(detail.is_recurring);
                this.periodMonth = '{{ $currentMonth }}';
            } else {
                this.budgetId = null;
                this.category = '';
                this.amount = '';
                this.isRecurring = false;
                this.periodMonth = '{{ $currentMonth }}';
            }
            this.showModal = true;
        }
    }" @open-budget-modal.window="openModal($event.detail)">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm rounded-xl px-4 py-3 flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-400 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            @if ($errors->any())
                <div class="bg-rose-500/10 border border-rose-500/20 text-rose-300 text-sm rounded-xl px-4 py-3">
                    {{ $errors->first() }}
                </div>
            @endif

            <!-- Overall Summary Card -->
            <div class="sure-card rounded-xl p-6">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 flex-1">
                        <div>
                            <span class="text-[11px] font-medium uppercase tracking-wider text-zinc-400">{{ __('Total budget') }}</span>
                            <div class="mt-1.5 text-2xl font-semibold font-mono-num text-zinc-100 tracking-tight">
                                {{ number_format($overview['total_budget'], 2) }}
                                <span class="text-xs font-normal text-zinc-500 ml-0.5">{{ $baseCurrency }}</span>
                            </div>
                        </div>

                        <div>
                            <span class="text-[11px] font-medium uppercase tracking-wider text-zinc-400">{{ __('Total spent') }}</span>
                            <div class="mt-1.5 text-2xl font-semibold font-mono-num text-zinc-100 tracking-tight">
                                {{ number_format($overview['total_spent'], 2) }}
                                <span class="text-xs font-normal text-zinc-500 ml-0.5">{{ $baseCurrency }}</span>
                            </div>
                        </div>

                        <div>
                            <span class="text-[11px] font-medium uppercase tracking-wider text-zinc-400">{{ __('Remaining allowance') }}</span>
                            <div class="mt-1.5 text-2xl font-semibold font-mono-num tracking-tight {{ $overview['total_remaining'] < 0 ? 'text-rose-400' : 'text-emerald-400' }}">
                                {{ number_format($overview['total_remaining'], 2) }}
                                <span class="text-xs font-normal text-zinc-500 ml-0.5">{{ $baseCurrency }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="md:w-64 border-t md:border-t-0 md:border-l pt-4 md:pt-0 md:pl-6 border-white/[0.06] flex flex-col justify-center">
                        <div class="flex items-center justify-between text-xs mb-2">
                            <span class="text-zinc-400 font-medium">{{ __('Overall progress') }}</span>
                            <span class="font-mono-num font-semibold text-zinc-200">{{ $overview['percentage_used'] }}%</span>
                        </div>
                        <div class="w-full bg-zinc-800 rounded-full h-2 overflow-hidden">
                            @php
                                $barColor = match ($overview['status']) {
                                    'exceeded' => 'bg-rose-400',
                                    'warning' => 'bg-amber-400',
                                    default => 'bg-emerald-400',
                                };
                            @endphp
                            <div class="{{ $barColor }} h-2 rounded-full transition-all duration-300" style="width: {{ min($overview['percentage_used'], 100) }}%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Category Budgets Grid -->
            @if (empty($overview['items']))
                <div class="sure-card rounded-xl p-12 text-center">
                    <div class="w-11 h-11 rounded-xl bg-zinc-900 border border-white/[0.07] flex items-center justify-center mx-auto mb-3 text-zinc-500">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 14l6-6m0 0l-6-6m6 6H3" />
                        </svg>
                    </div>
                    <h3 class="text-sm font-semibold text-zinc-100">{{ __('No budgets set for this month.') }}</h3>
                    <p class="mt-1 text-xs text-zinc-400 max-w-md mx-auto">{{ __('Set monthly spending limits for your categories and track your progress in real-time.') }}</p>
                    <div class="mt-5">
                        <x-primary-button type="button" @click="$dispatch('open-budget-modal', { isEditing: false })">
                            {{ __('Add budget') }}
                        </x-primary-button>
                    </div>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    @foreach ($overview['items'] as $item)
                        <div class="sure-card rounded-xl p-5 flex flex-col justify-between sure-hover-lift">
                            <div>
                                <div class="flex items-start justify-between gap-2 mb-3">
                                    <h4 class="font-semibold text-sm text-zinc-100 truncate" title="{{ $item['category'] }}">
                                        {{ $item['category'] }}
                                    </h4>
                                    <div class="flex items-center gap-1.5 shrink-0">
                                        @if ($item['is_recurring'])
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-sky-500/10 text-sky-400 border border-sky-500/20" title="{{ __('Repeat every month') }}">
                                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                                </svg>
                                                <span>{{ __('Recurring') }}</span>
                                            </span>
                                        @endif

                                        @if ($item['status'] === 'exceeded')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                                {{ __('Over budget') }}
                                            </span>
                                        @elseif ($item['status'] === 'warning')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                                {{ __('Near limit') }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                                {{ __('Safe') }}
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                <div class="space-y-1 mb-4">
                                    <div class="text-sm text-zinc-400 font-mono-num">
                                        <span class="font-semibold text-zinc-100">{{ number_format($item['spent_amount'], 2) }}</span>
                                        <span class="font-sans text-xs mx-0.5">{{ __('spent of') }}</span>
                                        <span class="font-semibold text-zinc-200">{{ number_format($item['budget_amount'], 2) }}</span>
                                        <span class="text-[11px] text-zinc-500">{{ $baseCurrency }}</span>
                                    </div>
                                    <div class="text-xs font-mono-num {{ $item['remaining_amount'] < 0 ? 'text-rose-400 font-medium' : 'text-zinc-500' }}">
                                        {{ number_format(abs($item['remaining_amount']), 2) }} {{ $baseCurrency }}
                                        <span class="font-sans">{{ $item['remaining_amount'] < 0 ? __('over limit') : __('remaining') }}</span>
                                    </div>
                                </div>

                                <div class="w-full bg-zinc-800/90 rounded-full h-1.5 overflow-hidden mb-4">
                                    @php
                                        $categoryBarColor = match ($item['status']) {
                                            'exceeded' => 'bg-rose-400',
                                            'warning' => 'bg-amber-400',
                                            default => 'bg-emerald-400',
                                        };
                                    @endphp
                                    <div class="{{ $categoryBarColor }} h-1.5 rounded-full transition-all duration-300" style="width: {{ min($item['percentage_used'], 100) }}%"></div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between pt-3 border-t border-white/[0.06] text-xs">
                                <span class="font-mono-num font-semibold text-zinc-400">{{ $item['percentage_used'] }}%</span>
                                <div class="flex items-center gap-3">
                                    <button type="button" @click="$dispatch('open-budget-modal', {
                                        isEditing: true,
                                        id: {{ $item['id'] }},
                                        category: '{{ addslashes($item['category']) }}',
                                        budget_amount: '{{ $item['budget_amount'] }}',
                                        is_recurring: {{ $item['is_recurring'] ? 'true' : 'false' }}
                                    })" class="text-emerald-400 hover:text-emerald-300 font-medium transition-colors">
                                        {{ __('Edit') }}
                                    </button>

                                    <form method="POST" action="{{ route('budgets.destroy', $item['id']) }}"
                                        onsubmit="return confirm('{{ $item['is_recurring'] ? __('Delete this budget? Recurring monthly rollover for this category will also stop.') : __('Delete this budget?') }}');"
                                        class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-rose-400 hover:text-rose-300 font-medium transition-colors">
                                            {{ __('Delete') }}
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Add / Edit Budget Modal -->
        <div x-cloak x-show="showModal" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div x-show="showModal" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-black/75 backdrop-blur-sm transition-opacity" @click="showModal = false" aria-hidden="true"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div x-show="showModal" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="inline-block align-bottom bg-[#111218] border border-white/[0.08] rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <form :action="isEditing ? '/budgets/' + budgetId : '{{ route('budgets.store') }}'" method="POST">
                        @csrf
                        <template x-if="isEditing">
                            <input type="hidden" name="_method" value="PUT">
                        </template>
                        <input type="hidden" name="period_month" :value="periodMonth">

                        <div class="px-6 pt-6 pb-5">
                            <h3 class="text-base font-bold text-white tracking-tight" x-text="isEditing ? '{{ __('Edit budget') }}' : '{{ __('Add budget') }}'"></h3>
                            <p class="mt-1 text-xs text-zinc-400">
                                {{ __('Target month') }}: <span class="font-mono-num font-medium text-zinc-200" x-text="periodMonth"></span>
                            </p>

                            <div class="mt-5 space-y-4">
                                <div>
                                    <x-input-label for="category" :value="__('Category')" />
                                    <input id="category" name="category" type="text" x-model="category" list="user-categories" required
                                        class="mt-1.5 block w-full rounded-lg bg-[#09090b] border border-white/[0.09] text-zinc-100 placeholder-zinc-500 text-sm px-3.5 py-2.5 focus:border-emerald-500/60 focus:ring-1 focus:ring-emerald-500/40 focus:outline-none"
                                        placeholder="{{ __('Select or type a category') }}">
                                    <datalist id="user-categories">
                                        @foreach ($categories as $cat)
                                            <option value="{{ $cat }}"></option>
                                        @endforeach
                                    </datalist>
                                </div>

                                <div>
                                    <x-input-label for="amount">
                                        {{ __('Monthly limit') }} ({{ $baseCurrency }})
                                    </x-input-label>
                                    <input id="amount" name="amount" type="number" step="0.01" min="0.01" x-model="amount" required
                                        class="mt-1.5 block w-full rounded-lg bg-[#09090b] border border-white/[0.09] text-zinc-100 placeholder-zinc-500 font-mono-num text-sm px-3.5 py-2.5 focus:border-emerald-500/60 focus:ring-1 focus:ring-emerald-500/40 focus:outline-none"
                                        placeholder="1000.00">
                                </div>

                                <div class="rounded-xl bg-[#09090b]/80 border border-white/[0.07] p-3.5">
                                    <label for="is_recurring" class="flex items-start gap-3 cursor-pointer">
                                        <input type="hidden" name="is_recurring" value="0">
                                        <input id="is_recurring" name="is_recurring" type="checkbox" value="1" x-model="isRecurring"
                                            class="mt-0.5 rounded bg-zinc-900 border-zinc-700 text-emerald-500 focus:ring-emerald-500/40 focus:ring-offset-0">
                                        <span class="space-y-0.5">
                                            <span class="block text-xs font-semibold text-zinc-100">{{ __('Repeat every month') }}</span>
                                            <span class="block text-[11px] text-zinc-400">{{ __('Automatically carry this budget forward into each new month.') }}</span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="bg-[#0c0d12] border-t border-white/[0.06] px-6 py-3.5 flex flex-row-reverse gap-2.5">
                            <x-primary-button type="submit">
                                <span x-text="isEditing ? '{{ __('Update budget') }}' : '{{ __('Save budget') }}'"></span>
                            </x-primary-button>
                            <button type="button" @click="showModal = false"
                                class="inline-flex items-center justify-center rounded-lg border border-white/[0.08] bg-zinc-900 px-4 py-2 text-xs font-medium text-zinc-300 hover:bg-zinc-800 hover:text-white transition-colors">
                                {{ __('Cancel') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
