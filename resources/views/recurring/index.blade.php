<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Recurring transactions') }}
            </h2>
            <div class="flex items-center gap-3">
                <form method="POST" action="{{ route('recurring.run') }}">
                    @csrf
                    <x-secondary-button>{{ __('Run now') }}</x-secondary-button>
                </form>
                <a href="{{ route('recurring.create') }}">
                    <x-primary-button>{{ __('Add recurring') }}</x-primary-button>
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm rounded-lg px-4 py-3">
                    {{ session('status') }}
                </div>
            @endif

            @if ($upcoming->isNotEmpty())
                <div class="bg-white p-4 shadow-sm sm:rounded-lg">
                    <h3 class="text-sm font-semibold text-gray-800">{{ __('Coming up') }}</h3>
                    <ul class="mt-3 space-y-2">
                        @foreach ($upcoming as $template)
                            @php $days = $template->daysUntilNextOccurrence($today); @endphp
                            <li class="flex flex-wrap items-baseline justify-between gap-2 text-sm">
                                <span class="text-gray-900">{{ $template->name }}</span>
                                <span class="text-gray-500">
                                    {{ $template->next_occurrence_on->format('Y-m-d') }}
                                    @if ($days === 0)
                                        <span class="font-medium text-amber-700">{{ __('today') }}</span>
                                    @else
                                        ({{ trans_choice('in :count day|in :count days', $days, ['count' => $days]) }})
                                    @endif
                                    · {{ number_format((float) $template->amount, 2) }} {{ $template->currency }}
                                    @unless ($template->auto_post)
                                        <span class="text-xs text-gray-400">{{ __('reminder only') }}</span>
                                    @endunless
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                @if ($recurring->isEmpty())
                    <div class="p-6 space-y-3">
                        <p class="text-gray-500">{{ __('No recurring transactions yet.') }}</p>
                        <a href="{{ route('recurring.create') }}" class="text-sm text-indigo-600 hover:text-indigo-900">
                            {{ __('Add recurring') }}
                        </a>
                    </div>
                @else
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Name') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Frequency') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Next') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Amount') }}</th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($recurring as $template)
                                <tr @class(['opacity-50' => ! $template->is_active])>
                                    <td class="px-6 py-4 text-sm">
                                        <a href="{{ route('recurring.show', $template) }}" class="text-gray-900 hover:underline">
                                            {{ $template->name }}
                                        </a>
                                        <span class="block text-xs text-gray-400">
                                            {{ $template->type->label() }} · {{ $template->category ?? '—' }}
                                            @unless ($template->auto_post)
                                                · {{ __('reminder only') }}
                                            @endunless
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-700">{{ $template->frequency->description() }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-700">
                                        {{ $template->next_occurrence_on->format('Y-m-d') }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-right text-gray-900">
                                        {{ number_format((float) $template->amount, 2) }}
                                        <span class="text-xs text-gray-400">{{ $template->currency }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                        <a href="{{ route('recurring.edit', $template) }}"
                                            class="text-indigo-600 hover:text-indigo-900">{{ __('Edit') }}</a>
                                        <form method="POST" action="{{ route('recurring.destroy', $template) }}"
                                            class="inline ml-3"
                                            onsubmit="return confirm('{{ __('Delete this template? Transactions it already made are kept.') }}');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-rose-600 hover:text-rose-900">
                                                {{ __('Remove') }}
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="border-t border-gray-100 px-6 py-4">
                        {{ $recurring->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>