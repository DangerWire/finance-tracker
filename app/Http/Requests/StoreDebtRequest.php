<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDebtRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'friend_id' => ['required', 'integer', 'exists:users,id'],
            'type' => ['required', 'string', Rule::in(['lent', 'borrowed'])],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'note' => ['nullable', 'string', 'max:255'],
            'due_date' => ['nullable', 'date'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $friendId = (int) $this->input('friend_id');
            $user = $this->user();

            if (! $user) {
                return;
            }

            if ($friendId === $user->id) {
                $validator->errors()->add('friend_id', __('You cannot create a loan with yourself.'));

                return;
            }

            if (! $user->isFriendWith($friendId)) {
                $validator->errors()->add('friend_id', __('You can only create loans with accepted friends.'));
            }
        });
    }
}
