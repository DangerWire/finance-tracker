<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Past imports') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm rounded-lg px-4 py-3">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                @if ($imports->isEmpty())
                    <div class="p-6 space-y-3">
                        <p class="text-gray-500">{{ __('Nothing imported yet.') }}</p>
                        <a href="{{ route('transaction-imports.create') }}" class="text-sm text-indigo-600 hover:text-indigo-900">
                            {{ __('Import transactions') }}
                        </a>
                    </div>
                @else
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                    {{ __('File') }}
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                    {{ __('Imported') }}
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                    {{ __('Rows') }}
                                </th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($imports as $import)
                                <tr>
                                    <td class="px-6 py-4 text-sm text-gray-700">{{ $import->filename }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        {{ $import->created_at->format('Y-m-d H:i') }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900 text-right">
                                        {{ number_format($import->transactions()->count()) }}
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm whitespace-nowrap">
                                        <form method="POST"
                                            action="{{ route('transaction-imports.destroy', $import) }}"
                                            class="inline"
                                            onsubmit="return confirm('{{ __('This deletes every transaction from this import.') }}');">
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
                @endif
            </div>

            <a href="{{ route('transaction-imports.create') }}">
                <x-primary-button>{{ __('Import transactions') }}</x-primary-button>
            </a>
        </div>
    </div>
</x-app-layout>