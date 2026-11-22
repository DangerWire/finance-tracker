<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-white">{{ __('Recurring transactions') }}</h1>
                <p class="text-xs text-zinc-400 mt-1">{{ __('Schedule automated bills, subscriptions, and regular income.') }}</p>
            </div>
            <div class="flex items-center gap-2.5">
                <form method="POST" action="{{ route('recurring.run') }}">
                    @csrf
                    <x-secondary-button type="submit">{{ __('Run now') }}</x-secondary-button>
                </form>
                <a href="{{ route('recurring.create') }}">
                    <x-primary-button>{{ __('Add recurring') }}</x-primary-button>
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm rounded-xl px-4 py-3">
                    {{ session('status') }}
                </div>
            @endif

            @if ($upcoming->isNotEmpty())
                <div class="sure-card rounded-2xl p-5">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-400">{{ __('Coming up') }}</h2>
                    </div>
                    <ul class="mt-3 divide-y divide-white/[0.05]">
                        @foreach ($upcoming as $template)
                            @php $days = $template->daysUntilNextOccurrence($today); @endphp
                            <li class="py-2.5 first:pt-0 last:pb-0 flex flex-wrap items-baseline justify-between gap-2 text-sm">
                                <a href="{{ route('recurring.show', $template) }}" class="font-medium text-zinc-100 hover:text-emerald-400 transition-colors">
                                    {{ $template->name }}
                                </a>
                                <span class="text-xs text-zinc-400 flex flex-wrap items-center gap-1.5">
                                    <span class="font-mono-num text-zinc-300">{{ $template->next_occurrence_on->format('Y-m-d') }}</span>
                                    @if ($days === 0)
                                        <span class="px-1.5 py-0.5 rounded bg-amber-500/15 border border-amber-500/30 text-amber-300 font-medium text-[11px]">{{ __('today') }}</span>
                                    @else
                                        <span>({{ trans_choice('in :count day|in :count days', $days, ['count' => $days]) }})</span>
                                    @endif
                                    <span class="text-zinc-600">·</span>
                                    <span class="font-mono-num font-medium text-zinc-100">{{ number_format((float) $template->amount, 2) }}</span>
                                    <span class="text-zinc-500">{{ $template->currency }}</span>
                                    @unless ($template->auto_post)
                                        <span class="px-1.5 py-0.5 rounded bg-zinc-800 text-zinc-400 text-[11px]">{{ __('reminder only') }}</span>
                                    @endunless
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="sure-card rounded-2xl overflow-hidden">
                @if ($recurring->isEmpty())
                    <div class="p-8 text-center space-y-3">
                        <p class="text-sm text-zinc-400">{{ __('No recurring transactions yet.') }}</p>
                        <a href="{{ route('recurring.create') }}" class="inline-flex items-center text-xs font-medium text-emerald-400 hover:text-emerald-300 transition-colors">
                            + {{ __('Add recurring') }}
                        </a>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-white/[0.06]">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">{{ __('Name') }}</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">{{ __('Frequency') }}</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">{{ __('Next') }}</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-zinc-500">{{ __('Amount') }}</th>
                                    <th class="px-6 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/[0.05]">
                                @foreach ($recurring as $template)
                                    <tr @class(['hover:bg-white/[0.02] transition-colors', 'opacity-50' => ! $template->is_active])>
                                        <td class="px-6 py-4 text-sm">
                                            <div class="flex items-center gap-2">
                                                <a href="{{ route('recurring.show', $template) }}" class="font-medium text-zinc-100 hover:text-emerald-400 transition-colors">
                                                    {{ $template->name }}
                                                </a>
                                                @unless ($template->is_active)
                                                    <span class="px-1.5 py-0.5 rounded bg-zinc-800 border border-white/[0.06] text-[10px] font-medium text-zinc-400">{{ __('Paused') }}</span>
                                                @endunless
                                            </div>
                                            <span class="block text-xs text-zinc-500 mt-0.5">
                                                {{ $template->type->label() }} · {{ $template->category ?? '—' }}
                                                @unless ($template->auto_post)
                                                    · {{ __('reminder only') }}
                                                @endunless
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-xs text-zinc-300">{{ $template->frequency->description() }}</td>
                                        <td class="px-6 py-4 text-xs font-mono-num text-zinc-300">
                                            {{ $template->next_occurrence_on->format('Y-m-d') }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-right font-mono-num">
                                            <span @class([
                                                'font-medium',
                                                'text-emerald-400' => $template->type === \App\Enums\TransactionType::Income,
                                                'text-zinc-100' => $template->type !== \App\Enums\TransactionType::Income,
                                            ])>
                                                {{ number_format((float) $template->amount, 2) }}
                                            </span>
                                            <span class="text-xs text-zinc-500 ml-1">{{ $template->currency }}</span>
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-right text-xs font-medium">
                                            <a href="{{ route('recurring.edit', $template) }}"
                                                class="text-zinc-300 hover:text-emerald-400 transition-colors">{{ __('Edit') }}</a>
                                            <form method="POST" action="{{ route('recurring.destroy', $template) }}"
                                                class="inline ml-3"
                                                onsubmit="return confirm('{{ __('Delete this template? Transactions it already made are kept.') }}');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-rose-400 hover:text-rose-300 transition-colors">
                                                    {{ __('Remove') }}
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="border-t border-white/[0.06] px-6 py-4">
                        {{ $recurring->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
