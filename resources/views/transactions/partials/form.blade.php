@php
    /** @var \App\Models\Transaction $transaction */
    $isEdit = $transaction->exists;
    $fieldClass = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
    $existingSplits = old('splits');
    if ($existingSplits === null && $transaction->exists && $transaction->relationLoaded('splits') && $transaction->splits->isNotEmpty()) {
        $existingSplits = $transaction->splits->map(fn ($s) => [
            'category' => $s->category,
            'amount' => (string) $s->amount,
            'note' => $s->note ?? '',
        ])->toArray();
    }
    $hasSplits = ! empty($existingSplits);
    $initialSplits = $hasSplits ? array_values($existingSplits) : [
        ['category' => '', 'amount' => '', 'note' => ''],
        ['category' => '', 'amount' => '', 'note' => ''],
    ];
@endphp

<div x-data="{
    isSplit: {{ $hasSplits ? 'true' : 'false' }},
    totalAmount: '{{ old('amount', $transaction->amount ?? '') }}',
    splits: {{ Js::from($initialSplits) }},
    get allocatedTotal() {
        return this.splits.reduce((sum, item) => sum + (parseFloat(item.amount) || 0), 0);
    },
    get remainingAmount() {
        const total = parseFloat(this.totalAmount) || 0;
        return Math.round((total - this.allocatedTotal) * 100) / 100;
    },
    get isBalanced() {
        const total = parseFloat(this.totalAmount) || 0;
        return total > 0 && Math.abs(this.remainingAmount) < 0.01;
    },
    addLine() {
        this.splits.push({ category: '', amount: '', note: '' });
    },
    removeLine(index) {
        if (this.splits.length > 2) {
            this.splits.splice(index, 1);
        }
    },
    assignRemaining(index) {
        const total = parseFloat(this.totalAmount) || 0;
        const others = this.splits.reduce((sum, item, idx) => idx === index ? sum : sum + (parseFloat(item.amount) || 0), 0);
        const diff = Math.max(0, Math.round((total - others) * 100) / 100);
        this.splits[index].amount = diff > 0 ? diff.toFixed(2) : '0.00';
    }
}">
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
        <div>
            <x-input-label for="type" :value="__('Type')" />
            <select id="type" name="type" required class="{{ $fieldClass }}">
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected(old('type', $transaction->type?->value) === $type->value)>
                        {{ $type->label() }}
                    </option>
                @endforeach
            </select>
            <x-input-error class="mt-2" :messages="$errors->get('type')" />
        </div>

        <div>
            <x-input-label for="amount" :value="__('Amount')" />
            <input id="amount" name="amount" type="number" step="0.01" min="0.01" required class="{{ $fieldClass }}"
                x-model="totalAmount" value="{{ old('amount', $transaction->amount) }}">
            <x-input-error class="mt-2" :messages="$errors->get('amount')" />
        </div>

        <div>
            <x-input-label for="currency" :value="__('Currency')" />
            <input id="currency" name="currency" type="text" maxlength="3" required
                class="{{ $fieldClass }} uppercase" value="{{ old('currency', $transaction->currency ?? 'CNY') }}">
            <x-input-error class="mt-2" :messages="$errors->get('currency')" />
        </div>

        <div>
            <x-input-label for="occurred_at" :value="__('Date')" />
            <input id="occurred_at" name="occurred_at" type="datetime-local" required class="{{ $fieldClass }}"
                value="{{ old('occurred_at', $transaction->occurred_at?->format('Y-m-d\TH:i')) }}">
            <x-input-error class="mt-2" :messages="$errors->get('occurred_at')" />
        </div>

        <div class="sm:col-span-2">
            <div class="flex items-center justify-between">
                <label class="inline-flex items-center cursor-pointer">
                    <input type="checkbox" x-model="isSplit" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                    <span class="ml-2 text-sm font-medium text-gray-700">{{ __('Split this transaction across multiple categories') }}</span>
                </label>
            </div>
        </div>

        <div class="sm:col-span-2" x-show="!isSplit">
            <x-input-label for="category" :value="__('Category')" />
            <input id="category" name="category" type="text" list="category-options" class="{{ $fieldClass }}"
                :disabled="isSplit" value="{{ old('category', $transaction->category) }}">
            <x-input-error class="mt-2" :messages="$errors->get('category')" />
        </div>

        <template x-if="isSplit">
            <div class="sm:col-span-2 bg-gray-50 border border-gray-200 rounded-lg p-4 space-y-4">
                <div class="flex items-center justify-between">
                    <h4 class="text-sm font-semibold text-gray-800">{{ __('Split Line Items') }}</h4>
                    <div class="text-xs font-medium">
                        <span x-show="isBalanced" class="inline-flex items-center text-emerald-700 bg-emerald-50 px-2 py-1 rounded">
                            ✓ {{ __('Allocated in full') }}
                        </span>
                        <span x-show="!isBalanced && remainingAmount > 0" class="inline-flex items-center text-amber-700 bg-amber-50 px-2 py-1 rounded">
                            <span x-text="remainingAmount.toFixed(2)"></span> {{ __('remaining to allocate') }}
                        </span>
                        <span x-show="!isBalanced && remainingAmount < 0" class="inline-flex items-center text-rose-700 bg-rose-50 px-2 py-1 rounded">
                            <span x-text="Math.abs(remainingAmount).toFixed(2)"></span> {{ __('over allocated amount') }}
                        </span>
                    </div>
                </div>

                <x-input-error :messages="$errors->get('splits')" />

                <div class="space-y-3">
                    <template x-for="(split, index) in splits" :key="index">
                        <div class="p-3 bg-white border border-gray-200 rounded-md shadow-sm space-y-3">
                            <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                                <div class="sm:col-span-5">
                                    <label class="block text-xs font-medium text-gray-600">{{ __('Category') }}</label>
                                    <input type="text" list="category-options" :name="`splits[${index}][category]`" x-model="split.category" required
                                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        placeholder="{{ __('e.g. Groceries') }}">
                                </div>
                                <div class="sm:col-span-3">
                                    <div class="flex items-center justify-between">
                                        <label class="block text-xs font-medium text-gray-600">{{ __('Amount') }}</label>
                                        <button type="button" @click="assignRemaining(index)" x-show="remainingAmount > 0" class="text-xs text-indigo-600 hover:text-indigo-800 underline">
                                            {{ __('Fill remaining') }}
                                        </button>
                                    </div>
                                    <input type="number" step="0.01" min="0.01" :name="`splits[${index}][amount]`" x-model.number="split.amount" required
                                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        placeholder="0.00">
                                </div>
                                <div class="sm:col-span-3">
                                    <label class="block text-xs font-medium text-gray-600">{{ __('Note') }}</label>
                                    <input type="text" :name="`splits[${index}][note]`" x-model="split.note"
                                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        placeholder="{{ __('Optional') }}">
                                </div>
                                <div class="sm:col-span-1 flex justify-end">
                                    <button type="button" @click="removeLine(index)" :disabled="splits.length <= 2"
                                        class="p-2 text-gray-400 hover:text-rose-600 disabled:opacity-30 disabled:cursor-not-allowed"
                                        title="{{ __('Remove split line') }}">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="pt-2 flex items-center justify-between">
                    <button type="button" @click="addLine" class="inline-flex items-center text-xs font-medium text-indigo-600 hover:text-indigo-800">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        {{ __('Add split line') }}
                    </button>
                    <span class="text-xs text-gray-500">
                        {{ __('Total') }}: <span class="font-medium" x-text="allocatedTotal.toFixed(2)"></span> / <span class="font-medium" x-text="totalAmount ? parseFloat(totalAmount).toFixed(2) : '0.00'"></span>
                    </span>
                </div>
            </div>
        </template>

        <datalist id="category-options">
            @foreach ($categories as $option)
                <option value="{{ $option }}"></option>
            @endforeach
        </datalist>

        <div class="sm:col-span-2">
            <x-input-label for="note" :value="__('Note')" />
            <textarea id="note" name="note" rows="3" class="{{ $fieldClass }}">{{ old('note', $transaction->note) }}</textarea>
            <x-input-error class="mt-2" :messages="$errors->get('note')" />
        </div>
    </div>

    <div class="mt-6 flex items-center gap-4">
        <x-primary-button>{{ $isEdit ? __('Save changes') : __('Add transaction') }}</x-primary-button>
        <a href="{{ route('transactions.index') }}" class="text-sm text-gray-600 hover:text-gray-900">
            {{ __('Cancel') }}
        </a>
    </div>
</div>