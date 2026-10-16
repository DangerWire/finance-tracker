<?php

namespace App\Http\Requests;

use App\Enums\RecurringFrequency;
use App\Enums\TransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecurringTransactionRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(TransactionType::class)],
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'category' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
            'frequency' => ['required', Rule::enum(RecurringFrequency::class)],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            // An unchecked box submits nothing, so absence means false rather
            // than being a validation failure.
            'auto_post' => ['nullable', 'boolean'],
            'remind_days_before' => ['nullable', 'integer', 'min:0', 'max:60'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Normalize input before validation runs.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('currency')) {
            $this->merge(['currency' => strtoupper(trim((string) $this->input('currency')))]);
        }

        // Form checkboxes are absent when unticked; treat that as false so a
        // reminder-only template can actually be created.
        $this->merge([
            'auto_post' => $this->boolean('auto_post'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'remind_days_before.max' => 'A reminder cannot be more than 60 days ahead.',
        ];
    }
}
