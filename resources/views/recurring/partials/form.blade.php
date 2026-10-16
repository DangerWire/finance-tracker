@php
    /**
     * @var \App\Models\RecurringTransaction $recurring
     * @var array<int, \App\Enums\TransactionType> $types
     * @var array<int, \App\Enums\RecurringFrequency> $frequencies
     * @var array<int, string> $categories
     */
    $isEdit = $recurring->exists;
    $fieldClass = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
@endphp

<div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <x-input-label for="name" :value="__('Name')" />
        <input id="name" name="name" type="text" required class="{{ $fieldClass }}"
            value="{{ old('name', $recurring->name) }}"
            placeholder="{{ __('Rent, salary, phone bill…') }}">
        <x-input-error class="mt-2" :messages="$errors->get('name')" />
    </div>

    <div>
        <x-input-label for="type" :value="__('Type')" />
        <select id="type" name="type" required class="{{ $fieldClass }}">
            @foreach ($types as $type)
                <option value="{{ $type->value }}" @selected(old('type', $type?->value ?? 'expense') === $type->value)>
                    {{ $type->label() }}
                </option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('type')" />
    </div>

    <div>
        <x-input-label for="amount" :value="__('Amount')" />
        <input id="amount" name="amount" type="number" step="0.01" min="0.01" required class="{{ $fieldClass }}"
            value="{{ old('amount', $recurring->amount) }}">
        <x-input-error class="mt-2" :messages="$errors->get('amount')" />
    </div>

    <div>
        <x-input-label for="currency" :value="__('Currency')" />
        <input id="currency" name="currency" type="text" maxlength="3" required
            class="{{ $fieldClass }} uppercase" value="{{ old('currency', $recurring->currency) }}">
        <x-input-error class="mt-2" :messages="$errors->get('currency')" />
    </div>

    <div>
        <x-input-label for="frequency" :value="__('Frequency')" />
        <select id="frequency" name="frequency" required class="{{ $fieldClass }}">
            @foreach ($frequencies as $frequency)
                <option value="{{ $frequency->value }}"
                    @selected(old('frequency', $frequency?->value ?? 'monthly') === $frequency->value)>
                    {{ $frequency->description() }}
                </option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('frequency')" />
    </div>

    <div>
        <x-input-label for="starts_on" :value="__('Starts on')" />
        <input id="starts_on" name="starts_on" type="date" required class="{{ $fieldClass }}"
            value="{{ old('starts_on', $recurring->starts_on?->format('Y-m-d')) }}">
        <x-input-error class="mt-2" :messages="$errors->get('starts_on')" />
    </div>

    <div>
        <x-input-label for="ends_on" :value="__('Ends on (optional)')" />
        <input id="ends_on" name="ends_on" type="date" class="{{ $fieldClass }}"
            value="{{ old('ends_on', $recurring->ends_on?->format('Y-m-d')) }}">
        <x-input-error class="mt-2" :messages="$errors->get('ends_on')" />
    </div>

    <div>
        <x-input-label for="category" :value="__('Category')" />
        <input id="category" name="category" type="text" list="category-options" class="{{ $fieldClass }}"
            value="{{ old('category', $recurring->category) }}">
        <datalist id="category-options">
            @foreach ($categories as $option)
                <option value="{{ $option }}"></option>
            @endforeach
        </datalist>
        <x-input-error class="mt-2" :messages="$errors->get('category')" />
    </div>

    <div class="sm:col-span-2">
        <x-input-label for="remind_days_before" :value="__('Remind me days before (optional)')" />
        <input id="remind_days_before" name="remind_days_before" type="number" min="0" max="60"
            class="{{ $fieldClass }}" value="{{ old('remind_days_before', $recurring->remind_days_before) }}">
        <p class="mt-1 text-xs text-gray-500">
            {{ __('Leave empty for no reminder. You will get one email per due date, not one per day.') }}
        </p>
        <x-input-error class="mt-2" :messages="$errors->get('remind_days_before')" />
    </div>

    <div class="sm:col-span-2">
        <label for="auto_post" class="flex items-start gap-2 text-sm text-gray-700">
            <input id="auto_post" name="auto_post" type="checkbox" value="1"
                @checked(old('auto_post', $recurring->auto_post ?? true))
                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 mt-0.5">
            <span>
                {{ __('Create the transaction automatically') }}
                <span class="block text-xs text-gray-500">
                    {{ __('Unticked, this only reminds you. Use it for a bill you have not paid yet.') }}
                </span>
            </span>
        </label>
    </div>

    <div class="sm:col-span-2">
        <label for="is_active" class="flex items-center gap-2 text-sm text-gray-700">
            <input id="is_active" name="is_active" type="checkbox" value="1"
                @checked(old('is_active', $recurring->is_active ?? true))
                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
            {{ __('Active') }}
        </label>
    </div>

    <div class="sm:col-span-2">
        <x-input-label for="note" :value="__('Note')" />
        <textarea id="note" name="note" rows="3" class="{{ $fieldClass }}">{{ old('note', $recurring->note) }}</textarea>
        <x-input-error class="mt-2" :messages="$errors->get('note')" />
    </div>
</div>

<div class="mt-6 flex items-center gap-4">
    <x-primary-button>{{ $isEdit ? __('Save changes') : __('Create') }}</x-primary-button>
    <a href="{{ route('recurring.index') }}" class="text-sm text-gray-600 hover:text-gray-900">
        {{ __('Cancel') }}
    </a>
</div>