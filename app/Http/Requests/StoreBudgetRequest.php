<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBudgetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'category' => [
                'required',
                'string',
                'max:255',
                Rule::unique('budgets')->where(fn ($query) => $query
                    ->where('user_id', $this->user()->id)
                    ->where('period_month', $this->input('period_month'))),
            ],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99'],
            'period_month' => ['required', 'date_format:Y-m'],
        ];
    }
}
