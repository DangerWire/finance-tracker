<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-zinc-100 leading-tight flex items-center gap-2.5">
                    <span>{{ __('Friends') }}</span>
                    @php
                        $friendCount = isset($friends) ? $friends->count() : 0;
                    @endphp
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-mono-num font-medium bg-zinc-800 text-zinc-400 border border-zinc-700/60">
                        {{ $friendCount }}
                    </span>
                </h2>
                <p class="text-xs text-zinc-400 mt-1">
                    {{ __('Connect with friends to track peer-to-peer loans and settle balances.') }}
                </p>
            </div>
            <div class="flex items-center gap-2.5">
                <a href="{{ Route::has('debts.index') ? route('debts.index') : url('/debts') }}">
                    <x-secondary-button class="gap-1.5 text-xs">
                        <svg class="w-3.5 h-3.5 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        <span>{{ __('Debts') }}</span>
                    </x-secondary-button>
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
            {{-- Status & Error Flash Notifications --}}
            @if (session('status'))
                <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm rounded-xl px-4 py-3 flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-400 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            @if (session('error'))
                <div class="bg-rose-500/10 border border-rose-500/20 text-rose-300 text-sm rounded-xl px-4 py-3 flex items-center gap-2">
                    <svg class="w-4 h-4 text-rose-400 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            {{-- Add Friend Card --}}
            <div class="sure-card rounded-2xl p-6 sm:p-7 relative overflow-hidden">
                <div class="relative z-10">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-8 h-8 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                            </svg>
                        </div>
                        <h3 class="text-base font-semibold text-zinc-100">
                            {{ __('Search / add friend by email') }}
                        </h3>
                    </div>
                    <p class="text-xs text-zinc-400 mb-5 max-w-xl">
                        {{ __('Enter your friend\'s registered email address to send a connection request. Once accepted, you can record peer-to-peer loans together.') }}
                    </p>

                    <form method="POST" action="{{ Route::has('friends.store') ? route('friends.store') : url('/friends') }}" class="flex flex-col sm:flex-row gap-3 max-w-xl">
                        @csrf
                        <div class="relative flex-1">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-zinc-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"/>
                                </svg>
                            </div>
                            <input
                                type="email"
                                name="email"
                                id="email"
                                value="{{ old('email') }}"
                                required
                                placeholder="{{ __('Friend\'s email address') }}"
                                class="w-full pl-10 pr-4 py-2.5 bg-zinc-900/90 border border-zinc-700/80 rounded-xl text-sm text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition duration-150"
                            />
                        </div>
                        <x-primary-button class="justify-center py-2.5 px-5 shrink-0 gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                            </svg>
                            <span>{{ __('Send request') }}</span>
                        </x-primary-button>
                    </form>
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>
            </div>

            {{-- Received Requests Section --}}
            @php
                $pendingIncoming = isset($receivedRequests) ? $receivedRequests : collect();
            @endphp
            @if ($pendingIncoming->isNotEmpty())
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <h3 class="text-sm font-semibold text-zinc-100 uppercase tracking-wider text-[11px]">
                                {{ __('Received requests') }}
                            </h3>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-mono-num font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                {{ $pendingIncoming->count() }}
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach ($pendingIncoming as $requestItem)
                            @php
                                $requester = $requestItem->user ?? ($requestItem->friend_id === auth()->id() ? $requestItem->user : $requestItem->friend);
                                $reqId = $requestItem->id;
                                $reqName = $requester->name ?? __('Unknown user');
                                $reqEmail = $requester->email ?? '';
                                $initials = strtoupper(substr($reqName ?: $reqEmail, 0, 2));
                            @endphp
                            <div class="sure-card rounded-xl p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 sure-hover-lift">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-10 h-10 rounded-full bg-amber-500/10 border border-amber-500/20 text-amber-400 flex items-center justify-center font-semibold text-xs shrink-0 font-mono-num">
                                        {{ $initials }}
                                    </div>
                                    <div class="min-w-0 truncate">
                                        <h4 class="text-sm font-semibold text-zinc-100 truncate">{{ $reqName }}</h4>
                                        <p class="text-xs text-zinc-400 font-mono-num truncate">{{ $reqEmail }}</p>
                                        @if (isset($requestItem->created_at))
                                            <p class="text-[10px] text-zinc-500 mt-0.5">
                                                {{ $requestItem->created_at->diffForHumans() }}
                                            </p>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 shrink-0">
                                    {{-- Accept Form --}}
                                    <form method="POST" action="{{ Route::has('friends.update') ? route('friends.update', $reqId) : url('/friends/' . $reqId) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="accepted">
                                        <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 bg-emerald-500 hover:bg-emerald-400 text-zinc-950 font-semibold rounded-lg text-xs transition duration-150">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                            </svg>
                                            <span>{{ __('Accept') }}</span>
                                        </button>
                                    </form>

                                    {{-- Decline Form --}}
                                    <form method="POST" action="{{ Route::has('friends.update') ? route('friends.update', $reqId) : url('/friends/' . $reqId) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="declined">
                                        <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 bg-zinc-900 border border-zinc-700/80 hover:bg-zinc-800 text-zinc-400 hover:text-zinc-200 rounded-lg text-xs transition duration-150">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                            <span>{{ __('Decline') }}</span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Sent Pending Requests (Optional Section) --}}
            @php
                $pendingOutgoing = isset($sentRequests) ? $sentRequests : collect();
            @endphp
            @if ($pendingOutgoing->isNotEmpty())
                <div class="space-y-4">
                    <div class="flex items-center gap-2.5">
                        <h3 class="text-sm font-semibold text-zinc-100 uppercase tracking-wider text-[11px]">
                            {{ __('Sent requests') }}
                        </h3>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-mono-num font-semibold bg-zinc-800 text-zinc-400 border border-zinc-700">
                            {{ $pendingOutgoing->count() }}
                        </span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach ($pendingOutgoing as $sentItem)
                            @php
                                $targetFriend = $sentItem->friend ?? ($sentItem->user_id === auth()->id() ? $sentItem->friend : $sentItem->user);
                                $sentId = $sentItem->id;
                                $targetName = $targetFriend->name ?? __('Friend');
                                $targetEmail = $targetFriend->email ?? '';
                            @endphp
                            <div class="sure-card rounded-xl p-4 flex items-center justify-between gap-4">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-9 h-9 rounded-full bg-zinc-800 border border-zinc-700 text-zinc-400 flex items-center justify-center font-semibold text-xs font-mono-num shrink-0">
                                        {{ strtoupper(substr($targetName ?: $targetEmail, 0, 2)) }}
                                    </div>
                                    <div class="min-w-0 truncate">
                                        <h4 class="text-sm font-medium text-zinc-200 truncate">{{ $targetName }}</h4>
                                        <p class="text-xs text-zinc-500 font-mono-num truncate">{{ $targetEmail }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-zinc-800/80 text-zinc-400 border border-zinc-700/50">
                                        {{ __('Waiting for approval') }}
                                    </span>
                                    <form method="POST" action="{{ Route::has('friends.destroy') ? route('friends.destroy', $sentId) : url('/friends/' . $sentId) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-1.5 text-zinc-500 hover:text-rose-400 rounded-lg hover:bg-zinc-800 transition" title="{{ __('Cancel request') }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Friends List Section --}}
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-zinc-100 uppercase tracking-wider text-[11px]">
                        {{ __('Friends list') }}
                    </h3>
                </div>

                @php
                    $friendsList = isset($friends) ? $friends : collect();
                @endphp

                @if ($friendsList->isEmpty())
                    <div class="sure-card rounded-2xl p-8 sm:p-12 text-center">
                        <div class="w-12 h-12 rounded-2xl bg-zinc-800/80 border border-zinc-700/60 flex items-center justify-center text-zinc-400 mx-auto mb-4">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                        </div>
                        <h4 class="text-sm font-semibold text-zinc-200 mb-1">
                            {{ __('No friends yet.') }}
                        </h4>
                        <p class="text-xs text-zinc-400 max-w-sm mx-auto">
                            {{ __('No friends yet. Add friends by their email above to start recording shared loans.') }}
                        </p>
                    </div>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach ($friendsList as $item)
                            @php
                                // Item may be User model or Friendship model
                                if ($item instanceof \App\Models\User) {
                                    $friendUser = $item;
                                    $friendshipId = $item->pivot->id ?? $item->id;
                                    $connectedAt = isset($item->pivot->created_at) ? \Carbon\Carbon::parse($item->pivot->created_at) : ($item->created_at ?? null);
                                } else {
                                    $friendUser = $item->user_id === auth()->id() ? ($item->friend ?? $item->user) : ($item->user ?? $item->friend);
                                    $friendshipId = $item->id;
                                    $connectedAt = $item->created_at ?? null;
                                }

                                $displayName = $friendUser->name ?? __('Friend');
                                $displayEmail = $friendUser->email ?? '';
                                $initials = strtoupper(substr($displayName ?: $displayEmail, 0, 2));
                            @endphp
                            <div class="sure-card rounded-xl p-5 sure-hover-lift flex flex-col justify-between">
                                <div class="flex items-start justify-between gap-3 mb-4">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="w-10 h-10 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-center font-semibold text-xs font-mono-num shrink-0">
                                            {{ $initials }}
                                        </div>
                                        <div class="min-w-0 truncate">
                                            <h4 class="text-sm font-semibold text-zinc-100 truncate">{{ $displayName }}</h4>
                                            <p class="text-xs text-zinc-400 font-mono-num truncate">{{ $displayEmail }}</p>
                                        </div>
                                    </div>
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-mono-num font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 shrink-0">
                                        {{ __('Connected') }}
                                    </span>
                                </div>

                                <div class="pt-3 border-t border-white/[0.06] flex items-center justify-between text-xs text-zinc-500">
                                    <div class="flex items-center gap-1 text-[11px]">
                                        <svg class="w-3.5 h-3.5 text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                        <span>
                                            @if ($connectedAt)
                                                {{ __('Connected') }} {{ $connectedAt->format('M d, Y') }}
                                            @else
                                                {{ __('Connected') }}
                                            @endif
                                        </span>
                                    </div>

                                    <form method="POST" action="{{ Route::has('friends.destroy') ? route('friends.destroy', $friendshipId) : url('/friends/' . $friendshipId) }}" onsubmit="return confirm('{{ __('Are you sure you want to remove this friend?') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex items-center gap-1 text-zinc-500 hover:text-rose-400 transition duration-150 py-1 px-2 rounded hover:bg-rose-500/10 text-xs" title="{{ __('Remove friend') }}">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                            <span>{{ __('Remove') }}</span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
