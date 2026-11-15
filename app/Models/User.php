<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\FriendshipStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

#[Fillable(['name', 'email', 'password', 'prefers_base_currency', 'currency'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the user's primary currency, falling back to the configured base currency.
     *
     * @return Attribute<string, string>
     */
    protected function currency(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ?: (string) config('finance.base_currency', 'CNY'),
            set: fn (?string $value) => $value ? strtoupper($value) : null,
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'prefers_base_currency' => 'boolean',
        ];
    }

    /**
     * Get the transactions owned by the user.
     *
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get the CSV imports this user has run.
     *
     * @return HasMany<TransactionImport, $this>
     */
    public function imports(): HasMany
    {
        return $this->hasMany(TransactionImport::class);
    }

    /**
     * Get the recurring transaction templates this user owns.
     *
     * @return HasMany<RecurringTransaction, $this>
     */
    public function recurringTransactions(): HasMany
    {
        return $this->hasMany(RecurringTransaction::class);
    }

    /**
     * Get the monthly budgets owned by the user.
     *
     * @return HasMany<Budget, $this>
     */
    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    /**
     * Get the outgoing friendship requests initiated by the user.
     *
     * @return HasMany<Friendship, $this>
     */
    public function friendships(): HasMany
    {
        return $this->hasMany(Friendship::class, 'user_id');
    }

    /**
     * Get the incoming friendship requests sent to the user.
     *
     * @return HasMany<Friendship, $this>
     */
    public function receivedFriendships(): HasMany
    {
        return $this->hasMany(Friendship::class, 'friend_id');
    }

    /**
     * Get the accepted friends initiated by the user.
     *
     * @return BelongsToMany<User, $this>
     */
    public function friends(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'friendships', 'user_id', 'friend_id')
            ->wherePivot('status', FriendshipStatus::Accepted->value)
            ->withTimestamps();
    }

    /**
     * Get the accepted friends who initiated the friendship with the user.
     *
     * @return BelongsToMany<User, $this>
     */
    public function friendOf(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'friendships', 'friend_id', 'user_id')
            ->wherePivot('status', FriendshipStatus::Accepted->value)
            ->withTimestamps();
    }

    /**
     * Get all accepted friends in both directions as a merged collection.
     *
     * @return Collection<int, User>
     */
    public function allFriends(): Collection
    {
        return $this->friends()->get()
            ->merge($this->friendOf()->get())
            ->unique('id')
            ->values();
    }

    /**
     * Accessor to return all accepted friends in both directions when accessed as property.
     *
     * @return Collection<int, User>
     */
    public function getFriendsAttribute(): Collection
    {
        return $this->allFriends();
    }

    /**
     * Determine if the user is accepted friends with another user.
     */
    public function isFriendWith(User|int $user): bool
    {
        $userId = $user instanceof User ? $user->id : $user;

        return Friendship::query()
            ->where('status', FriendshipStatus::Accepted)
            ->where(function ($q) use ($userId) {
                $q->where(function ($sub) use ($userId) {
                    $sub->where('user_id', $this->id)->where('friend_id', $userId);
                })->orWhere(function ($sub) use ($userId) {
                    $sub->where('user_id', $userId)->where('friend_id', $this->id);
                });
            })
            ->exists();
    }

    /**
     * Get the debts created by this user.
     *
     * @return HasMany<Debt, $this>
     */
    public function debts(): HasMany
    {
        return $this->hasMany(Debt::class, 'creator_id');
    }

    /**
     * Get debts where this user is the lender.
     *
     * @return HasMany<Debt, $this>
     */
    public function debtsAsLender(): HasMany
    {
        return $this->hasMany(Debt::class, 'lender_id');
    }

    /**
     * Get debts where this user is the borrower.
     *
     * @return HasMany<Debt, $this>
     */
    public function debtsAsBorrower(): HasMany
    {
        return $this->hasMany(Debt::class, 'borrower_id');
    }
}
