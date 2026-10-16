<?php

namespace App\Http\Requests;

use App\Enums\TransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTransactionRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'occurred_at' => ['required', 'date'],
            'category' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
            'splits' => ['nullable', 'array'],
            'splits.*.category' => ['required_with:splits', 'string', 'max:255'],
            'splits.*.amount' => ['required_with:splits', 'numeric', 'min:0.01'],
            'splits.*.note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  Validator  $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $splits = $this->input('splits');

            if (is_array($splits) && count($splits) > 0) {
                if (count($splits) < 2) {
                    $validator->errors()->add('splits', 'A split transaction must have at least two categories.');

                    return;
                }

                $totalAmount = (float) $this->input('amount');
                $splitsTotal = array_sum(array_map(fn ($s) => (float) ($s['amount'] ?? 0), $splits));

                if (abs($totalAmount - $splitsTotal) > 0.01) {
                    $validator->errors()->add('splits', 'The sum of split amounts must equal the total transaction amount.');
                }
            }
        });
    }

    /**
     * Normalize input before validation runs.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('currency')) {
            $this->merge(['currency' => strtoupper(trim((string) $this->input('currency')))]);
        }
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.min' => 'The amount must be greater than zero.',
            'currency.alpha' => 'The currency must be a three-letter code.',
        ];
    }
}
