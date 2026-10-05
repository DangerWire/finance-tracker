<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit transaction') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('transactions.update', $transaction) }}">
                        @csrf
                        @method('PUT')

                        @include('transactions.partials.form', ['transaction' => $transaction, 'types' => $types])
                    </form>

                    <div class="mt-8 border-t border-gray-200 pt-6">
                        <form method="POST" action="{{ route('transactions.destroy', $transaction) }}"
                            onsubmit="return confirm('{{ __('Delete this transaction?') }}');">
                            @csrf
                            @method('DELETE')

                            <x-danger-button>{{ __('Delete transaction') }}</x-danger-button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>