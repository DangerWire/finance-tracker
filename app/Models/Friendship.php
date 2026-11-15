<?php

namespace App\Models;

use App\Enums\FriendshipStatus;
use Database\Factories\FriendshipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'friend_id', 'status'])]
class Friendship extends Model
{
    /** @use HasFactory<FriendshipFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => FriendshipStatus::class,
        ];
    }

    /**
     * The user who initiated the friendship request.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The recipient user of the friendship request.
     *
     * @return BelongsTo<User, $this>
     */
    public function friend(): BelongsTo
    {
        return $this->belongsTo(User::class, 'friend_id');
    }

    /**
     * Resolve the other user in this friendship relative to the given or current user.
     */
    public function otherUser(?User $currentUser = null): ?User
    {
        $currentUser ??= auth()->user();

        if (! $currentUser) {
            return $this->friend ?? $this->user;
        }

        return $this->user_id === $currentUser->id ? $this->friend : $this->user;
    }

    /**
     * Accessor for friend_user (the counterpart user in this friendship).
     */
    public function getFriendUserAttribute(): ?User
    {
        return $this->otherUser();
    }

    /**
     * Accessor for the other user's name.
     */
    public function getNameAttribute(): ?string
    {
        return $this->otherUser()?->name;
    }

    /**
     * Accessor for the other user's email.
     */
    public function getEmailAttribute(): ?string
    {
        return $this->otherUser()?->email;
    }
}
