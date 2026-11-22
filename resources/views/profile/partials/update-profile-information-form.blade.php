<section>
    <header>
        <h2 class="text-base font-bold tracking-tight text-white">
            {{ __('Profile & Ledger Preferences') }}
        </h2>

        <p class="mt-1 text-xs text-zinc-400">
            {{ __("Update your account's profile information, email address, and primary ledger currency.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-xs mt-2 text-zinc-300">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="underline text-xs text-zinc-400 hover:text-white rounded focus:outline-none">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-xs text-emerald-400">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <!-- Primary / Main Currency Setting -->
        <div class="pt-4 border-t border-white/[0.06]">
            <div class="flex items-center justify-between mb-1.5">
                <x-input-label for="currency" :value="__('Primary Currency')" class="mb-0" />
                <span class="text-[11px] font-mono text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded border border-emerald-500/20">
                    {{ __('Active') }}: {{ $user->currency }}
                </span>
            </div>
            <p class="text-xs text-zinc-400 mb-3">
                {{ __('All income and expense transactions will be locked to this currency upon entry.') }}
            </p>

            <select id="currency" name="currency" class="w-full bg-zinc-900/80 border border-zinc-800 text-zinc-200 text-xs rounded-lg px-3 py-2.5 focus:border-zinc-700 focus:ring-1 focus:ring-zinc-700 transition">
                @foreach (config('finance.supported_currencies', []) as $code => $curr)
                    <option value="{{ $code }}" @selected(old('currency', $user->currency) === $code)>
                        {{ $code }} ({{ $curr['symbol'] }}) — {{ $curr['name'] }}
                    </option>
                @endforeach
            </select>
            <x-input-error class="mt-2" :messages="$errors->get('currency')" />

            <div class="mt-3.5 flex items-start gap-2.5 p-3 rounded-lg bg-zinc-900/60 border border-white/[0.04]">
                <input id="recalculate_existing" name="recalculate_existing" type="checkbox" value="1" checked
                    class="mt-0.5 rounded bg-zinc-900 border-zinc-800 text-emerald-500 focus:ring-emerald-500/20">
                <label for="recalculate_existing" class="text-xs cursor-pointer select-none">
                    <span class="font-medium text-zinc-200">{{ __('Recalculate historical transactions to new currency') }}</span>
                    <p class="text-[11px] text-zinc-400 mt-0.5">{{ __('Automatically converts existing base balances and reports to match your newly selected primary currency.') }}</p>
                </label>
            </div>
        </div>

        <div class="flex items-center gap-4 pt-2">
            <x-primary-button>{{ __('Save Changes') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2500)"
                    class="text-xs text-emerald-400 font-medium flex items-center gap-1"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                    {{ __('Settings saved successfully.') }}
                </p>
            @endif
        </div>
    </form>
</section>
