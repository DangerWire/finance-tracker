@php
    /** @var \App\Models\Transaction $transaction */
    $isEdit = $transaction->exists;
    $fieldClass = 'mt-1 block w-full rounded-lg bg-zinc-900/80 border border-zinc-800 text-zinc-100 placeholder-zinc-500 text-sm shadow-inner focus:border-zinc-500 focus:ring-1 focus:ring-zinc-500 focus:outline-none transition duration-150 px-3 py-2';
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
            <select id="type" name="type" required class="{{ $fieldClass }} cursor-pointer">
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" class="bg-zinc-900 text-zinc-100" @selected(old('type', $transaction->type?->value) === $type->value)>
                        {{ $type->label() }}
                    </option>
                @endforeach
            </select>
            <x-input-error class="mt-2" :messages="$errors->get('type')" />
        </div>

        <div>
            <x-input-label for="amount" :value="__('Amount')" />
            <input id="amount" name="amount" type="number" step="0.01" min="0.01" required class="{{ $fieldClass }} font-mono-num"
                x-model="totalAmount" value="{{ old('amount', $transaction->amount) }}" placeholder="0.00">
            <x-input-error class="mt-2" :messages="$errors->get('amount')" />
        </div>

        <div>
            <div class="flex items-center justify-between">
                <x-input-label for="currency" :value="__('Currency')" />
                <span class="inline-flex items-center gap-1 text-[11px] font-medium text-zinc-400 bg-zinc-800/80 px-1.5 py-0.5 rounded border border-white/[0.06]">
                    <svg class="w-3 h-3 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                    </svg>
                    {{ __('Locked') }}
                </span>
            </div>
            @php
                $userCurrency = strtoupper((string) (auth()->user()?->currency ?? $transaction->currency ?? config('finance.base_currency')));
            @endphp
            <div class="relative mt-1">
                <input id="currency_display" type="text" disabled readonly
                    class="{{ $fieldClass }} uppercase bg-zinc-950/60 text-zinc-400 border-zinc-800/80 cursor-not-allowed select-none font-mono"
                    value="{{ $userCurrency }}">
                <input type="hidden" id="currency" name="currency" value="{{ old('currency', $userCurrency) }}">
            </div>
            <p class="mt-1.5 text-xs text-zinc-400 flex items-center gap-1">
                <span>{{ __('Configured via') }}</span>
                <a href="{{ route('profile.edit') }}" class="text-emerald-400 hover:text-emerald-300 font-medium underline transition-colors">
                    {{ __('Settings') }}
                </a>
            </p>
            <x-input-error class="mt-2" :messages="$errors->get('currency')" />
        </div>

        <div>
            <x-input-label for="occurred_at" :value="__('Date')" />
            <input id="occurred_at" name="occurred_at" type="datetime-local" required class="{{ $fieldClass }} [color-scheme:dark]"
                value="{{ old('occurred_at', $transaction->occurred_at?->format('Y-m-d\TH:i')) }}">
            <x-input-error class="mt-2" :messages="$errors->get('occurred_at')" />
        </div>

        <div class="sm:col-span-2">
            <div class="flex items-center justify-between">
                <label class="inline-flex items-center cursor-pointer select-none">
                    <input type="checkbox" x-model="isSplit" class="rounded bg-zinc-900 border-zinc-800 text-emerald-500 shadow-sm focus:ring-emerald-500/20">
                    <span class="ml-2 text-xs font-medium text-zinc-300">{{ __('Split this transaction across multiple categories') }}</span>
                </label>
            </div>
        </div>

        <div class="sm:col-span-2" x-show="!isSplit">
            <x-input-label for="category" :value="__('Category')" />
            <input id="category" name="category" type="text" list="category-options" class="{{ $fieldClass }}"
                :disabled="isSplit" value="{{ old('category', $transaction->category) }}" placeholder="{{ __('e.g. Groceries, Entertainment, Utilities') }}">
            <x-input-error class="mt-2" :messages="$errors->get('category')" />
        </div>

        <template x-if="isSplit">
            <div class="sm:col-span-2 bg-[#09090b]/80 border border-white/[0.08] rounded-xl p-4 space-y-4">
                <div class="flex items-center justify-between">
                    <h4 class="text-xs font-semibold text-zinc-200 uppercase tracking-wider">{{ __('Split Line Items') }}</h4>
                    <div class="text-xs font-medium">
                        <span x-show="isBalanced" class="inline-flex items-center text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 px-2 py-0.5 rounded text-[11px]">
                            ✓ {{ __('Allocated in full') }}
                        </span>
                        <span x-show="!isBalanced && remainingAmount > 0" class="inline-flex items-center text-amber-400 bg-amber-500/10 border border-amber-500/20 px-2 py-0.5 rounded text-[11px]">
                            <span x-text="remainingAmount.toFixed(2)"></span> {{ __('remaining to allocate') }}
                        </span>
                        <span x-show="!isBalanced && remainingAmount < 0" class="inline-flex items-center text-rose-400 bg-rose-500/10 border border-rose-500/20 px-2 py-0.5 rounded text-[11px]">
                            <span x-text="Math.abs(remainingAmount).toFixed(2)"></span> {{ __('over allocated amount') }}
                        </span>
                    </div>
                </div>

                <x-input-error :messages="$errors->get('splits')" />

                <div class="space-y-3">
                    <template x-for="(split, index) in splits" :key="index">
                        <div class="p-3 bg-zinc-900/60 border border-zinc-800 rounded-lg shadow-sm space-y-3">
                            <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                                <div class="sm:col-span-5">
                                    <label class="block text-[11px] font-medium text-zinc-400 mb-1">{{ __('Category') }}</label>
                                    <input type="text" list="category-options" :name="`splits[${index}][category]`" x-model="split.category" required
                                        class="mt-1 block w-full rounded-lg bg-zinc-900/90 border border-zinc-800 text-zinc-100 placeholder-zinc-500 text-xs px-3 py-2 shadow-inner focus:border-zinc-500 focus:ring-1 focus:ring-zinc-500 focus:outline-none"
                                        placeholder="{{ __('e.g. Groceries') }}">
                                </div>
                                <div class="sm:col-span-3">
                                    <div class="flex items-center justify-between mb-1">
                                        <label class="block text-[11px] font-medium text-zinc-400">{{ __('Amount') }}</label>
                                        <button type="button" @click="assignRemaining(index)" x-show="remainingAmount > 0" class="text-[11px] text-emerald-400 hover:text-emerald-300 underline font-medium">
                                            {{ __('Fill remaining') }}
                                        </button>
                                    </div>
                                    <input type="number" step="0.01" min="0.01" :name="`splits[${index}][amount]`" x-model.number="split.amount" required
                                        class="mt-1 block w-full rounded-lg bg-zinc-900/90 border border-zinc-800 text-zinc-100 placeholder-zinc-500 text-xs px-3 py-2 font-mono-num shadow-inner focus:border-zinc-500 focus:ring-1 focus:ring-zinc-500 focus:outline-none"
                                        placeholder="0.00">
                                </div>
                                <div class="sm:col-span-3">
                                    <label class="block text-[11px] font-medium text-zinc-400 mb-1">{{ __('Note') }}</label>
                                    <input type="text" :name="`splits[${index}][note]`" x-model="split.note"
                                        class="mt-1 block w-full rounded-lg bg-zinc-900/90 border border-zinc-800 text-zinc-100 placeholder-zinc-500 text-xs px-3 py-2 shadow-inner focus:border-zinc-500 focus:ring-1 focus:ring-zinc-500 focus:outline-none"
                                        placeholder="{{ __('Optional') }}">
                                </div>
                                <div class="sm:col-span-1 flex justify-end">
                                    <button type="button" @click="removeLine(index)" :disabled="splits.length <= 2"
                                        class="p-2 text-zinc-500 hover:text-rose-400 disabled:opacity-30 disabled:cursor-not-allowed transition"
                                        title="{{ __('Remove split line') }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="pt-2 flex items-center justify-between border-t border-white/[0.04]">
                    <button type="button" @click="addLine" class="inline-flex items-center text-xs font-medium text-emerald-400 hover:text-emerald-300 transition">
                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        {{ __('Add split line') }}
                    </button>
                    <span class="text-xs text-zinc-400">
                        {{ __('Total') }}: <span class="font-mono text-zinc-200" x-text="allocatedTotal.toFixed(2)"></span> / <span class="font-mono text-zinc-200" x-text="totalAmount ? parseFloat(totalAmount).toFixed(2) : '0.00'"></span>
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
            <textarea id="note" name="note" rows="3" class="{{ $fieldClass }}" placeholder="{{ __('Add details, reference numbers, or context...') }}">{{ old('note', $transaction->note) }}</textarea>
            <x-input-error class="mt-2" :messages="$errors->get('note')" />
        </div>
    </div>

    <div class="mt-8 pt-4 border-t border-white/[0.06] flex items-center gap-3">
        <x-primary-button>{{ $isEdit ? __('Save changes') : __('Add transaction') }}</x-primary-button>
        <a href="{{ route('transactions.index') }}" class="px-4 py-2 rounded-lg text-xs font-medium text-zinc-400 hover:text-white hover:bg-zinc-800 transition">
            {{ __('Cancel') }}
        </a>
    </div>
</div>