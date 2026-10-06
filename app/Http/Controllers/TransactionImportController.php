<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportTransactionsRequest;
use App\Models\TransactionImport;
use App\Services\TransactionImportParser;
use App\Services\TransactionImportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Bulk import of transactions from a CSV export.
 *
 * The flow is deliberately two steps. The file is parsed and mapped, the
 * result is shown, and only then is anything written. The staged payload is
 * authoritative for the confirmation step: the mapping that is applied is the
 * one that was previewed, not whatever the confirmation request happens to
 * send back.
 */
class TransactionImportController extends Controller
{
    /**
     * Where the parsed upload is held between preview and confirmation.
     */
    private const STAGING_DIRECTORY = 'imports';

    public function __construct(
        private readonly TransactionImportParser $parser,
        private readonly TransactionImportService $importer,
    ) {}

    /**
     * Show the upload form.
     */
    public function create(): View
    {
        return view('transaction-imports.create');
    }

    /**
     * List this user's past imports.
     */
    public function index(Request $request): View
    {
        return view('transaction-imports.index', [
            'imports' => $request->user()->imports()->latest()->get(),
        ]);
    }

    /**
     * Upload a file and preview what importing it would produce.
     *
     * Nothing reaches the transactions table here. The response reports how
     * many rows are usable and why the rest are not, so a mis-mapped column is
     * caught before any data is committed.
     */
    public function preview(ImportTransactionsRequest $request): JsonResponse
    {
        $parsed = $this->parser->parse($request->file('file'));

        // A guessed mapping makes the first preview useful instead of empty.
        // It is only a starting point; the user can correct it and re-check.
        $mapping = array_filter($request->input('mapping', []), fn ($value): bool => $value !== null && $value !== '');
        $mapping = $mapping === [] ? $this->importer->guessMapping($parsed['headers']) : $mapping;

        $signedAmount = (string) $request->input('signed_amount', 'no');

        $result = $this->importer->validate($parsed['rows'], $mapping, $signedAmount);

        $key = $this->stage($request->file('file')->getClientOriginalName(), [
            'headers' => $parsed['headers'],
            'rows' => $parsed['rows'],
            'mapping' => $mapping,
            'signed_amount' => $signedAmount,
        ]);

        return $this->respond(
            $result,
            $key,
            $request->file('file')->getClientOriginalName(),
            count($parsed['rows']),
            $mapping,
            $parsed['headers'],
        );
    }

    /**
     * Re-check the staged rows against a corrected mapping.
     *
     * The chosen mapping is written back into the staged payload, so the
     * confirmation step applies exactly what was just validated rather than
     * whatever the commit request happens to contain.
     */
    public function remap(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'upload_key' => ['required', 'string', 'max:100'],
            'mapping' => ['required', 'array'],
            'mapping.date' => ['required', 'string', 'max:255'],
            'mapping.amount' => ['required', 'string', 'max:255'],
            'signed_amount' => ['nullable', 'in:yes,no'],
        ]);

        $payload = $this->readStaged((string) $validated['upload_key']);

        if ($payload === null) {
            return response()->json(['message' => 'expired'], 410);
        }

        $mapping = array_filter($validated['mapping'], fn ($value): bool => $value !== null && $value !== '');

        $payload['mapping'] = $mapping;
        $payload['signed_amount'] = (string) ($validated['signed_amount'] ?? 'no');

        $this->writeStaged((string) $validated['upload_key'], $payload);

        $result = $this->importer->validate(
            $payload['rows'],
            $mapping,
            (string) $payload['signed_amount'],
        );

        return $this->respond(
            $result,
            (string) $validated['upload_key'],
            $payload['filename'],
            count($payload['rows']),
            $mapping,
            $payload['headers'],
        );
    }

    /**
     * @param  array{valid: array<int, mixed>, invalid: array<int, mixed>}  $result
     * @param  array<string, mixed>  $mapping
     */
    private function respond(array $result, string $key, string $filename, int $totalRows, array $mapping, array $headers): JsonResponse
    {
        return response()->json([
            'upload_key' => $key,
            'filename' => $filename,
            'headers' => $headers,
            'total_rows' => $totalRows,
            'valid_count' => count($result['valid']),
            'invalid_count' => count($result['invalid']),
            'invalid' => array_slice($result['invalid'], 0, 50),
            'preview' => array_slice($result['valid'], 0, 10),
            'mapping' => $mapping,
        ]);
    }

    /**
     * Commit the previewed rows as one import batch.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'upload_key' => ['required', 'string', 'max:100'],
        ]);

        $payload = $this->readStaged((string) $validated['upload_key']);

        if ($payload === null) {
            return back()->withErrors([
                'file' => 'That upload has expired. Please choose the file again.',
            ]);
        }

        // Re-validated rather than reusing the preview's verdict, so the rows
        // written are exactly what the parser and mapper produce from the
        // staged bytes.
        $result = $this->importer->validate(
            $payload['rows'],
            $payload['mapping'],
            (string) $payload['signed_amount'],
        );

        if ($result['valid'] === []) {
            $this->discard((string) $validated['upload_key']);

            return back()->withErrors([
                'file' => 'No importable rows were found with that column mapping.',
            ]);
        }

        $batch = $this->importer->import(
            $request->user(),
            array_column($result['valid'], 'attributes'),
            $payload['filename'],
        );

        $skipped = count($result['invalid']);

        $this->discard((string) $validated['upload_key']);

        return redirect()
            ->route('transactions.index')
            ->with('status', $skipped === 0
                ? trans_choice(
                    'Imported :count transaction from :file.|-Imported :count transactions from :file.',
                    $batch->imported_count,
                    ['count' => $batch->imported_count, 'file' => $batch->filename],
                )
                : trans_choice(
                    'Imported :count transaction from :file, skipping :skipped unusable rows.|-Imported :count transactions from :file, skipping :skipped unusable rows.',
                    $batch->imported_count,
                    [
                        'count' => $batch->imported_count,
                        'file' => $batch->filename,
                        'skipped' => $skipped,
                    ],
                ));
    }

    /**
     * Remove an import and every transaction it produced.
     *
     * This is the recovery path for a file imported twice or mapped wrongly.
     */
    public function destroy(Request $request, TransactionImport $import): RedirectResponse
    {
        abort_unless($import->user_id === $request->user()->id, 403);

        $count = $import->transactions()->count();

        // The foreign key nulls out rather than cascading, so the rows and the
        // batch record are removed together deliberately here.
        $import->transactions()->delete();
        $import->delete();

        return redirect()
            ->route('transaction-imports.index')
            ->with('status', trans_choice(
                'Removed :count imported transaction.|-Removed :count imported transactions.',
                $count,
                ['count' => $count],
            ));
    }

    /**
     * Persist the parsed upload and return its staging key.
     *
     * @param  array<string, mixed>  $payload
     */
    private function stage(string $filename, array $payload): string
    {
        $key = Str::uuid()->toString();

        $this->writeStaged($key, ['filename' => $filename, ...$payload]);

        return $key;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeStaged(string $key, array $payload): void
    {
        Storage::disk('local')->put(
            self::STAGING_DIRECTORY.'/'.$key.'.json',
            json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readStaged(string $key): ?array
    {
        $raw = Storage::disk('local')->get(self::STAGING_DIRECTORY.'/'.$key.'.json');

        if ($raw === null || $raw === '') {
            return null;
        }

        try {
            return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
    }

    private function discard(string $key): void
    {
        Storage::disk('local')->delete(self::STAGING_DIRECTORY.'/'.$key.'.json');
    }
}
