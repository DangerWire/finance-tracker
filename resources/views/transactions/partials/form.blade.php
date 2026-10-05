@php
    /** @var \App\Models\Transaction $transaction */
    $isEdit = $transaction->exists;
    $fieldClass = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
@endphp

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
            value="{{ old('amount', $transaction->amount) }}">
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
        <x-input-label for="category" :value="__('Category')" />
        <input id="category" name="category" type="text" list="category-options" class="{{ $fieldClass }}"
            value="{{ old('category', $transaction->category) }}">
        <datalist id="category-options">
            @foreach (['food', 'transport', 'housing', 'entertainment', 'utilities', 'salary'] as $option)
                <option value="{{ $option }}"></option>
            @endforeach
        </datalist>
        <x-input-error class="mt-2" :messages="$errors->get('category')" />
    </div>

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