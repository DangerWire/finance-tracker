<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $recurring->name }}
            </h2>
            <a href="{{ route('recurring.index') }}">
                <x-primary-button>{{ __('Back') }}</x-primary-button>
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                <div class="bg-white p-4 shadow-sm sm:rounded-lg">
                    <p class="text-sm text-gray-500">{{ __('Amount') }}</p>
                    <p class="mt-1 text-xl font-semibold text-gray-900">
                        {{ number_format((float) $recurring->amount, 2) }}
                        <span class="text-sm font-normal text-gray-400">{{ $recurring->currency }}</span>
                    </p>
                </div>
                <div class="bg-white p-4 shadow-sm sm:rounded-lg">
                    <p class="text-sm text-gray-500">{{ __('Frequency') }}</p>
                    <p class="mt-1 text-xl font-semibold text-gray-900">{{ $recurring->frequency->description() }}</p>
                </div>
                <div class="bg-white p-4 shadow-sm sm:rounded-lg">
                    <p class="text-sm text-gray-500">{{ __('Next occurrence') }}</p>
                    <p class="mt-1 text-xl font-semibold text-gray-900">
                        {{ $recurring->next_occurrence_on->format('Y-m-d') }}
                    </p>
                </div>
                <div class="bg-white p-4 shadow-sm sm:rounded-lg">
                    <p class="text-sm text-gray-500">{{ __('Posting') }}</p>
                    <p class="mt-1 text-xl font-semibold text-gray-900">
                        {{ $recurring->auto_post ? __('Automatic') : __('Reminder only') }}
                    </p>
                </div>
            </div>

            <div class="bg-white p-6 shadow-sm sm:rounded-lg space-y-2 text-sm text-gray-700">
                <p><span class="text-gray-500">{{ __('Type') }}:</span> {{ $recurring->type->label() }}</p>
                <p><span class="text-gray-500">{{ __('Category') }}:</span> {{ $recurring->category ?? '—' }}</p>
                <p><span class="text-gray-500">{{ __('Starts on') }}:</span> {{ $recurring->starts_on->format('Y-m-d') }}</p>
                @if ($recurring->ends_on)
                    <p><span class="text-gray-500">{{ __('Ends on') }}:</span> {{ $recurring->ends_on->format('Y-m-d') }}</p>
                @endif
                @if ($recurring->remind_days_before !== null)
                    <p>
                        <span class="text-gray-500">{{ __('Reminder') }}:</span>
                        {{ trans_choice('one day before|:count days before', $recurring->remind_days_before, ['count' => $recurring->remind_days_before]) }}
                    </p>
                @endif
                @if ($recurring->note)
                    <p><span class="text-gray-500">{{ __('Note') }}:</span> {{ $recurring->note }}</p>
                @endif
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h3 class="font-semibold text-gray-800">{{ __('Transactions it created') }}</h3>
                </div>

                @if ($generated->isEmpty())
                    <p class="p-6 text-gray-500">{{ __('Nothing generated yet.') }}</p>
                @else
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Due date') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Posted') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Amount') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($generated as $transaction)
                                <tr>
                                    <td class="px-6 py-3 text-sm text-gray-700">
                                        {{ $transaction->occurrence_on?->format('Y-m-d') ?? '—' }}
                                    </td>
                                    <td class="px-6 py-3 text-sm text-gray-700">
                                        {{ $transaction->occurred_at->format('Y-m-d') }}
                                    </td>
                                    <td class="px-6 py-3 text-sm text-right text-gray-900">
                                        {{ number_format((float) $transaction->amount, 2) }}
                                        <span class="text-xs text-gray-400">{{ $transaction->currency }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="border-t border-gray-100 px-6 py-4">
                        {{ $generated->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>