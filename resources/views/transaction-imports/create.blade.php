<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Import transactions') }}
            </h2>
            <a href="{{ route('transaction-imports.index') }}" class="text-sm text-gray-600 hover:text-gray-900">
                {{ __('Past imports') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm rounded-lg px-4 py-3">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="bg-rose-50 border border-rose-200 text-rose-800 text-sm rounded-lg px-4 py-3">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <p class="text-sm text-gray-600 mb-4">
                    {{ __('Upload a CSV export from another app. Nothing is saved until you confirm the preview.') }}
                </p>

                <form id="upload-form"
                    method="POST"
                    action="{{ route('transaction-imports.preview') }}"
                    enctype="multipart/form-data"
                    class="flex flex-wrap items-end gap-4">
                    @csrf

                    <div class="flex-1 min-w-[16rem]">
                        <x-input-label for="file" :value="__('CSV file')" />
                        <input id="file" name="file" type="file" accept=".csv,.txt,.tsv" required
                            class="mt-1 block w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <x-primary-button>{{ __('Preview import') }}</x-primary-button>
                </form>
            </div>

            <div id="result" class="space-y-6"></div>
        </div>
    </div>

    @push('scripts')
        <script>
            const uploadForm = document.getElementById('upload-form');
            const result = document.getElementById('result');
            const csrf = uploadForm.querySelector('input[name="_token"]').value;
            const remapUrl = @json(route('transaction-imports.remap'));
            const storeUrl = @json(route('transaction-imports.store'));

            // Holds the staged key across the remap round trip.
            let uploadKey = null;
            let headers = [];

            const fields = [
                ['mapping[date]', @json(__('Date column')), true],
                ['mapping[amount]', @json(__('Amount column')), true],
                ['mapping[type]', @json(__('Type column (income/expense)')), false],
                ['mapping[currency]', @json(__('Currency column')), false],
                ['mapping[category]', @json(__('Category column')), false],
                ['mapping[note]', @json(__('Note column')), false],
            ];

            function selectMarkup(field, label, required, chosen) {
                const options = [`<option value="">— ${required ? '{{ __('choose a column') }}' : '{{ __('not in this file') }}'} —</option>`];

                headers.forEach((header) => {
                    options.push(`<option value="${header}" ${header === chosen ? 'selected' : ''}>${header}</option>`);
                });

                return `
                    <div>
                        <label class="block text-sm font-medium text-gray-700">${label}</label>
                        <select name="${field}" class="mapping mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            ${options.join('')}
                        </select>
                    </div>`;
            }

            function render(data) {
                uploadKey = data.upload_key;
                headers = data.headers || headers;

                const selects = fields.map(([name, label, required]) =>
                    selectMarkup(name, label, required, (data.mapping || {})[name.split('[')[1].replace(']', '')]
                )).join('');

                const problems = data.invalid_count > 0
                    ? `<div class="bg-amber-50 border border-amber-200 text-amber-900 text-sm rounded-lg px-4 py-3">
                        <p class="font-medium">{{ __('skipped') }} ${data.invalid_count}</p>
                        <ul class="mt-2 list-disc pl-5 space-y-1">
                            ${data.invalid.map((row) => `<li>{{ __('Line') }} ${row.line}: ${row.errors.join(' ')}</li>`).join('')}
                        </ul>
                        ${data.invalid_count > data.invalid.length ? `<p class="mt-2">… ${data.invalid_count - data.invalid.length} {{ __('more') }}</p>` : ''}
                    </div>`
                    : '';

                const rows = data.preview.map((row) => {
                    const a = row.attributes;

                    return `<tr class="border-t border-gray-100">
                        <td class="px-3 py-2 text-sm text-gray-500">${row.line}</td>
                        <td class="px-3 py-2 text-sm">${a.occurred_at}</td>
                        <td class="px-3 py-2 text-sm">${a.type}</td>
                        <td class="px-3 py-2 text-sm">${a.amount} ${a.currency}</td>
                        <td class="px-3 py-2 text-sm text-gray-500">${a.category || ''}</td>
                    </tr>`;
                }).join('');

                result.innerHTML = `
                    ${problems}
                    <div class="bg-white p-6 shadow-sm sm:rounded-lg space-y-4">
                        <p class="text-sm text-gray-700">
                            <strong>${data.filename}</strong> &mdash;
                            ${data.total_rows} {{ __('rows read,') }} ${data.valid_count} {{ __('ready to import.') }}
                        </p>

                        <p class="text-sm text-gray-500">
                            {{ __('Check which column is which. Pick "not in this file" for anything the export does not have.') }}
                        </p>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">${selects}</div>

                        <label class="flex items-start gap-2 text-sm text-gray-700">
                            <input type="checkbox" id="signed" class="signed rounded border-gray-300 text-indigo-600 mt-0.5">
                            <span>
                                {{ __('One amount column') }}
                                <span class="block text-xs text-gray-500">
                                    {{ __('Use it for both income and expense: negative amounts become expenses, positive amounts become income.') }}
                                </span>
                            </span>
                        </label>

                        <div class="flex items-center gap-4">
                            <button type="button" id="remap" class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 border border-gray-300 hover:bg-gray-50">
                                {{ __('Re-check mapping') }}
                            </button>
                            <form method="POST" action="${storeUrl}" class="inline">
                                <input type="hidden" name="_token" value="${csrf}">
                                <input type="hidden" name="upload_key" value="${data.upload_key}">
                                <button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">
                                    {{ __('Start import') }}
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs uppercase text-gray-500">{{ __('Line') }}</th>
                                    <th class="px-3 py-2 text-left text-xs uppercase text-gray-500">{{ __('Date') }}</th>
                                    <th class="px-3 py-2 text-left text-xs uppercase text-gray-500">{{ __('Type') }}</th>
                                    <th class="px-3 py-2 text-left text-xs uppercase text-gray-500">{{ __('Amount') }}</th>
                                    <th class="px-3 py-2 text-left text-xs uppercase text-gray-500">{{ __('Category') }}</th>
                                </tr>
                            </thead>
                            <tbody>${rows}</tbody>
                        </table>
                    </div>`;

                document.getElementById('remap').addEventListener('click', async (event) => {
                    event.preventDefault();
                    event.target.disabled = true;

                    const body = new FormData();
                    body.append('_token', csrf);
                    body.append('upload_key', uploadKey);
                    document.querySelectorAll('.mapping').forEach((el) => body.append(el.name, el.value));
                    body.append('signed_amount', document.getElementById('signed').checked ? 'yes' : 'no');

                    try {
                        const response = await fetch(remapUrl, { method: 'POST', body });
                        render(await response.json());
                    } finally {
                        event.target.disabled = false;
                    }
                });
            }

            uploadForm.addEventListener('submit', async (event) => {
                event.preventDefault();

                const button = uploadForm.querySelector('button');
                button.disabled = true;

                try {
                    const response = await fetch(uploadForm.action, {
                        method: 'POST',
                        body: new FormData(uploadForm),
                    });

                    const data = await response.json();

                    if (!response.ok) {
                        throw new Error(data.message || data.errors?.file?.[0] || 'The upload failed.');
                    }

                    render({ ...data, headers: data.headers });
                } catch (error) {
                    result.innerHTML = `<div class="bg-rose-50 border border-rose-200 text-rose-800 text-sm rounded-lg px-4 py-3">${error.message}</div>`;
                } finally {
                    button.disabled = false;
                }
            });
        </script>
    @endpush
</x-app-layout>