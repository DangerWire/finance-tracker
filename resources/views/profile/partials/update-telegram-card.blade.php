<section>
    <header class="flex items-start justify-between">
        <div>
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-sky-400" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.74-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .38z"/>
                </svg>
                <h2 class="text-base font-bold tracking-tight text-white">
                    {{ __('Telegram Integration') }}
                </h2>
            </div>
            <p class="mt-1 text-xs text-zinc-400">
                {{ __('Connect your Telegram account to log expenses via chat commands, AI messages, and launch the Finance Tracker Mini App.') }}
            </p>
        </div>

        @if ($user->isTelegramLinked())
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                {{ __('Connected') }}
            </span>
        @else
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-medium bg-zinc-800 text-zinc-400 border border-white/[0.08]">
                {{ __('Not Connected') }}
            </span>
        @endif
    </header>

    @if (session('status') === 'telegram-link-token-generated')
        <div class="mt-4 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-xs text-emerald-400 flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
            </svg>
            <span>{{ __('Link token generated! Open the Telegram link below to connect.') }}</span>
        </div>
    @elseif (session('status') === 'telegram-unlinked')
        <div class="mt-4 p-3 rounded-xl bg-zinc-800/80 border border-white/[0.08] text-xs text-zinc-300 flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
            <span>{{ __('Your Telegram account has been disconnected.') }}</span>
        </div>
    @endif

    <div class="mt-6 space-y-4">
        @if ($user->isTelegramLinked())
            <div class="p-4 rounded-xl bg-white/[0.03] border border-white/[0.06] flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <div class="text-xs text-zinc-400">{{ __('Connected Telegram User') }}</div>
                    <div class="text-sm font-semibold text-white mt-0.5 flex items-center gap-1.5">
                        <span class="font-mono text-sky-400">{{ $user->telegram_username ? '@' . $user->telegram_username : 'ID: ' . $user->telegram_chat_id }}</span>
                    </div>
                    <div class="text-[11px] text-zinc-400 mt-1">
                        {{ __('Direct commands like /balance, /expense, and AI messages are currently active.') }}
                    </div>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    @if (config('services.telegram.bot_username'))
                        <a
                            href="https://t.me/{{ config('services.telegram.bot_username') }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-sky-500/10 text-sky-400 hover:bg-sky-500/20 border border-sky-500/20 transition-colors"
                        >
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.74-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .38z"/>
                            </svg>
                            {{ __('Open Bot in Telegram') }}
                        </a>
                    @endif

                    <form method="post" action="{{ route('profile.telegram.unlink') }}" onsubmit="return confirm('{{ __('Are you sure you want to disconnect your Telegram account?') }}');">
                        @csrf
                        @method('delete')
                        <button
                            type="submit"
                            class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg bg-rose-500/10 text-rose-400 hover:bg-rose-500/20 border border-rose-500/20 transition-colors"
                        >
                            {{ __('Disconnect') }}
                        </button>
                    </form>
                </div>
            </div>
        @else
            @php
                $hasValidToken = $user->telegram_link_token && $user->telegram_link_token_expires_at && $user->telegram_link_token_expires_at->isFuture();
                $botUsername = config('services.telegram.bot_username');
            @endphp

            @if ($hasValidToken)
                <div class="p-4 rounded-xl bg-white/[0.03] border border-sky-500/20 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-sky-400 flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-sky-400 animate-ping"></span>
                            {{ __('Link Token Active') }}
                        </span>
                        <span class="text-[11px] text-zinc-400 font-mono">
                            {{ __('Expires in 15 minutes') }}
                        </span>
                    </div>

                    <p class="text-xs text-zinc-300">
                        {{ __('Click the button below to connect with one tap in your Telegram app:') }}
                    </p>

                    @if ($botUsername)
                        <div class="flex flex-wrap items-center gap-2">
                            <a
                                href="https://t.me/{{ $botUsername }}?start={{ $user->telegram_link_token }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold rounded-lg bg-sky-500 hover:bg-sky-400 text-white shadow-lg shadow-sky-500/20 transition-all"
                            >
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.74-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .38z"/>
                                </svg>
                                {{ __('Open in Telegram & Connect') }}
                            </a>

                            <form method="post" action="{{ route('profile.telegram.link') }}">
                                @csrf
                                <button
                                    type="submit"
                                    class="inline-flex items-center px-3 py-2 text-xs font-medium rounded-lg bg-white/[0.05] hover:bg-white/[0.09] text-zinc-300 border border-white/[0.08] transition-colors"
                                >
                                    {{ __('Generate New Link') }}
                                </button>
                            </form>
                        </div>

                        <div class="pt-2 text-[11px] text-zinc-400">
                            {{ __('Or manually send') }} <code class="px-1.5 py-0.5 rounded bg-black/40 text-emerald-400 font-mono text-[10px]">/start {{ $user->telegram_link_token }}</code> {{ __('to') }} <span class="font-mono text-zinc-300">@{{ $botUsername }}</span>
                        </div>
                    @else
                        <div class="text-xs text-amber-400">
                            {{ __('TELEGRAM_BOT_USERNAME is not set in environment. Set TELEGRAM_BOT_USERNAME to enable direct linking.') }}
                        </div>
                    @endif
                </div>
            @else
                <div class="p-4 rounded-xl bg-white/[0.02] border border-white/[0.06] flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <div class="text-xs font-medium text-zinc-200">
                            {{ __('Connect in seconds') }}
                        </div>
                        <p class="text-[11px] text-zinc-400 mt-0.5">
                            {{ __('Generate a one-time connection link to authorize your Telegram account with Finance Tracker.') }}
                        </p>
                    </div>

                    <form method="post" action="{{ route('profile.telegram.link') }}">
                        @csrf
                        <button
                            type="submit"
                            class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-lg bg-sky-500 hover:bg-sky-400 text-white shadow-lg shadow-sky-500/20 transition-all"
                        >
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.74-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .38z"/>
                            </svg>
                            {{ __('Generate Connect Link') }}
                        </button>
                    </form>
                </div>
            @endif
        @endif
    </div>
</section>
