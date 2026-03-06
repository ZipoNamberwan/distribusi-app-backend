<?php

namespace App\Jobs;

use App\Models\Input;
use App\Models\SyncStatus;
use App\Models\Tabulation;
use App\Services\GoogleSheetService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;
use SplFileObject;
use Throwable;

class SyncDataJob implements ShouldQueue
{
    use Queueable;

    protected SyncStatus $status;

    /**
     * Create a new job instance.
     */
    public function __construct(SyncStatus $status)
    {
        $this->status = $status;
        // update status to loading immediately to reflect in UI
        $this->status->update([
            'status' => 'loading',
            'message' => 'Sync process started',
        ]);
    }

    /**
     * Execute the job.
     */
    public function handle(GoogleSheetService $sheet): void
    {
        $syncStatus = $this->status->fresh() ?? $this->status;

        try {
            if ($syncStatus->filename === null || trim((string) $syncStatus->filename) === '') {
                throw new RuntimeException('Missing filename for sync status');
            }

            $relativePath = 'uploads/'.ltrim((string) $syncStatus->filename, '/');

            $extension = Str::lower((string) Str::of($syncStatus->filename)->afterLast('.'));
            if (! in_array($extension, ['csv', 'xlsx'], true)) {
                throw new RuntimeException('Uploaded file must be a .csv or .xlsx');
            }

            $imported = match ($extension) {
                'csv' => $this->importInputCsvFromStorage($relativePath),
                'xlsx' => $this->importInputXlsxFromStorage($relativePath),
            };

            $syncStatus->update([
                'status' => 'success',
                'message' => "Imported {$imported} rows",
            ]);
        } catch (Throwable $e) {
            $syncStatus->update([
                'status' => 'failed',
                'message' => substr($e->getMessage(), 0, 1000),
            ]);

            throw $e;
        }
    }

    private function importInputCsvFromStorage(string $relativePath): int
    {
        if (! Storage::disk('local')->exists($relativePath)) {
            throw new RuntimeException("CSV file not found: {$relativePath}");
        }

        $absolutePath = Storage::disk('local')->path($relativePath);
        $delimiter = $this->detectCsvDelimiter($absolutePath);

        $file = new SplFileObject($absolutePath);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
        $file->setCsvControl($delimiter);

        $rawHeaders = $file->fgetcsv();
        if (! is_array($rawHeaders) || $rawHeaders === [null] || $rawHeaders === []) {
            throw new RuntimeException('CSV header row is missing');
        }

        $headers = array_map([$this, 'normalizeHeader'], $rawHeaders);

        $allowedColumns = array_values(array_diff(
            Schema::getColumnListing('input'),
            ['id', 'created_at', 'updated_at']
        ));
        $allowedLookup = array_fill_keys($allowedColumns, true);

        $columnMap = [];
        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }

            if (isset($allowedLookup[$header])) {
                $columnMap[$index] = $header;

                continue;
            }

            $snake = Str::snake($header);
            if (isset($allowedLookup[$snake])) {
                $columnMap[$index] = $snake;
            }
        }

        if ($columnMap === []) {
            throw new RuntimeException('No matching columns found between CSV headers and input table');
        }

        $nullableIntegers = [
            'tahun',
            'bulan',
            'status_kunjungan',
            'jenis_akomodasi',
            'kelas_akomodasi',
        ];
        $defaultZeroIntegers = [
            'room',
            'bed',
            'room_yesterday',
            'room_in',
            'room_out',
            'wna_yesterday',
            'wni_yesterday',
            'wna_in',
            'wni_in',
            'wna_out',
            'wni_out',
            'room_per_day',
            'bed_per_day',
            'day',
        ];

        Input::query()->truncate();

        $insertBatchSize = 500;
        $buffer = [];
        $imported = 0;

        foreach ($file as $row) {
            if (! is_array($row) || $row === [null]) {
                continue;
            }

            $record = [
                'id' => (string) Str::uuid(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $hasAnyValue = false;

            foreach ($columnMap as $index => $column) {
                $value = $row[$index] ?? null;
                if ($value === null) {
                    continue;
                }

                $value = trim((string) $value);
                if ($value === '') {
                    continue;
                }

                $hasAnyValue = true;

                if ($column === 'tanggal_update') {
                    $record[$column] = $this->parseDate($value);

                    continue;
                }

                if (in_array($column, $nullableIntegers, true)) {
                    $record[$column] = is_numeric($value) ? (int) $value : null;

                    continue;
                }

                if (in_array($column, $defaultZeroIntegers, true)) {
                    $record[$column] = is_numeric($value) ? (int) $value : 0;

                    continue;
                }

                $record[$column] = $value;
            }

            if (! $hasAnyValue) {
                continue;
            }

            $buffer[] = $record;

            if (count($buffer) >= $insertBatchSize) {
                Input::insert($buffer);
                $imported += count($buffer);
                $buffer = [];
            }
        }

        if ($buffer !== []) {
            Input::insert($buffer);
            $imported += count($buffer);
        }

        return $imported;
    }

    private function importInputXlsxFromStorage(string $relativePath): int
    {
        if (! Storage::disk('local')->exists($relativePath)) {
            throw new RuntimeException("XLSX file not found: {$relativePath}");
        }

        $absolutePath = Storage::disk('local')->path($relativePath);
        $reader = new Xlsx;
        $reader->setReadDataOnly(true);

        $info = $reader->listWorksheetInfo($absolutePath);
        $first = $info[0] ?? null;
        if (! is_array($first)) {
            throw new RuntimeException('Unable to read XLSX metadata');
        }

        $totalRows = (int) ($first['totalRows'] ?? 0);
        $totalColumns = (int) ($first['totalColumns'] ?? 0);

        if ($totalRows < 1 || $totalColumns < 1) {
            throw new RuntimeException('XLSX appears to be empty');
        }

        $headersRow = $this->readXlsxRows($reader, $absolutePath, 1, 1);
        $rawHeaders = $headersRow[0] ?? [];
        if (! is_array($rawHeaders) || $rawHeaders === []) {
            throw new RuntimeException('XLSX header row is missing');
        }

        $headers = array_map([$this, 'normalizeHeader'], array_map(fn ($v) => $this->stringifyCellValue($v), $rawHeaders));

        $allowedColumns = array_values(array_diff(
            Schema::getColumnListing('input'),
            ['id', 'created_at', 'updated_at']
        ));
        $allowedLookup = array_fill_keys($allowedColumns, true);

        $columnMap = [];
        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }

            if (isset($allowedLookup[$header])) {
                $columnMap[$index] = $header;

                continue;
            }

            $snake = Str::snake($header);
            if (isset($allowedLookup[$snake])) {
                $columnMap[$index] = $snake;
            }
        }

        if ($columnMap === []) {
            throw new RuntimeException('No matching columns found between XLSX headers and input table');
        }

        $nullableIntegers = [
            'tahun',
            'bulan',
            'status_kunjungan',
            'jenis_akomodasi',
            'kelas_akomodasi',
        ];
        $defaultZeroIntegers = [
            'room',
            'bed',
            'room_yesterday',
            'room_in',
            'room_out',
            'wna_yesterday',
            'wni_yesterday',
            'wna_in',
            'wni_in',
            'wna_out',
            'wni_out',
            'room_per_day',
            'bed_per_day',
            'day',
        ];

        Input::query()->truncate();

        $insertBatchSize = 500;
        $buffer = [];
        $imported = 0;

        $chunkSize = 1000;
        $start = 2;

        while ($start <= $totalRows) {
            $end = min($totalRows, $start + $chunkSize - 1);
            $rows = $this->readXlsxRows($reader, $absolutePath, $start, $end);

            foreach ($rows as $row) {
                if (! is_array($row) || $row === []) {
                    continue;
                }

                $record = [
                    'id' => (string) Str::uuid(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $hasAnyValue = false;

                foreach ($columnMap as $index => $column) {
                    $value = $row[$index] ?? null;
                    if ($value === null) {
                        continue;
                    }

                    if ($column === 'tanggal_update') {
                        $record[$column] = $this->parseExcelDate($value);
                        if ($record[$column] !== null) {
                            $hasAnyValue = true;
                        }

                        continue;
                    }

                    $string = trim($this->stringifyCellValue($value));
                    if ($string === '') {
                        continue;
                    }

                    $hasAnyValue = true;

                    if (in_array($column, $nullableIntegers, true)) {
                        $record[$column] = is_numeric($string) ? (int) $string : null;

                        continue;
                    }

                    if (in_array($column, $defaultZeroIntegers, true)) {
                        $record[$column] = is_numeric($string) ? (int) $string : 0;

                        continue;
                    }

                    $record[$column] = $string;
                }

                if (! $hasAnyValue) {
                    continue;
                }

                $buffer[] = $record;

                if (count($buffer) >= $insertBatchSize) {
                    Input::insert($buffer);
                    $imported += count($buffer);
                    $buffer = [];
                }
            }

            $start = $end + 1;
        }

        if ($buffer !== []) {
            Input::insert($buffer);
            $imported += count($buffer);
        }

        return $imported;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function readXlsxRows(Xlsx $reader, string $absolutePath, int $startRow, int $endRow): array
    {
        $filter = new class($startRow, $endRow) implements IReadFilter
        {
            public function __construct(private readonly int $startRow, private readonly int $endRow) {}

            public function readCell($columnAddress, $row, $worksheetName = ''): bool
            {
                return $row >= $this->startRow && $row <= $this->endRow;
            }
        };

        $reader->setReadFilter($filter);
        $spreadsheet = $reader->load($absolutePath);
        $sheet = $spreadsheet->getActiveSheet();
        $highestColumn = $sheet->getHighestColumn();
        $highestRow = $sheet->getHighestRow();

        $clampedEndRow = min($endRow, $highestRow);
        $range = 'A'.$startRow.':'.$highestColumn.$clampedEndRow;
        $rows = $sheet->rangeToArray($range, null, true, false);

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return is_array($rows) ? $rows : [];
    }

    private function parseExcelDate(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (Throwable) {
                return null;
            }
        }

        $string = trim($this->stringifyCellValue($value));

        return $this->parseDate($string);
    }

    private function stringifyCellValue(mixed $value): string
    {
        if ($value instanceof RichText) {
            return $value->getPlainText();
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    private function detectCsvDelimiter(string $absolutePath): string
    {
        $handle = fopen($absolutePath, 'r');
        if ($handle === false) {
            return ',';
        }

        $line = fgets($handle);
        fclose($handle);

        if ($line === false) {
            return ',';
        }

        $candidates = [',', ';', "\t", '|'];
        $best = ',';
        $bestCount = 0;

        foreach ($candidates as $delimiter) {
            $count = substr_count($line, $delimiter);
            if ($count > $bestCount) {
                $bestCount = $count;
                $best = $delimiter;
            }
        }

        return $best;
    }

    private function normalizeHeader(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
        $value = trim($value);

        return Str::snake(Str::lower($value));
    }

    /**
     * Sync data from INPUT sheet
     */
    private function syncInputSheet(GoogleSheetService $sheet, string $spreadsheetId): void
    {
        $batchSize = 5000;
        $startRow = 1;
        $headers = null;
        $isFirstBatch = true;

        // Clear existing data once at the start
        Input::query()->truncate();

        while (true) {
            // Calculate range for this batch
            $endRow = $startRow + $batchSize;
            $range = "INPUT!A{$startRow}:AE{$endRow}";

            // Fetch batch from Google Sheets
            $batchData = $sheet->read($spreadsheetId, $range);

            if (empty($batchData)) {
                break; // No more data
            }

            // Extract headers from first batch
            if ($isFirstBatch) {
                $headers = $this->headersFromSheetValues($batchData);
                $rows = $this->rowsFromSheetValues($batchData, $headers);
                $isFirstBatch = false;
            } else {
                // For subsequent batches, manually create rows without header row
                $rows = [];
                foreach ($batchData as $rowIndex => $rowValues) {
                    if (! is_array($rowValues)) {
                        continue;
                    }

                    $row = ['__row' => (string) ($startRow + $rowIndex)];
                    foreach ($headers as $headerIndex => $header) {
                        $cell = $rowValues[$headerIndex] ?? null;
                        $row[$header] = $cell === null ? null : (string) $cell;
                    }

                    $rows[] = $row;
                }
            }

            // Store batch row count before freeing
            $batchRowCount = count($batchData);

            // Free batch data from memory
            unset($batchData);

            // Filter and process rows
            $rows = $this->filterCompleteRows($rows, $headers);

            // Process and insert in smaller chunks
            $insertBatchSize = 500;
            $mappedRows = [];

            foreach ($rows as $row) {
                $mappedRow = $this->mapInputRow($row, $headers);
                $mappedRow['id'] = (string) Str::uuid();
                $mappedRow['created_at'] = now();
                $mappedRow['updated_at'] = now();
                $mappedRows[] = $mappedRow;

                // Insert when batch is full
                if (count($mappedRows) >= $insertBatchSize) {
                    Input::insert($mappedRows);
                    $mappedRows = [];
                    gc_collect_cycles();
                }
            }

            // Insert remaining rows from this batch
            if (! empty($mappedRows)) {
                Input::insert($mappedRows);
                $mappedRows = [];
            }

            // Free memory
            unset($rows, $mappedRows);
            gc_collect_cycles();

            // Check if we got fewer rows than expected (last batch)
            if ($batchRowCount <= $batchSize) {
                break;
            }

            // Move to next batch
            $startRow = $endRow + 1;
        }
    }

    /**
     * Sync data from TABULASI sheet
     */
    private function syncTabulasiSheet(GoogleSheetService $sheet, string $spreadsheetId): void
    {
        $batchSize = 5000;
        $startRow = 1;
        $headers = null;
        $isFirstBatch = true;

        // Clear existing data once at the start
        Tabulation::query()->truncate();

        while (true) {
            // Calculate range for this batch
            $endRow = $startRow + $batchSize;
            $range = "TABULASI!A{$startRow}:AZ{$endRow}";

            // Fetch batch from Google Sheets
            $batchData = $sheet->read($spreadsheetId, $range);

            if (empty($batchData)) {
                break; // No more data
            }

            // Extract headers from first batch
            if ($isFirstBatch) {
                $headers = $this->headersFromSheetValues($batchData);
                $rows = $this->rowsFromSheetValues($batchData, $headers);
                $isFirstBatch = false;
            } else {
                // For subsequent batches, manually create rows without header row
                $rows = [];
                foreach ($batchData as $rowIndex => $rowValues) {
                    if (! is_array($rowValues)) {
                        continue;
                    }

                    $row = ['__row' => (string) ($startRow + $rowIndex)];
                    foreach ($headers as $headerIndex => $header) {
                        $cell = $rowValues[$headerIndex] ?? null;
                        $row[$header] = $cell === null ? null : (string) $cell;
                    }

                    $rows[] = $row;
                }
            }

            // Store batch row count before freeing
            $batchRowCount = count($batchData);

            // Free batch data from memory
            unset($batchData);

            // Filter and process rows
            $rows = $this->filterCompleteRows($rows, $headers);

            // Process and insert in smaller chunks
            $insertBatchSize = 500;
            $mappedRows = [];

            foreach ($rows as $row) {
                $mappedRow = $this->mapTabulationRow($row, $headers);
                $mappedRow['created_at'] = now();
                $mappedRow['updated_at'] = now();
                $mappedRows[] = $mappedRow;

                // Insert when batch is full
                if (count($mappedRows) >= $insertBatchSize) {
                    Tabulation::insert($mappedRows);
                    $mappedRows = [];
                    gc_collect_cycles();
                }
            }

            // Insert remaining rows from this batch
            if (! empty($mappedRows)) {
                Tabulation::insert($mappedRows);
                $mappedRows = [];
            }

            // Free memory
            unset($rows, $mappedRows);
            gc_collect_cycles();

            // Check if we got fewer rows than batch size (last batch)
            if ($batchRowCount < $batchSize) {
                break;
            }

            // Move to next batch
            $startRow = $endRow + 1;
        }
    }

    /**
     * Map sheet row to Input model attributes
     *
     * @param  array<string, string|null>  $row
     * @param  array<int, string>  $headers
     * @return array<string, mixed>
     */
    private function mapInputRow(array $row, array $headers): array
    {
        return [
            'tanggal_update' => $this->parseDate($this->getValue($row, ['tanggal_update', 'tanggal update'])),
            'tarikan_ke' => $this->getValue($row, ['tarikan_ke', 'tarikan ke']),
            'idunik' => $this->getValue($row, ['idunik', 'id_unik', 'id unik']),
            'tahun' => $this->getValue($row, ['tahun']) ?: null,
            'bulan' => $this->getValue($row, ['bulan']) ?: null,
            'kode_prov' => $this->getValue($row, ['kode_prov', 'kode prov']),
            'kode_kec' => $this->getValue($row, ['kode_kec', 'kode kec']),
            'kode_desa' => $this->getValue($row, ['kode_desa', 'kode_des', 'kode desa', 'kode des']),
            'status_kunjungan' => $this->getValue($row, ['status_kunjungan', 'status_kur', 'status kunjungan', 'status kur']) ?: null,
            'jenis_akomodasi' => $this->getValue($row, ['jenis_akomodasi', 'jenis_ako', 'jenis akomodasi', 'jenis ako']) ?: null,
            'kelas_akomodasi' => $this->getValue($row, ['kelas_akomodasi', 'kelas_ako', 'kelas akomodasi', 'kelas ako']) ?: null,
            'nama_komersial' => $this->getValue($row, ['nama_komersial', 'nama_kor', 'nama komersial', 'nama kor']),
            'alamat' => $this->getValue($row, ['alamat']),
            'room' => (int) ($this->getValue($row, ['room']) ?? 0),
            'bed' => (int) ($this->getValue($row, ['bed']) ?? 0),
            'room_yesterday' => (int) ($this->getValue($row, ['room_yesterday', 'room yesterday']) ?? 0),
            'room_in' => (int) ($this->getValue($row, ['room_in', 'room in']) ?? 0),
            'room_out' => (int) ($this->getValue($row, ['room_out', 'room out']) ?? 0),
            'day' => (int) ($this->getValue($row, ['day']) ?? 0),
            'wna_yesterday' => (int) ($this->getValue($row, ['wna_yesterday', 'wna yesterday']) ?? 0),
            'wni_yesterday' => (int) ($this->getValue($row, ['wni_yesterday', 'wni yesterday']) ?? 0),
            'wna_in' => (int) ($this->getValue($row, ['wna_in', 'wna in']) ?? 0),
            'wni_in' => (int) ($this->getValue($row, ['wni_in', 'wni in']) ?? 0),
            'wna_out' => (int) ($this->getValue($row, ['wna_out', 'wna out']) ?? 0),
            'wni_out' => (int) ($this->getValue($row, ['wni_out', 'wni out']) ?? 0),
            'status' => $this->getValue($row, ['status']),
            'room_per_day' => (int) ($this->getValue($row, ['room_per_day', 'room_per', 'room per day', 'room per']) ?? 0),
            'bed_per_day' => (int) ($this->getValue($row, ['bed_per_day', 'bed per day']) ?? 0),
        ];
    }

    /**
     * Map sheet row to Tabulation model attributes
     *
     * @param  array<string, string|null>  $row
     * @param  array<int, string>  $headers
     * @return array<string, mixed>
     */
    private function mapTabulationRow(array $row, array $headers): array
    {
        return [
            'tanggal_update' => $this->parseDate($this->getValue($row, ['tanggal_update', 'tanggal update'])),
            'tarikan_ke' => $this->getValue($row, ['tarikan_ke', 'tarikan ke']),
            'idunik' => $this->getValue($row, ['idunik', 'id_unik', 'id unik']),
            'tahun' => $this->getValue($row, ['tahun']) ?: null,
            'bulan' => $this->getValue($row, ['bulan']) ?: null,
            'kode_prov' => $this->getValue($row, ['kode_prov', 'kode prov']),
            'kode_kab' => $this->getValue($row, ['kode_kab', 'kode kab']),
            'kode_kec' => $this->getValue($row, ['kode_kec', 'kode kec']),
            'kode_des' => $this->getValue($row, ['kode_des', 'kode des']),
            'status_kur' => $this->getValue($row, ['status_kur', 'status kur']) ?: null,
            'jenis_ako' => $this->getValue($row, ['jenis_ako', 'jenis ako']) ?: null,
            'kelas_ako' => $this->getValue($row, ['kelas_ako', 'kelas ako']) ?: null,
            'nama_kor' => $this->getValue($row, ['nama_kor', 'nama kor']),
            'alamat' => $this->getValue($row, ['alamat']),
            'mkts' => (int) ($this->getValue($row, ['mkts']) ?? 0),
            'mktj' => (int) ($this->getValue($row, ['mktj']) ?? 0),
            'tpk' => (float) ($this->getValue($row, ['tpk']) ?? 0),
            'mta' => (int) ($this->getValue($row, ['mta']) ?? 0),
            'ta' => (int) ($this->getValue($row, ['ta']) ?? 0),
            'mtnus' => (int) ($this->getValue($row, ['mtnus']) ?? 0),
            'tnus' => (int) ($this->getValue($row, ['tnus']) ?? 0),
            'rlmta' => (float) ($this->getValue($row, ['rlmta']) ?? 0),
            'rlmtnus' => (float) ($this->getValue($row, ['rlmtnus']) ?? 0),
            'mtgab' => (int) ($this->getValue($row, ['mtgab']) ?? 0),
            'tgab' => (int) ($this->getValue($row, ['tgab']) ?? 0),
            'rlmtgab' => (float) ($this->getValue($row, ['rlmtgab']) ?? 0),
            'gpr' => (float) ($this->getValue($row, ['gpr']) ?? 0),
            'tptt' => (float) ($this->getValue($row, ['tptt']) ?? 0),
            'jumlah_hari' => $this->getValue($row, ['jumlah_hari', 'jumlah hari']) ?: null,
            'error_tpk' => (int) ($this->getValue($row, ['error_tpk', 'error tpk']) ?? 0),
            'error_rlmta' => (int) ($this->getValue($row, ['error_rlmta', 'error rlmta']) ?? 0),
            'error_rlmtnus' => (int) ($this->getValue($row, ['error_rlmtnus', 'error rlmtnus']) ?? 0),
            'error_gpr' => (int) ($this->getValue($row, ['error_gpr', 'error gpr']) ?? 0),
            'error_tptt' => (int) ($this->getValue($row, ['error_tptt', 'error tptt']) ?? 0),
            'error_hari' => (int) ($this->getValue($row, ['error_hari', 'error hari']) ?? 0),
            'jumlah_error' => (int) ($this->getValue($row, ['jumlah_error', 'jumlah error']) ?? 0),
            'status_konf' => $this->getValue($row, ['status_konf', 'status konf']),
        ];
    }

    /**
     * Parse date from various formats to MySQL format (YYYY-MM-DD)
     */
    private function parseDate(?string $dateString): ?string
    {
        if ($dateString === null || trim($dateString) === '') {
            return null;
        }

        $dateString = trim($dateString);

        try {
            // Try parsing DD/MM/YYYY format (with or without time)
            if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})/', $dateString, $matches)) {
                $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                $year = $matches[3];

                return "{$year}-{$month}-{$day}";
            }

            // Try parsing YYYY-MM-DD format (already correct)
            if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $dateString)) {
                $date = new \DateTime($dateString);

                return $date->format('Y-m-d');
            }

            // Try generic date parsing
            $date = new \DateTime($dateString);

            return $date->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get value from row by checking multiple possible header names
     *
     * @param  array<string, string|null>  $row
     * @param  array<int, string>  $possibleKeys
     */
    private function getValue(array $row, array $possibleKeys): ?string
    {
        foreach ($possibleKeys as $key) {
            // Try exact match first
            if (isset($row[$key])) {
                return $row[$key];
            }

            // Try case-insensitive match
            foreach (array_keys($row) as $rowKey) {
                if (Str::lower($rowKey) === Str::lower($key)) {
                    return $row[$rowKey];
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<int, mixed>>  $values
     * @return array<int, string>
     */
    private function headersFromSheetValues(array $values): array
    {
        $rawHeaders = $values[0] ?? [];
        if (! is_array($rawHeaders)) {
            return [];
        }

        $headers = [];
        foreach (array_values($rawHeaders) as $index => $value) {
            $label = trim((string) $value);
            $headers[] = $label !== '' ? $label : 'Column '.($index + 1);
        }

        return $headers;
    }

    /**
     * @param  array<int, array<int, mixed>>  $values
     * @param  array<int, string>  $headers
     * @return array<int, array<string, string|null>>
     */
    private function rowsFromSheetValues(array $values, array $headers): array
    {
        if ($headers === []) {
            return [];
        }

        $rows = [];
        foreach (array_slice($values, 1) as $rowIndex => $rowValues) {
            if (! is_array($rowValues)) {
                continue;
            }

            $row = ['__row' => (string) ($rowIndex + 1)];
            foreach ($headers as $headerIndex => $header) {
                $cell = $rowValues[$headerIndex] ?? null;
                $row[$header] = $cell === null ? null : (string) $cell;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Filter rows based on column C (3rd column)
     *
     * @param  array<int, array<string, string|null>>  $rows
     * @param  array<int, string>  $headers
     * @return array<int, array<string, string|null>>
     */
    private function filterCompleteRows(array $rows, array $headers): array
    {
        // Column C is at index 2 (A=0, B=1, C=2)
        $columnC = $headers[2] ?? null;

        if ($columnC === null) {
            // If column C doesn't exist, return all rows
            return $rows;
        }

        return array_values(array_filter($rows, function ($row) use ($columnC) {
            $value = $row[$columnC] ?? null;

            // Include row only if column C has a non-empty value
            return $value !== null && trim($value) !== '';
        }));
    }

    private function normalizeSpreadsheetId(string $spreadsheetId): string
    {
        $spreadsheetId = trim($spreadsheetId);
        if ($spreadsheetId === '') {
            return '';
        }

        if (Str::startsWith($spreadsheetId, ['http://', 'https://'])) {
            if (preg_match('~\/spreadsheets\/d\/([^\/\?]+)~', $spreadsheetId, $matches) === 1) {
                return $matches[1];
            }
        }

        $spreadsheetId = ltrim($spreadsheetId, '/');

        if (Str::startsWith($spreadsheetId, 'd/')) {
            $spreadsheetId = (string) Str::of($spreadsheetId)->after('d/');
        }

        if (str_contains($spreadsheetId, '/')) {
            $spreadsheetId = explode('/', $spreadsheetId, 2)[0];
        }

        return trim($spreadsheetId);
    }
}
