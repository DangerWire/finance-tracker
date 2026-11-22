<?php

namespace Database\Factories;

use App\Enums\FriendshipStatus;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Friendship>
 */
class FriendshipFactory extends Factory
{
    protected $model = Friendship::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'friend_id' => User::factory(),
            'status' => FriendshipStatus::Pending,
        ];
    }

    /**
     * Indicate that the friendship has been accepted.
     */
    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FriendshipStatus::Accepted,
        ]);
    }

    /**
     * Indicate that the friendship has been declined.
     */
    public function declined(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FriendshipStatus::Declined,
        ]);
    }
}
