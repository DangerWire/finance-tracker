<?php

namespace Tests\Feature;

use App\Enums\FriendshipStatus;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FriendshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_friends_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('friends.index'))
            ->assertOk()
            ->assertSee('Friends');
    }

    public function test_user_can_send_friend_request(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)
            ->post(route('friends.store'), ['email' => $other->email])
            ->assertRedirect(route('friends.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('friendships', [
            'user_id' => $user->id,
            'friend_id' => $other->id,
            'status' => FriendshipStatus::Pending->value,
        ]);
    }

    public function test_user_cannot_send_friend_request_to_self(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('friends.store'), ['email' => $user->email])
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('friendships', 0);
    }

    public function test_user_cannot_send_duplicate_friend_request(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Friendship::factory()->create([
            'user_id' => $user->id,
            'friend_id' => $other->id,
            'status' => FriendshipStatus::Pending,
        ]);

        $this->actingAs($user)
            ->post(route('friends.store'), ['email' => $other->email])
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('friendships', 1);
    }

    public function test_user_cannot_send_friend_request_when_reverse_request_is_pending(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        Friendship::factory()->create([
            'user_id' => $sender->id,
            'friend_id' => $recipient->id,
            'status' => FriendshipStatus::Pending,
        ]);

        $this->actingAs($recipient)
            ->post(route('friends.store'), ['email' => $sender->email])
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('friendships', 1);
    }

    public function test_user_cannot_send_friend_request_when_already_friends(): void
    {
        $user = User::factory()->create();
        $friend = User::factory()->create();

        Friendship::factory()->accepted()->create([
            'user_id' => $user->id,
            'friend_id' => $friend->id,
        ]);

        $this->actingAs($user)
            ->post(route('friends.store'), ['email' => $friend->email])
            ->assertSessionHasErrors('email');

        $this->actingAs($friend)
            ->post(route('friends.store'), ['email' => $user->email])
            ->assertSessionHasErrors('email');
    }

    public function test_user_can_accept_friend_request(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        $friendship = Friendship::factory()->create([
            'user_id' => $sender->id,
            'friend_id' => $recipient->id,
            'status' => FriendshipStatus::Pending,
        ]);

        $this->actingAs($recipient)
            ->patch(route('friends.update', $friendship), ['status' => 'accepted'])
            ->assertRedirect(route('friends.index'));

        $this->assertDatabaseHas('friendships', [
            'id' => $friendship->id,
            'status' => FriendshipStatus::Accepted->value,
        ]);

        $this->assertTrue($recipient->fresh()->isFriendWith($sender));
        $this->assertTrue($sender->fresh()->isFriendWith($recipient));
    }

    public function test_user_can_decline_friend_request(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        $friendship = Friendship::factory()->create([
            'user_id' => $sender->id,
            'friend_id' => $recipient->id,
            'status' => FriendshipStatus::Pending,
        ]);

        $this->actingAs($recipient)
            ->patch(route('friends.update', $friendship), ['status' => 'declined'])
            ->assertRedirect(route('friends.index'));

        $this->assertDatabaseHas('friendships', [
            'id' => $friendship->id,
            'status' => FriendshipStatus::Declined->value,
        ]);

        $this->assertFalse($recipient->fresh()->isFriendWith($sender));
    }

    public function test_sender_cannot_accept_own_friend_request(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        $friendship = Friendship::factory()->create([
            'user_id' => $sender->id,
            'friend_id' => $recipient->id,
            'status' => FriendshipStatus::Pending,
        ]);

        $this->actingAs($sender)
            ->patch(route('friends.update', $friendship), ['status' => 'accepted'])
            ->assertForbidden();
    }

    public function test_user_can_remove_friend(): void
    {
        $user = User::factory()->create();
        $friend = User::factory()->create();

        $friendship = Friendship::factory()->accepted()->create([
            'user_id' => $user->id,
            'friend_id' => $friend->id,
        ]);

        $this->actingAs($user)
            ->delete(route('friends.destroy', $friendship))
            ->assertRedirect(route('friends.index'));

        $this->assertDatabaseMissing('friendships', ['id' => $friendship->id]);
        $this->assertFalse($user->fresh()->isFriendWith($friend));
    }
}
