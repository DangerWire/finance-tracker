<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Reads a CSV export into headers and rows.
 *
 * Deliberately knows nothing about any particular bank's layout. The columns
 * an export happens to use are decided later by the user through the mapping
 * step, which is what allows one importer to handle Alipay, WeChat Pay, a
 * bank statement, or a hand-made spreadsheet without special-casing any of
 * them.
 */
class TransactionImportParser
{
    /**
     * Delimiters considered when detecting a file's layout.
     *
     * @var array<int, string>
     */
    private const CANDIDATE_DELIMITERS = [',', ';', "\t", '|'];

    /**
     * Lines to skip looking for the header row, so a preamble of account
     * metadata does not get mistaken for the header.
     */
    private const MAX_PREAMBLE_LINES = 10;

    /**
     * Refuse to read a file larger than this, so a mis-selected huge export
     * cannot exhaust memory during parsing.
     */
    private const MAX_ROWS = 20000;

    /**
     * Read the uploaded file.
     *
     * Rows are returned keyed by their header label rather than by position,
     * because the mapping step addresses columns by name and a positional row
     * would have to be re-indexed against the header all over again.
     *
     * @return array{headers: array<int, string>, rows: array<int, array<string, string>>}
     *
     * @throws RuntimeException when the file cannot be read as delimited text
     */
    public function parse(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'rb');

        if ($handle === false) {
            throw new RuntimeException('The uploaded file could not be read.');
        }

        try {
            $this->rewindToHeader($handle);

            $delimiter = $this->detectDelimiter($handle);
            $headers = $this->label($this->readRow($handle, $delimiter));

            if ($headers === []) {
                throw new RuntimeException('The file appears to be empty.');
            }

            return [
                'headers' => $headers,
                'rows' => $this->readRows($handle, $delimiter, $headers),
            ];
        } finally {
            fclose($handle);
        }
    }

    /**
     * Skip any preamble lines that sit above the real header.
     *
     * @param  resource  $handle
     */
    private function rewindToHeader($handle): void
    {
        for ($skipped = 0; $skipped < self::MAX_PREAMBLE_LINES; $skipped++) {
            $position = ftell($handle);
            $line = fgets($handle);

            if ($line === false) {
                return;
            }

            // A header carries at least one separator and does not open with a
            // currency symbol, which is what separates it from prose such as
            // "¥1,200.00 paid at merchant".
            if ($this->separatorCount($line) >= 1 && ! preg_match('/^[\p{Sc}]/u', trim($line))) {
                fseek($handle, (int) $position);

                return;
            }
        }
    }

    /**
     * Choose the delimiter that splits the header into the most fields.
     *
     * @param  resource  $handle
     */
    private function detectDelimiter($handle): string
    {
        $position = ftell($handle);
        $sample = (string) fgets($handle);
        fseek($handle, (int) $position);

        $best = ',';
        $bestCount = 0;

        foreach (self::CANDIDATE_DELIMITERS as $delimiter) {
            $count = substr_count($sample, $delimiter);

            if ($count > $bestCount) {
                $best = $delimiter;
                $bestCount = $count;
            }
        }

        return $best;
    }

    private function separatorCount(string $line): int
    {
        $count = 0;

        foreach (self::CANDIDATE_DELIMITERS as $delimiter) {
            $count += substr_count($line, $delimiter);
        }

        return $count;
    }

    /**
     * @param  resource  $handle
     * @return array<int, string>
     */
    private function readRow($handle, string $delimiter): array
    {
        $line = fgetcsv($handle, 0, $delimiter);

        return $line === false ? [] : array_map(fn ($value): string => trim((string) $value), $line);
    }

    /**
     * @param  resource  $handle
     * @param  array<int, string>  $headers
     * @return array<int, array<string, string>>
     */
    private function readRows($handle, string $delimiter, array $headers): array
    {
        $rows = [];
        $columnCount = count($headers);

        while (count($rows) < self::MAX_ROWS) {
            $row = $this->readRow($handle, $delimiter);

            // An empty result is the end of the file. A blank line is just
            // spacing inside the export and is stepped over.
            if ($row === []) {
                break;
            }

            if ($this->isBlank($row)) {
                continue;
            }

            // A short row is padded and a long one trimmed so every row lines
            // up with the header, then keyed by header name.
            $row = array_slice(array_pad($row, $columnCount, ''), 0, $columnCount);

            $rows[] = array_combine($headers, $row);
        }

        return $rows;
    }

    /**
     * @param  array<int, string>  $row
     */
    private function isBlank(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Give every column a stable, unique name, including duplicates and
     * unnamed ones, so the mapping step can address any column unambiguously.
     *
     * @param  array<int, string>  $headers
     * @return array<int, string>
     */
    private function label(array $headers): array
    {
        $seen = [];

        foreach ($headers as $index => $header) {
            $header = $header !== '' ? $header : 'column '.($index + 1);
            $seen[$header] = ($seen[$header] ?? 0) + 1;

            $headers[$index] = $seen[$header] > 1
                ? $header.' ('.$seen[$header].')'
                : $header;
        }

        return $headers;
    }
}
