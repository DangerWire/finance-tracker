<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-white">{{ $recurring->name }}</h1>
                <p class="text-xs text-zinc-400 mt-1">{{ $recurring->type->label() }} · {{ $recurring->category ?? '—' }}</p>
            </div>
            <div class="flex items-center gap-2.5">
                <a href="{{ route('recurring.edit', $recurring) }}" class="inline-flex items-center px-3 py-1.5 rounded-lg border border-white/[0.08] bg-zinc-900/60 text-xs font-medium text-zinc-300 hover:text-white hover:bg-zinc-800 transition">
                    {{ __('Edit') }}
                </a>
                <a href="{{ route('recurring.index') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-white/[0.08] bg-zinc-900/60 text-xs font-medium text-zinc-300 hover:text-white hover:bg-zinc-800 transition">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                    {{ __('Back to recurring') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="sure-card p-4 rounded-2xl">
                    <p class="text-xs text-zinc-400">{{ __('Amount') }}</p>
                    <p class="mt-1.5 text-xl font-semibold font-mono-num text-white">
                        {{ number_format((float) $recurring->amount, 2) }}
                        <span class="text-xs font-normal text-zinc-500">{{ $recurring->currency }}</span>
                    </p>
                </div>
                <div class="sure-card p-4 rounded-2xl">
                    <p class="text-xs text-zinc-400">{{ __('Frequency') }}</p>
                    <p class="mt-1.5 text-lg font-semibold text-white">{{ $recurring->frequency->description() }}</p>
                </div>
                <div class="sure-card p-4 rounded-2xl">
                    <p class="text-xs text-zinc-400">{{ __('Next occurrence') }}</p>
                    <p class="mt-1.5 text-lg font-semibold font-mono-num text-white">
                        {{ $recurring->next_occurrence_on->format('Y-m-d') }}
                    </p>
                </div>
                <div class="sure-card p-4 rounded-2xl">
                    <p class="text-xs text-zinc-400">{{ __('Posting') }}</p>
                    <p class="mt-1.5 text-lg font-semibold text-white">
                        {{ $recurring->auto_post ? __('Automatic') : __('Reminder only') }}
                    </p>
                </div>
            </div>

            <div class="sure-card p-6 rounded-2xl space-y-2.5 text-sm text-zinc-300">
                <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 mb-3">{{ __('Template details') }}</h2>
                <p><span class="text-zinc-500">{{ __('Status') }}:</span> <span class="text-zinc-200">{{ $recurring->is_active ? __('Active') : __('Paused') }}</span></p>
                <p><span class="text-zinc-500">{{ __('Type') }}:</span> {{ $recurring->type->label() }}</p>
                <p><span class="text-zinc-500">{{ __('Category') }}:</span> {{ $recurring->category ?? '—' }}</p>
                <p><span class="text-zinc-500">{{ __('Starts on') }}:</span> <span class="font-mono-num">{{ $recurring->starts_on->format('Y-m-d') }}</span></p>
                @if ($recurring->ends_on)
                    <p><span class="text-zinc-500">{{ __('Ends on') }}:</span> <span class="font-mono-num">{{ $recurring->ends_on->format('Y-m-d') }}</span></p>
                @endif
                @if ($recurring->remind_days_before !== null)
                    <p>
                        <span class="text-zinc-500">{{ __('Reminder') }}:</span>
                        {{ trans_choice('one day before|:count days before', $recurring->remind_days_before, ['count' => $recurring->remind_days_before]) }}
                    </p>
                @endif
                @if ($recurring->note)
                    <p><span class="text-zinc-500">{{ __('Note') }}:</span> {{ $recurring->note }}</p>
                @endif
            </div>

            <div class="sure-card rounded-2xl overflow-hidden">
                <div class="border-b border-white/[0.06] px-6 py-4">
                    <h3 class="text-sm font-semibold text-white">{{ __('Transactions it created') }}</h3>
                </div>

                @if ($generated->isEmpty())
                    <p class="p-6 text-sm text-zinc-400">{{ __('Nothing generated yet.') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-white/[0.06]">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">{{ __('Due date') }}</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">{{ __('Posted') }}</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-zinc-500">{{ __('Amount') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/[0.05]">
                                @foreach ($generated as $transaction)
                                    <tr class="hover:bg-white/[0.02] transition-colors">
                                        <td class="px-6 py-3 text-xs font-mono-num text-zinc-300">
                                            {{ $transaction->occurrence_on?->format('Y-m-d') ?? '—' }}
                                        </td>
                                        <td class="px-6 py-3 text-xs font-mono-num text-zinc-300">
                                            {{ $transaction->occurred_at->format('Y-m-d') }}
                                        </td>
                                        <td class="px-6 py-3 text-sm text-right font-mono-num text-zinc-100">
                                            {{ number_format((float) $transaction->amount, 2) }}
                                            <span class="text-xs text-zinc-500 ml-1">{{ $transaction->currency }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="border-t border-white/[0.06] px-6 py-4">
                        {{ $generated->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
