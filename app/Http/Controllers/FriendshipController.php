<?php

namespace App\Http\Controllers;

use App\Enums\FriendshipStatus;
use App\Http\Requests\StoreFriendshipRequest;
use App\Http\Requests\UpdateFriendshipRequest;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FriendshipController extends Controller
{
    /**
     * Display a listing of friends and friendship requests.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $receivedRequests = Friendship::query()
            ->where('friend_id', $user->id)
            ->where('status', FriendshipStatus::Pending)
            ->with('user')
            ->latest()
            ->get();

        $sentRequests = Friendship::query()
            ->where('user_id', $user->id)
            ->where('status', FriendshipStatus::Pending)
            ->with('friend')
            ->latest()
            ->get();

        $friends = Friendship::query()
            ->where('status', FriendshipStatus::Accepted)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhere('friend_id', $user->id);
            })
            ->with(['user', 'friend'])
            ->latest()
            ->get();

        return view('friends.index', [
            'friends' => $friends,
            'receivedRequests' => $receivedRequests,
            'sentRequests' => $sentRequests,
        ]);
    }

    /**
     * Store a newly created friendship request.
     */
    public function store(StoreFriendshipRequest $request): RedirectResponse
    {
        $targetUser = User::where('email', $request->validated('email'))->firstOrFail();

        $existing = Friendship::query()
            ->where(function ($q) use ($request, $targetUser) {
                $q->where('user_id', $request->user()->id)->where('friend_id', $targetUser->id);
            })
            ->orWhere(function ($q) use ($request, $targetUser) {
                $q->where('user_id', $targetUser->id)->where('friend_id', $request->user()->id);
            })
            ->first();

        if ($existing) {
            $existing->update([
                'user_id' => $request->user()->id,
                'friend_id' => $targetUser->id,
                'status' => FriendshipStatus::Pending,
            ]);
        } else {
            Friendship::create([
                'user_id' => $request->user()->id,
                'friend_id' => $targetUser->id,
                'status' => FriendshipStatus::Pending,
            ]);
        }

        return redirect()
            ->route('friends.index')
            ->with('status', __('Friend request sent.'));
    }

    /**
     * Update the specified friendship status (accept or decline).
     */
    public function update(UpdateFriendshipRequest $request, Friendship $friendship): RedirectResponse
    {
        abort_unless($friendship->friend_id === $request->user()->id, 403);
        abort_unless($friendship->status === FriendshipStatus::Pending, 400);

        $status = FriendshipStatus::from($request->validated('status'));
        $friendship->update([
            'status' => $status,
        ]);

        $message = $status === FriendshipStatus::Accepted
            ? __('Friend request accepted.')
            : __('Friend request declined.');

        return redirect()
            ->route('friends.index')
            ->with('status', $message);
    }

    /**
     * Remove the specified friend or cancel/delete friendship.
     */
    public function destroy(Request $request, Friendship $friendship): RedirectResponse
    {
        abort_unless(
            $friendship->user_id === $request->user()->id || $friendship->friend_id === $request->user()->id,
            403
        );

        $friendship->delete();

        return redirect()
            ->route('friends.index')
            ->with('status', __('Friend removed.'));
    }
}
