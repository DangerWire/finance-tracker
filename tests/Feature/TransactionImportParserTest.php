<?php

namespace Tests\Feature;

use App\Services\TransactionImportParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TransactionImportParserTest extends TestCase
{
    public function test_it_reads_headers_and_rows(): void
    {
        $parsed = $this->parse("Date,Amount,Note\n2026-10-01,25.00,Lunch\n2026-10-02,40.50,Coffee\n");

        $this->assertSame(['Date', 'Amount', 'Note'], $parsed['headers']);
        $this->assertSame([
            ['Date' => '2026-10-01', 'Amount' => '25.00', 'Note' => 'Lunch'],
            ['Date' => '2026-10-02', 'Amount' => '40.50', 'Note' => 'Coffee'],
        ], $parsed['rows']);
    }

    public function test_it_skips_blank_lines(): void
    {
        $parsed = $this->parse("Date,Amount\n2026-10-01,25.00\n\n2026-10-02,40.50\n");

        $this->assertCount(2, $parsed['rows']);
    }

    public function test_it_skips_a_preamble_above_the_header(): void
    {
        // Some exports open with account metadata before the real header.
        $csv = "Account: 1234\nStatement for October\n\nDate,Amount\n2026-10-01,25.00\n";

        $parsed = $this->parse($csv);

        $this->assertSame(['Date', 'Amount'], $parsed['headers']);
        $this->assertSame([['Date' => '2026-10-01', 'Amount' => '25.00']], $parsed['rows']);
    }

    public function test_it_detects_a_semicolon_delimiter(): void
    {
        $parsed = $this->parse("Date;Amount;Note\n2026-10-01;25.00;Lunch\n");

        $this->assertSame(['Date', 'Amount', 'Note'], $parsed['headers']);
        $this->assertSame([['Date' => '2026-10-01', 'Amount' => '25.00', 'Note' => 'Lunch']], $parsed['rows']);
    }

    public function test_it_gives_duplicate_and_empty_headers_distinct_names(): void
    {
        // Two columns both called "Amount" would otherwise collapse into one
        // key and make one of them impossible to select in the mapping step.
        $parsed = $this->parse("Date,Amount,Amount\n2026-10-01,25.00,60.00\n");

        $this->assertSame(['Date', 'Amount', 'Amount (2)'], $parsed['headers']);
    }

    public function test_it_pads_short_rows_so_columns_stay_aligned(): void
    {
        $parsed = $this->parse("Date,Amount,Note\n2026-10-01,25.00\n");

        $this->assertSame(['Date' => '2026-10-01', 'Amount' => '25.00', 'Note' => ''], $parsed['rows'][0]);
    }

    public function test_it_throws_on_an_empty_file(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->parse('');
    }

    public function test_it_parses_gb18030_chinese_export_with_long_preamble_and_utf8_bom(): void
    {
        $preambleLines = [];
        for ($i = 1; $i <= 16; $i++) {
            $preambleLines[] = "Account metadata line {$i}";
        }

        $csvUtf8 = implode("\r\n", [
            ...$preambleLines,
            '交易时间,收/支,金额,商品说明',
            '2026-10-01 08:30:00,支出,35.50,瑞幸咖啡',
            '2026-10-02 18:00:00,收入,5000.00,工资',
        ]);

        // Encode as GB18030 (matching Alipay/Chinese Windows bank exports)
        $gb18030 = mb_convert_encoding($csvUtf8, 'GB18030', 'UTF-8');

        $parsed = $this->parse($gb18030);

        $this->assertSame(['交易时间', '收/支', '金额', '商品说明'], $parsed['headers']);
        $this->assertCount(2, $parsed['rows']);
        $this->assertSame('瑞幸咖啡', $parsed['rows'][0]['商品说明']);
        $this->assertSame('收入', $parsed['rows'][1]['收/支']);

        // Also ensure UTF-8 BOM is cleanly stripped from first header
        $bomParsed = $this->parse("\xEF\xBB\xBFDate,Amount\n2026-10-01,25.00\n");
        $this->assertSame(['Date', 'Amount'], $bomParsed['headers']);
    }

    private function parse(string $contents): array
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->createWithContent('statement.csv', $contents);

        return app(TransactionImportParser::class)->parse($file);
    }
}
