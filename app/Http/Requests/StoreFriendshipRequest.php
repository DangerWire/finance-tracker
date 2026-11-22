<?php

namespace App\Http\Requests;

use App\Enums\FriendshipStatus;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreFriendshipRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:255', 'exists:users,email'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $email = $this->input('email');
            $user = $this->user();

            if (! $email || ! $user) {
                return;
            }

            if (strtolower((string) $user->email) === strtolower((string) $email)) {
                $validator->errors()->add('email', __('You cannot send a friend request to yourself.'));

                return;
            }

            $targetUser = User::where('email', $email)->first();
            if (! $targetUser) {
                return;
            }

            $existing = Friendship::query()
                ->where(function ($q) use ($user, $targetUser) {
                    $q->where('user_id', $user->id)->where('friend_id', $targetUser->id);
                })
                ->orWhere(function ($q) use ($user, $targetUser) {
                    $q->where('user_id', $targetUser->id)->where('friend_id', $user->id);
                })
                ->first();

            if ($existing) {
                if ($existing->status === FriendshipStatus::Accepted) {
                    $validator->errors()->add('email', __('You are already friends with this user.'));
                } elseif ($existing->status === FriendshipStatus::Pending) {
                    $validator->errors()->add('email', __('A friend request is already pending with this user.'));
                }
            }
        });
    }
}
