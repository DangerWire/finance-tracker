<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportTransactionsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // A CSV export can plausibly run to a few megabytes; the parser
            // separately caps how many rows it will read.
            'file' => [
                'required',
                'file',
                'max:8192',
                'mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel,text/comma-separated-values,application/octet-stream',
                Rule::file()
                    ->extensions(['csv', 'txt', 'tsv'])
                    ->max('5mb'),
            ],
            // Header labels chosen for each field. Optional on the first
            // upload because the mapping is guessed from the headers when the
            // user has not chosen one; the remap step enforces it.
            'mapping' => ['sometimes', 'array'],
            'mapping.date' => ['nullable', 'string', 'max:255'],
            'mapping.amount' => ['nullable', 'string', 'max:255'],
            'mapping.type' => ['nullable', 'string', 'max:255'],
            'mapping.currency' => ['nullable', 'string', 'max:255'],
            'mapping.category' => ['nullable', 'string', 'max:255'],
            'mapping.note' => ['nullable', 'string', 'max:255'],
            // Whether a single amount column carries direction in its sign.
            'signed_amount' => ['nullable', 'in:yes,no'],
            // Re-uploaded file kept between the preview and the confirmation.
            'upload_key' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.max' => 'The file must be smaller than 8 MB.',
        ];
    }
}
