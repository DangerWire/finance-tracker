<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Monthly budgets') }}
                </h2>
                <div class="flex items-center gap-2 mt-1 text-sm text-gray-500">
                    <a href="{{ route('budgets.index', ['month' => $prevMonth]) }}" class="hover:text-gray-700 px-1 py-0.5 rounded hover:bg-gray-100" title="{{ __('Previous month') }}">
                        &larr;
                    </a>
                    <span class="font-medium text-gray-900">{{ $monthLabel }}</span>
                    <a href="{{ route('budgets.index', ['month' => $nextMonth]) }}" class="hover:text-gray-700 px-1 py-0.5 rounded hover:bg-gray-100" title="{{ __('Next month') }}">
                        &rarr;
                    </a>
                </div>
            </div>

            <div class="flex items-center gap-3" x-data>
                <button type="button" @click="$dispatch('open-budget-modal', { isEditing: false })" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    {{ __('Add budget') }}
                </button>
            </div>
        </div>
    </x-slot>

    <div class="py-12" x-data="{
        showModal: false,
        isEditing: false,
        budgetId: null,
        category: '',
        amount: '',
        periodMonth: '{{ $currentMonth }}',
        openModal(detail) {
            this.isEditing = detail.isEditing;
            if (this.isEditing) {
                this.budgetId = detail.id;
                this.category = detail.category;
                this.amount = detail.budget_amount;
                this.periodMonth = '{{ $currentMonth }}';
            } else {
                this.budgetId = null;
                this.category = '';
                this.amount = '';
                this.periodMonth = '{{ $currentMonth }}';
            }
            this.showModal = true;
        }
    }" @open-budget-modal.window="openModal($event.detail)">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm rounded-lg px-4 py-3">
                    {{ session('status') }}
                </div>
            @endif

            <!-- Overall Summary Card -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 flex-1">
                        <div>
                            <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">{{ __('Total budget') }}</span>
                            <div class="mt-1 text-2xl font-bold text-gray-900">
                                {{ number_format($overview['total_budget'], 2) }}
                                <span class="text-sm font-normal text-gray-500">{{ $baseCurrency }}</span>
                            </div>
                        </div>

                        <div>
                            <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">{{ __('Total spent') }}</span>
                            <div class="mt-1 text-2xl font-bold text-gray-900">
                                {{ number_format($overview['total_spent'], 2) }}
                                <span class="text-sm font-normal text-gray-500">{{ $baseCurrency }}</span>
                            </div>
                        </div>

                        <div>
                            <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">{{ __('Remaining allowance') }}</span>
                            <div class="mt-1 text-2xl font-bold {{ $overview['total_remaining'] < 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                                {{ number_format($overview['total_remaining'], 2) }}
                                <span class="text-sm font-normal text-gray-500">{{ $baseCurrency }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="md:w-64 border-t md:border-t-0 md:border-l md:pl-6 border-gray-100 flex flex-col justify-center">
                        <div class="flex items-center justify-between text-sm mb-2">
                            <span class="text-gray-600 font-medium">{{ __('Overall progress') }}</span>
                            <span class="font-bold text-gray-900">{{ $overview['percentage_used'] }}%</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-3 overflow-hidden">
                            @php
                                $barColor = match ($overview['status']) {
                                    'exceeded' => 'bg-rose-500',
                                    'warning' => 'bg-amber-500',
                                    default => 'bg-emerald-500',
                                };
                            @endphp
                            <div class="{{ $barColor }} h-3 rounded-full transition-all duration-300" style="width: {{ min($overview['percentage_used'], 100) }}%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Category Budgets Grid -->
            @if (empty($overview['items']))
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 14l6-6m0 0l-6-6m6 6H3" />
                    </svg>
                    <h3 class="mt-2 text-sm font-semibold text-gray-900">{{ __('No budgets set for this month.') }}</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ __('Set monthly spending limits for your categories and track your progress in real-time.') }}</p>
                    <div class="mt-6">
                        <button type="button" @click="$dispatch('open-budget-modal', { isEditing: false })" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                            {{ __('Add budget') }}
                        </button>
                    </div>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach ($overview['items'] as $item)
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-100 p-5 flex flex-col justify-between">
                            <div>
                                <div class="flex items-start justify-between gap-2 mb-3">
                                    <h4 class="font-semibold text-gray-900 truncate" title="{{ $item['category'] }}">
                                        {{ $item['category'] }}
                                    </h4>
                                    @if ($item['status'] === 'exceeded')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-rose-50 text-rose-700 border border-rose-200">
                                            {{ __('Over budget') }}
                                        </span>
                                    @elseif ($item['status'] === 'warning')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200">
                                            {{ __('Near limit') }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            {{ __('Safe') }}
                                        </span>
                                    @endif
                                </div>

                                <div class="space-y-1 mb-4">
                                    <div class="text-sm text-gray-600">
                                        <span class="font-semibold text-gray-900">{{ number_format($item['spent_amount'], 2) }}</span>
                                        {{ __('spent of') }}
                                        <span class="font-semibold text-gray-900">{{ number_format($item['budget_amount'], 2) }}</span>
                                        <span class="text-xs text-gray-400">{{ $baseCurrency }}</span>
                                    </div>
                                    <div class="text-xs {{ $item['remaining_amount'] < 0 ? 'text-rose-600 font-medium' : 'text-gray-500' }}">
                                        {{ number_format(abs($item['remaining_amount']), 2) }} {{ $baseCurrency }}
                                        {{ $item['remaining_amount'] < 0 ? __('over limit') : __('remaining') }}
                                    </div>
                                </div>

                                <div class="w-full bg-gray-100 rounded-full h-2.5 overflow-hidden mb-4">
                                    @php
                                        $categoryBarColor = match ($item['status']) {
                                            'exceeded' => 'bg-rose-500',
                                            'warning' => 'bg-amber-500',
                                            default => 'bg-emerald-500',
                                        };
                                    @endphp
                                    <div class="{{ $categoryBarColor }} h-2.5 rounded-full transition-all duration-300" style="width: {{ min($item['percentage_used'], 100) }}%"></div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between pt-3 border-t border-gray-100 text-xs">
                                <span class="font-semibold text-gray-500">{{ $item['percentage_used'] }}%</span>
                                <div class="flex items-center gap-3">
                                    <button type="button" @click="$dispatch('open-budget-modal', {
                                        isEditing: true,
                                        id: {{ $item['id'] }},
                                        category: '{{ addslashes($item['category']) }}',
                                        budget_amount: '{{ $item['budget_amount'] }}'
                                    })" class="text-gray-500 hover:text-gray-900 font-medium">
                                        {{ __('Edit') }}
                                    </button>

                                    <form method="POST" action="{{ route('budgets.destroy', $item['id']) }}" onsubmit="return confirm('{{ __('Delete this budget?') }}');" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-rose-500 hover:text-rose-700 font-medium">
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
                <div x-show="showModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showModal = false" aria-hidden="true"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div x-show="showModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <form :action="isEditing ? '/budgets/' + budgetId : '{{ route('budgets.store') }}'" method="POST">
                        @csrf
                        <template x-if="isEditing">
                            <input type="hidden" name="_method" value="PUT">
                        </template>
                        <input type="hidden" name="period_month" :value="periodMonth">

                        <div class="bg-white px-6 pt-5 pb-4">
                            <div class="sm:flex sm:items-start">
                                <div class="w-full">
                                    <h3 class="text-lg leading-6 font-semibold text-gray-900" x-text="isEditing ? '{{ __('Edit budget') }}' : '{{ __('Add budget') }}'"></h3>
                                    <p class="mt-1 text-sm text-gray-500">
                                        {{ __('Target month') }}: <span class="font-medium text-gray-800" x-text="periodMonth"></span>
                                    </p>

                                    <div class="mt-4 space-y-4">
                                        <div>
                                            <x-input-label for="category" :value="__('Category')" />
                                            <input id="category" name="category" type="text" x-model="category" list="user-categories" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="{{ __('Select or type a category') }}">
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
                                            <input id="amount" name="amount" type="number" step="0.01" min="0.01" x-model="amount" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="1000.00">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50 px-6 py-3 flex flex-row-reverse gap-3">
                            <button type="submit" class="inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-gray-800 text-base font-medium text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:text-sm">
                                <span x-text="isEditing ? '{{ __('Update budget') }}' : '{{ __('Save budget') }}'"></span>
                            </button>
                            <button type="button" @click="showModal = false" class="inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:text-sm">
                                {{ __('Cancel') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
