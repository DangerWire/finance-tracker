<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Add recurring transaction') }}
            </h2>
            <a href="{{ route('recurring.index') }}">
                <x-primary-button>{{ __('Back') }}</x-primary-button>
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="mb-6 bg-rose-50 border border-rose-200 text-rose-800 text-sm rounded-lg px-4 py-3">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('recurring.store') }}">
                    @csrf

                    @include('recurring.partials.form')
                </form>
            </div>
        </div>
    </div>
</x-app-layout>