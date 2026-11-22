@php
    /**
     * @var \App\Models\RecurringTransaction $recurring
     * @var array<int, \App\Enums\TransactionType> $types
     * @var array<int, \App\Enums\RecurringFrequency> $frequencies
     * @var array<int, string> $categories
     */
    $isEdit = $recurring->exists;
    $fieldClass = 'mt-1 block w-full rounded-lg bg-zinc-900/80 border border-zinc-800 text-zinc-100 placeholder-zinc-500 text-sm shadow-inner focus:border-zinc-500 focus:ring-1 focus:ring-zinc-500 focus:outline-none transition duration-150 px-3 py-2';
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
        <select id="type" name="type" required class="{{ $fieldClass }} [color-scheme:dark] cursor-pointer">
            @foreach ($types as $type)
                <option value="{{ $type->value }}" class="bg-zinc-900 text-zinc-100"
                    @selected(old('type', $recurring->type?->value ?? 'expense') === $type->value)>
                    {{ $type->label() }}
                </option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('type')" />
    </div>

    <div>
        <x-input-label for="amount" :value="__('Amount')" />
        <input id="amount" name="amount" type="number" step="0.01" min="0.01" required
            class="{{ $fieldClass }} font-mono-num"
            value="{{ old('amount', $recurring->amount) }}" placeholder="0.00">
        <x-input-error class="mt-2" :messages="$errors->get('amount')" />
    </div>

    <div>
        <x-input-label for="currency" :value="__('Currency')" />
        <input id="currency" name="currency" type="text" maxlength="3" required
            class="{{ $fieldClass }} uppercase font-mono" value="{{ old('currency', $recurring->currency) }}">
        <x-input-error class="mt-2" :messages="$errors->get('currency')" />
    </div>

    <div>
        <x-input-label for="frequency" :value="__('Frequency')" />
        <select id="frequency" name="frequency" required class="{{ $fieldClass }} [color-scheme:dark] cursor-pointer">
            @foreach ($frequencies as $frequency)
                <option value="{{ $frequency->value }}" class="bg-zinc-900 text-zinc-100"
                    @selected(old('frequency', $recurring->frequency?->value ?? 'monthly') === $frequency->value)>
                    {{ $frequency->description() }}
                </option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('frequency')" />
    </div>

    <div>
        <x-input-label for="starts_on" :value="__('Starts on')" />
        <input id="starts_on" name="starts_on" type="date" required class="{{ $fieldClass }} [color-scheme:dark]"
            value="{{ old('starts_on', $recurring->starts_on?->format('Y-m-d')) }}">
        <x-input-error class="mt-2" :messages="$errors->get('starts_on')" />
    </div>

    <div>
        <x-input-label for="ends_on" :value="__('Ends on (optional)')" />
        <input id="ends_on" name="ends_on" type="date" class="{{ $fieldClass }} [color-scheme:dark]"
            value="{{ old('ends_on', $recurring->ends_on?->format('Y-m-d')) }}">
        <x-input-error class="mt-2" :messages="$errors->get('ends_on')" />
    </div>

    <div>
        <x-input-label for="category" :value="__('Category')" />
        <input id="category" name="category" type="text" list="category-options" class="{{ $fieldClass }}"
            value="{{ old('category', $recurring->category) }}" placeholder="{{ __('e.g. Groceries, Entertainment, Utilities') }}">
        <datalist id="category-options">
            @foreach ($categories as $option)
                <option value="{{ $option }}"></option>
            @endforeach
        </datalist>
        <x-input-error class="mt-2" :messages="$errors->get('category')" />
    </div>

    <div>
        <x-input-label for="remind_days_before" :value="__('Remind me days before (optional)')" />
        <input id="remind_days_before" name="remind_days_before" type="number" min="0" max="60"
            class="{{ $fieldClass }} font-mono-num" value="{{ old('remind_days_before', $recurring->remind_days_before) }}" placeholder="0">
        <p class="mt-1.5 text-xs text-zinc-400">
            {{ __('Leave empty for no reminder. You will get one email per due date, not one per day.') }}
        </p>
        <x-input-error class="mt-2" :messages="$errors->get('remind_days_before')" />
    </div>

    <div class="sm:col-span-2 rounded-xl bg-zinc-900/40 border border-white/[0.06] p-4 space-y-3.5">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-zinc-500">{{ __('Automation') }}</p>

        <label for="auto_post" class="flex items-start gap-3 text-sm text-zinc-200 cursor-pointer select-none">
            <input id="auto_post" name="auto_post" type="checkbox" value="1"
                @checked(old('auto_post', $recurring->auto_post ?? true))
                class="mt-0.5 rounded bg-zinc-900 border-zinc-700 text-emerald-500 accent-emerald-500 [color-scheme:dark] shadow-sm focus:ring-emerald-500/30 focus:ring-offset-0">
            <span>
                <span class="block text-xs font-medium text-zinc-200">{{ __('Create the transaction automatically') }}</span>
                <span class="block text-xs text-zinc-400 mt-0.5">
                    {{ __('Unticked, this only reminds you. Use it for a bill you have not paid yet.') }}
                </span>
            </span>
        </label>

        <div class="border-t border-white/[0.05] pt-3">
            <label for="is_active" class="flex items-center gap-3 text-xs font-medium text-zinc-200 cursor-pointer select-none">
                <input id="is_active" name="is_active" type="checkbox" value="1"
                    @checked(old('is_active', $recurring->is_active ?? true))
                    class="rounded bg-zinc-900 border-zinc-700 text-emerald-500 accent-emerald-500 [color-scheme:dark] shadow-sm focus:ring-emerald-500/30 focus:ring-offset-0">
                <span>{{ __('Active') }}</span>
            </label>
        </div>
    </div>

    <div class="sm:col-span-2">
        <x-input-label for="note" :value="__('Note')" />
        <textarea id="note" name="note" rows="3" class="{{ $fieldClass }}" placeholder="{{ __('Add details, reference numbers, or context...') }}">{{ old('note', $recurring->note) }}</textarea>
        <x-input-error class="mt-2" :messages="$errors->get('note')" />
    </div>
</div>

<div class="mt-8 pt-4 border-t border-white/[0.06] flex items-center gap-3">
    <x-primary-button>{{ $isEdit ? __('Save changes') : __('Create') }}</x-primary-button>
    <a href="{{ route('recurring.index') }}" class="px-4 py-2 rounded-lg text-xs font-medium text-zinc-400 hover:text-white hover:bg-zinc-800 transition">
        {{ __('Cancel') }}
    </a>
</div>
