<?php

namespace App\Jobs;

use App\Exceptions\ImportException;
use App\Models\Category;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Models\Input;
use App\Models\Regency;
use App\Models\SyncStatus;
use App\Models\Tabulation;
use App\Services\GoogleSheetService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
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
            'system_message' => 'Sync process started',
            'user_message' => 'Sync process started',
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
                throw new ImportException('Missing filename for sync status', 'File configuration missing');
            }

            $defaultUserId = (string) ($syncStatus->user_id ?? '');
            if (trim($defaultUserId) === '') {
                throw new ImportException('Missing user_id for sync status', 'User configuration missing');
            }

            $relativePath = 'uploads/'.ltrim((string) $syncStatus->filename, '/');

            $extension = Str::lower((string) Str::of($syncStatus->filename)->afterLast('.'));
            if (! in_array($extension, ['csv', 'xlsx'], true)) {
                throw new ImportException('Uploaded file must be a .csv or .xlsx', 'Uploaded file must be .csv or .xlsx');
            }

            $imported = match ($extension) {
                'csv' => $this->importInputCsvFromStorage($relativePath, $syncStatus),
                'xlsx' => $this->importInputXlsxFromStorage($relativePath, $syncStatus),
            };

            Input::where('bulan', $syncStatus->month_id)
                ->where('tahun', $syncStatus->year_id)
                ->where('sync_status_id', '!=', (string) $syncStatus->id)
                ->delete();

            $this->calculateIndicatorValues($syncStatus);

            $syncStatus->update([
                'status' => 'success',
                'system_message' => "Imported {$imported} rows",
                'user_message' => "Imported {$imported} rows",
            ]);
        } catch (ImportException $e) {
            $syncStatus->update([
                'status' => 'failed',
                'system_message' => substr($e->getMessage(), 0, 1000),
                'user_message' => $e->getUserMessage(),
            ]);

            throw $e;
        } catch (Throwable $e) {
            $syncStatus->update([
                'status' => 'failed',
                'system_message' => substr($e->getMessage(), 0, 1000),
                'user_message' => 'An unexpected error occurred',
            ]);

            throw $e;
        }
    }

    private function importInputCsvFromStorage(string $relativePath, SyncStatus $syncStatus): int
    {
        if (! Storage::disk('local')->exists($relativePath)) {
            throw new ImportException("CSV file not found: {$relativePath}", 'Uploaded file could not be found');
        }

        $absolutePath = Storage::disk('local')->path($relativePath);
        $delimiter = $this->detectCsvDelimiter($absolutePath);

        $file = new SplFileObject($absolutePath);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
        $file->setCsvControl($delimiter);

        $rawHeaders = $file->fgetcsv();
        if (! is_array($rawHeaders) || $rawHeaders === [null] || $rawHeaders === []) {
            throw new ImportException('CSV header row is missing', 'File has no header row');
        }

        $headers = array_map([$this, 'normalizeHeader'], $rawHeaders);

        $computedColumns = [
            'mkts', 'mktj', 'tpk', 'mta', 'ta', 'mtnus', 'tnus',
            'rlmta', 'rlmtnus', 'mtgab', 'tgab', 'rlmtgab', 'gpr', 'tptt',
            'jumlah_hari', 'error_tpk', 'error_rlmta', 'error_rlmtnus',
            'error_gpr', 'error_tptt', 'error_hari', 'jumlah_error',
        ];

        $allowedColumns = array_values(array_diff(
            Schema::getColumnListing('input'),
            array_merge(['id', 'created_at', 'updated_at', 'tahun', 'bulan', 'sync_status_id'], $computedColumns)
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
            throw new ImportException('No matching columns found between CSV headers and input table', 'File headers do not match the expected format');
        }

        $maps = $this->buildInputForeignKeyMaps();
        $indexes = $this->requiredInputForeignKeyIndexes($columnMap);

        $this->validateInputForeignKeysInCsv($file, $columnMap, $indexes, $maps);

        $nullableIntegers = [
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

        // Build a canonical template so every record has identical keys for batch insert.
        $emptyRecord = array_fill_keys(array_values($columnMap), null);
        foreach ($defaultZeroIntegers as $col) {
            if (array_key_exists($col, $emptyRecord)) {
                $emptyRecord[$col] = 0;
            }
        }
        // Ensure tahun, bulan, sync_status_id are always present regardless of file headers.
        $emptyRecord['tahun'] = null;
        $emptyRecord['bulan'] = null;
        $emptyRecord['sync_status_id'] = null;

        // Rewind to start inserting (skip header row)
        $file->rewind();
        $file->fgetcsv();

        $insertBatchSize = 500;
        $buffer = [];
        $imported = 0;

        $fkCodeColumns = ['kode_kab' => true];

        $rowNumber = 1;

        foreach ($file as $row) {
            $rowNumber++;

            if (! is_array($row) || $row === [null]) {
                continue;
            }

            $record = array_merge($emptyRecord, [
                'id' => (string) Str::uuid(),
                'created_at' => now(),
                'updated_at' => now(),
                'tahun' => $syncStatus->year_id,
                'bulan' => $syncStatus->month_id,
                'sync_status_id' => (string) $syncStatus->id,
            ]);

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

                if (isset($fkCodeColumns[$column])) {
                    $record[$column] = $value;

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

            if (! isset($record['user_id']) || trim((string) $record['user_id']) === '') {
                $record['user_id'] = (string) $syncStatus->user_id;
            }

            $record = $this->resolveInputForeignKeysOrThrow($record, $maps, 'CSV row '.$rowNumber);

            try {
                $record = $this->calculateTabulation($record);
            } catch (Throwable $calcEx) {
                throw new ImportException(
                    'Error calculating tabulation at CSV row '.$rowNumber.': '.$calcEx->getMessage(),
                    'Error calculating tabulation'
                );
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

    private function importInputXlsxFromStorage(string $relativePath, SyncStatus $syncStatus): int
    {
        if (! Storage::disk('local')->exists($relativePath)) {
            throw new ImportException("XLSX file not found: {$relativePath}", 'Uploaded file could not be found');
        }

        $absolutePath = Storage::disk('local')->path($relativePath);
        $reader = new Xlsx;
        $reader->setReadDataOnly(true);

        $info = $reader->listWorksheetInfo($absolutePath);
        $first = $info[0] ?? null;
        if (! is_array($first)) {
            throw new ImportException('Unable to read XLSX metadata', 'Could not read the uploaded file');
        }

        $totalRows = (int) ($first['totalRows'] ?? 0);
        $totalColumns = (int) ($first['totalColumns'] ?? 0);

        if ($totalRows < 1 || $totalColumns < 1) {
            throw new ImportException('XLSX appears to be empty', 'The uploaded file is empty');
        }

        $headersRow = $this->readXlsxRows($reader, $absolutePath, 1, 1);
        $rawHeaders = $headersRow[0] ?? [];
        if (! is_array($rawHeaders) || $rawHeaders === []) {
            throw new ImportException('XLSX header row is missing', 'File has no header row');
        }

        $headers = array_map([$this, 'normalizeHeader'], array_map(fn ($v) => $this->stringifyCellValue($v), $rawHeaders));

        $computedColumns = [
            'mkts', 'mktj', 'tpk', 'mta', 'ta', 'mtnus', 'tnus',
            'rlmta', 'rlmtnus', 'mtgab', 'tgab', 'rlmtgab', 'gpr', 'tptt',
            'jumlah_hari', 'error_tpk', 'error_rlmta', 'error_rlmtnus',
            'error_gpr', 'error_tptt', 'error_hari', 'jumlah_error',
        ];

        $allowedColumns = array_values(array_diff(
            Schema::getColumnListing('input'),
            array_merge(['id', 'created_at', 'updated_at', 'tahun', 'bulan', 'sync_status_id'], $computedColumns)
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
            throw new ImportException('No matching columns found between XLSX headers and input table', 'File headers do not match the expected format');
        }

        $maps = $this->buildInputForeignKeyMaps();
        $indexes = $this->requiredInputForeignKeyIndexes($columnMap);

        $this->validateInputForeignKeysInXlsx($reader, $absolutePath, $totalRows, $columnMap, $indexes, $maps);

        $nullableIntegers = [
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

        // Build a canonical template so every record has identical keys for batch insert.
        $emptyRecord = array_fill_keys(array_values($columnMap), null);
        foreach ($defaultZeroIntegers as $col) {
            if (array_key_exists($col, $emptyRecord)) {
                $emptyRecord[$col] = 0;
            }
        }
        // Ensure tahun, bulan, sync_status_id are always present regardless of file headers.
        $emptyRecord['tahun'] = null;
        $emptyRecord['bulan'] = null;
        $emptyRecord['sync_status_id'] = null;

        $insertBatchSize = 500;
        $buffer = [];
        $imported = 0;

        $fkCodeColumns = ['kode_kab' => true];

        $chunkSize = 1000;
        $start = 2;

        while ($start <= $totalRows) {
            $end = min($totalRows, $start + $chunkSize - 1);
            $rows = $this->readXlsxRows($reader, $absolutePath, $start, $end);

            foreach ($rows as $offset => $row) {
                if (! is_array($row) || $row === []) {
                    continue;
                }

                $rowNumber = $start + $offset;

                $record = array_merge($emptyRecord, [
                    'id' => (string) Str::uuid(),
                    'created_at' => now(),
                    'updated_at' => now(),
                    'tahun' => $syncStatus->year_id,
                    'bulan' => $syncStatus->month_id,
                    'sync_status_id' => (string) $syncStatus->id,
                ]);

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

                    if (isset($fkCodeColumns[$column])) {
                        $record[$column] = $string;

                        continue;
                    }

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

                if (! isset($record['user_id']) || trim((string) $record['user_id']) === '') {
                    $record['user_id'] = (string) $syncStatus->user_id;
                }

                $record = $this->resolveInputForeignKeysOrThrow($record, $maps, 'XLSX row '.$rowNumber);

                try {
                    $record = $this->calculateTabulation($record);
                } catch (Throwable $calcEx) {
                    throw new ImportException(
                        'Error calculating tabulation at XLSX row '.$rowNumber.': '.$calcEx->getMessage(),
                        'Error calculating tabulation'
                    );
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
     * @return array{regencies: array<string, int>}
     */
    private function buildInputForeignKeyMaps(): array
    {
        /** @var array<string, int> $regencies */
        $regencies = Regency::query()->pluck('id', 'short_code')->all();

        return [
            'regencies' => $regencies,
        ];
    }

    /**
     * @param  array<int, string>  $columnMap
     * @return array{kode_kab: int}
     */
    private function requiredInputForeignKeyIndexes(array $columnMap): array
    {
        $indexesByColumn = array_flip($columnMap);

        if (! array_key_exists('kode_kab', $indexesByColumn)) {
            throw new ImportException("Missing required column 'kode_kab' in file headers", "File is missing the required 'kode_kab' column");
        }

        return [
            'kode_kab' => (int) $indexesByColumn['kode_kab'],
        ];
    }

    /**
     * @param  array<int, string>  $columnMap
     * @param  array{kode_kab: int}  $indexes
     * @param  array{regencies: array<string, int>}  $maps
     */
    private function validateInputForeignKeysInCsv(SplFileObject $file, array $columnMap, array $indexes, array $maps): void
    {
        $missingRegencies = [];

        $file->rewind();
        $file->fgetcsv();

        $rowNumber = 1;
        foreach ($file as $row) {
            $rowNumber++;

            if (! is_array($row) || $row === [null]) {
                continue;
            }

            $hasAnyValue = false;
            foreach ($columnMap as $index => $column) {
                $value = $row[$index] ?? null;
                if ($value === null) {
                    continue;
                }

                $string = trim((string) $value);
                if ($string === '') {
                    continue;
                }

                $hasAnyValue = true;
                break;
            }

            if (! $hasAnyValue) {
                continue;
            }

            $kodeKab = trim((string) ($row[$indexes['kode_kab']] ?? ''));

            if ($kodeKab === '') {
                throw new ImportException('Missing kode_kab at CSV row '.$rowNumber, 'Error at row '.$rowNumber);
            }

            if ($this->resolveRegencyId($kodeKab, $maps['regencies']) === null) {
                $missingRegencies[$kodeKab] = true;
            }
        }

        $this->throwIfMissingForeignKeys($missingRegencies);
    }

    /**
     * @param  array<int, string>  $columnMap
     * @param  array{kode_kab: int}  $indexes
     * @param  array{regencies: array<string, int>}  $maps
     */
    private function validateInputForeignKeysInXlsx(Xlsx $reader, string $absolutePath, int $totalRows, array $columnMap, array $indexes, array $maps): void
    {
        $missingRegencies = [];

        $chunkSize = 1000;
        $start = 2;

        while ($start <= $totalRows) {
            $end = min($totalRows, $start + $chunkSize - 1);
            $rows = $this->readXlsxRows($reader, $absolutePath, $start, $end);

            foreach ($rows as $offset => $row) {
                if (! is_array($row) || $row === []) {
                    continue;
                }

                $rowNumber = $start + $offset;

                $hasAnyValue = false;
                foreach ($columnMap as $index => $column) {
                    $value = $row[$index] ?? null;
                    if ($value === null) {
                        continue;
                    }

                    if ($column === 'tanggal_update') {
                        $parsed = $this->parseExcelDate($value);
                        if ($parsed !== null) {
                            $hasAnyValue = true;
                            break;
                        }

                        continue;
                    }

                    $string = trim($this->stringifyCellValue($value));
                    if ($string === '') {
                        continue;
                    }

                    $hasAnyValue = true;
                    break;
                }

                if (! $hasAnyValue) {
                    continue;
                }

                $kodeKab = trim($this->stringifyCellValue($row[$indexes['kode_kab']] ?? ''));

                if ($kodeKab === '') {
                    throw new ImportException('Missing kode_kab at XLSX row '.$rowNumber, 'Error at row '.$rowNumber);
                }

                if ($this->resolveRegencyId($kodeKab, $maps['regencies']) === null) {
                    $missingRegencies[$kodeKab] = true;
                }
            }

            $start = $end + 1;
        }

        $this->throwIfMissingForeignKeys($missingRegencies);
    }

    /**
     * @param  array<string, bool>  $missingRegencies
     */
    private function throwIfMissingForeignKeys(array $missingRegencies): void
    {
        if ($missingRegencies === []) {
            return;
        }

        $parts = [];

        if ($missingRegencies !== []) {
            $codes = array_slice(array_keys($missingRegencies), 0, 25);
            $parts[] = 'Unknown kode_kab short_codes: '.implode(', ', $codes);
        }

        throw new ImportException(implode(' | ', $parts), 'File contains references not found in the database');
    }

    /**
     * @param  array{regencies: array<string, int>}  $maps
     */
    private function resolveInputForeignKeysOrThrow(array $record, array $maps, string $rowRef): array
    {
        $kodeKabCode = trim((string) ($record['kode_kab'] ?? ''));

        preg_match('/\d+$/', $rowRef, $rowMatch);
        $rowUserMessage = isset($rowMatch[0]) ? 'Error at row '.$rowMatch[0] : 'Error during import';

        if ($kodeKabCode === '') {
            throw new ImportException("Missing kode_kab at {$rowRef}", $rowUserMessage);
        }

        $kodeKabId = $this->resolveRegencyId($kodeKabCode, $maps['regencies']);
        if ($kodeKabId === null) {
            throw new ImportException("Unknown kode_kab short_code '{$kodeKabCode}' at {$rowRef}", $rowUserMessage);
        }

        $record['kode_kab'] = $kodeKabId;

        return $record;
    }

    /**
     * @param  array<string, int>  $map
     */
    private function resolveRegencyId(string $shortCode, array $map): ?int
    {
        $shortCode = trim($shortCode);
        if ($shortCode === '') {
            return null;
        }

        $tries = [$shortCode];
        if (is_numeric($shortCode)) {
            $tries[] = (string) ((int) $shortCode);
        }

        foreach (array_values(array_unique($tries)) as $try) {
            if (isset($map[$try])) {
                return (int) $map[$try];
            }
        }

        return null;
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
     * Calculate tabulation columns from raw input data.
     * Formulas translated from the Excel REKAPITULASI sheet.
     *
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function calculateTabulation(array $record): array
    {
        $room = isset($record['room']) ? (float) $record['room'] : null;
        $bed = isset($record['bed']) ? (float) $record['bed'] : null;
        $roomYesterday = (float) ($record['room_yesterday'] ?? 0);
        $roomIn = (float) ($record['room_in'] ?? 0);
        $roomOut = (float) ($record['room_out'] ?? 0);
        $wnaYesterday = (float) ($record['wna_yesterday'] ?? 0);
        $wniYesterday = (float) ($record['wni_yesterday'] ?? 0);
        $wnaIn = (float) ($record['wna_in'] ?? 0);
        $wniIn = (float) ($record['wni_in'] ?? 0);
        $wnaOut = (float) ($record['wna_out'] ?? 0);
        $wniOut = (float) ($record['wni_out'] ?? 0);
        $namaKomersialEmpty = trim((string) ($record['nama_komersial'] ?? '')) === '';
        $statusKunjunganEmpty = ($record['status_kunjungan'] ?? null) === null || (string) $record['status_kunjungan'] === '';
        $jenisAkomodasi = isset($record['jenis_akomodasi']) ? (int) $record['jenis_akomodasi'] : null;

        // MKTS = =if(INPUT!P="";"";INPUT!P) → room, 0 when blank
        $mkts = (int) ($room ?? 0);

        // MKTJ = room_yesterday + room_in - room_out; "" when 0
        $mktjRaw = $roomYesterday + $roomIn - $roomOut;
        $mktj = $mktjRaw == 0 ? 0 : (int) $mktjRaw;

        // TPK = if(nama empty → 0); else 100 * MKTJ / MKTS; iferror → 0
        $tpk = 0.0;
        if (! $namaKomersialEmpty) {
            $tpk = $mkts != 0 ? round(100 * $mktj / $mkts, 6) : 0.0;
        }

        // MTA = if(nama empty → 0); else wna_yesterday + wna_in - wna_out
        $mta = 0;
        if (! $namaKomersialEmpty) {
            $mta = (int) ($wnaYesterday + $wnaIn - $wnaOut);
        }

        // TA = if(nama empty → 0); else wna_in (0 when wna_in = 0)
        $ta = 0;
        if (! $namaKomersialEmpty) {
            $ta = (int) $wnaIn;
        }

        // MTNUS = if(nama empty → 0); else wni_yesterday + wni_in - wni_out
        $mtnus = 0;
        if (! $namaKomersialEmpty) {
            $mtnus = (int) ($wniYesterday + $wniIn - $wniOut);
        }

        // TNUS = if(nama empty → 0); else wni_in (0 when wni_in = 0)
        $tnus = 0;
        if (! $namaKomersialEmpty) {
            $tnus = (int) $wniIn;
        }

        // RLMTA = if(nama empty → 0); else MTA / TA; iferror → 0
        $rlmta = 0.0;
        if (! $namaKomersialEmpty) {
            $rlmta = $ta != 0 ? round($mta / $ta, 6) : 0.0;
        }

        // RLMTNUS = if(nama empty → 0); else MTNUS / TNUS; iferror → 0
        $rlmtnus = 0.0;
        if (! $namaKomersialEmpty) {
            $rlmtnus = $tnus != 0 ? round($mtnus / $tnus, 6) : 0.0;
        }

        // MTGAB = if(status_kunjungan empty → 0); else MTA + MTNUS
        $mtgab = 0;
        if (! $statusKunjunganEmpty) {
            $mtgab = $mta + $mtnus;
        }

        // TGAB = if(status_kunjungan empty → 0); else TA + TNUS
        $tgab = 0;
        if (! $statusKunjunganEmpty) {
            $tgab = $ta + $tnus;
        }

        // RLMTGAB = MTGAB / TGAB; iferror → 0
        $rlmtgab = 0.0;
        if ($tgab != 0) {
            $rlmtgab = round($mtgab / $tgab, 6);
        }

        // GPR = MTGAB / MKTJ; iferror → 0
        $gpr = 0.0;
        if ($mktj != 0) {
            $gpr = round($mtgab / $mktj, 6);
        }

        // TPTT = if(nama empty → 0); else 100 * MTGAB / bed; iferror → 0
        $tptt = 0.0;
        if (! $namaKomersialEmpty) {
            $tptt = ($bed !== null && $bed != 0) ? round(100 * $mtgab / $bed, 6) : 0.0;
        }

        // Error TPK: if(nama empty → 0); (TPK <= 0 || TPK > 100) ? 1 : 0
        $errorTpk = 0;
        if (! $namaKomersialEmpty) {
            $errorTpk = ($tpk <= 0 || $tpk > 100) ? 1 : 0;
        }

        // Error RLMTA: if(nama empty → 0); if(MTA=0 & TA=0 → 0); (RLMTA < 1 || RLMTA > 5) ? 1 : 0
        $errorRlmta = 0;
        if (! $namaKomersialEmpty) {
            if ($mta !== 0 || $ta !== 0) {
                $errorRlmta = ($rlmta < 1 || $rlmta > 5) ? 1 : 0;
            }
        }

        // Error RLMTNUS: if(nama empty → 0); if(MTNUS=0 & TNUS=0 → 0); (RLMTNUS < 1 || RLMTNUS > 5) ? 1 : 0
        $errorRlmtnus = 0;
        if (! $namaKomersialEmpty) {
            if ($mtnus !== 0 || $tnus !== 0) {
                $errorRlmtnus = ($rlmtnus < 1 || $rlmtnus > 5) ? 1 : 0;
            }
        }

        // Error GPR: if(nama empty → 0); if(MTGAB=0 & MKTJ=0 → 0);
        //            jenis >= 2: (GPR<1||GPR>8)?1:0; jenis=1: (GPR<1||GPR>4)?1:0; else 0
        $errorGpr = 0;
        if (! $namaKomersialEmpty) {
            if ($mtgab !== 0 || $mktj !== 0) {
                if ($jenisAkomodasi !== null && $jenisAkomodasi >= 2) {
                    $errorGpr = ($gpr < 1 || $gpr > 8) ? 1 : 0;
                } elseif ($jenisAkomodasi === 1) {
                    $errorGpr = ($gpr < 1 || $gpr > 4) ? 1 : 0;
                }
            }
        }

        // Error TPTT: if(nama empty → 0); if(MTGAB=0 & MTA=0 → 0); (TPTT < 0 || TPTT > 100) ? 1 : 0
        $errorTptt = 0;
        if (! $namaKomersialEmpty) {
            if ($mtgab !== 0 || $mta !== 0) {
                $errorTptt = ($tptt < 0 || $tptt > 100) ? 1 : 0;
            }
        }

        // Error Hari: 0 for now
        $errorHari = 0;

        $jumlahError = $errorTpk + $errorRlmta + $errorRlmtnus + $errorGpr + $errorTptt + $errorHari;

        return array_merge($record, [
            'mkts' => $mkts,
            'mktj' => $mktj,
            'tpk' => $tpk,
            'mta' => $mta,
            'ta' => $ta,
            'mtnus' => $mtnus,
            'tnus' => $tnus,
            'rlmta' => $rlmta,
            'rlmtnus' => $rlmtnus,
            'mtgab' => $mtgab,
            'tgab' => $tgab,
            'rlmtgab' => $rlmtgab,
            'gpr' => $gpr,
            'tptt' => $tptt,
            'jumlah_hari' => null,
            'error_tpk' => $errorTpk,
            'error_rlmta' => $errorRlmta,
            'error_rlmtnus' => $errorRlmtnus,
            'error_gpr' => $errorGpr,
            'error_tptt' => $errorTptt,
            'error_hari' => $errorHari,
            'jumlah_error' => $jumlahError,
        ]);
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

    /**
     * Calculate and persist indicator values for the synced period.
     * Aggregates input rows per regency/jenis_akomodasi and applies the
     * indicator formulas translated from the original Excel spreadsheet.
     */
    private function calculateIndicatorValues(SyncStatus $syncStatus): void
    {
        try {
            $yearId = $syncStatus->year_id;
            $monthId = $syncStatus->month_id;

            $aggregates = Input::query()
                ->select([
                    'kode_kab',
                    'jenis_akomodasi',
                    DB::raw('SUM(mktj) as sum_mktj'),
                    DB::raw('SUM(mkts) as sum_mkts'),
                    DB::raw('SUM(mta) as sum_mta'),
                    DB::raw('SUM(ta) as sum_ta'),
                    DB::raw('SUM(mtnus) as sum_mtnus'),
                    DB::raw('SUM(tnus) as sum_tnus'),
                    DB::raw('SUM(mtgab) as sum_mtgab'),
                    DB::raw('SUM(bed) as sum_bed'),
                ])
                ->where('tahun', $yearId)
                ->where('bulan', $monthId)
                ->whereIn('jenis_akomodasi', [1, 2])
                ->groupBy('kode_kab', 'jenis_akomodasi')
                ->get();

            if ($aggregates->isEmpty()) {
                return;
            }

            $totalAggregates = Input::query()
                ->select([
                    'kode_kab',
                    DB::raw('SUM(mktj) as sum_mktj'),
                    DB::raw('SUM(mkts) as sum_mkts'),
                    DB::raw('SUM(mta) as sum_mta'),
                    DB::raw('SUM(ta) as sum_ta'),
                    DB::raw('SUM(mtnus) as sum_mtnus'),
                    DB::raw('SUM(tnus) as sum_tnus'),
                    DB::raw('SUM(mtgab) as sum_mtgab'),
                    DB::raw('SUM(bed) as sum_bed'),
                ])
                ->where('tahun', $yearId)
                ->where('bulan', $monthId)
                ->groupBy('kode_kab')
                ->get()
                ->keyBy('kode_kab');

            $indicators = Indicator::query()->pluck('id', 'code');
            $categories = Category::query()->pluck('id', 'code');

            foreach (['TPK', 'RLMTA', 'RLMTN', 'GPR', 'TPTT'] as $code) {
                if ($indicators->get($code) === null) {
                    throw new RuntimeException("Indicator '{$code}' not found in database");
                }
            }

            foreach (['1', '2'] as $code) {
                if ($categories->get($code) === null) {
                    throw new RuntimeException("Category '{$code}' not found in database");
                }
            }

            $totalCategoryIdRaw = Category::whereNull('code')->value('id');
            if ($totalCategoryIdRaw === null) {
                throw new RuntimeException("Category 'Total' not found in database");
            }
            $totalCategoryId = (int) $totalCategoryIdRaw;

            /** @var array<int|string, array<int, mixed>> $byRegencyAndJenis */
            $byRegencyAndJenis = [];
            foreach ($aggregates as $agg) {
                $byRegencyAndJenis[$agg->kode_kab][$agg->jenis_akomodasi] = $agg;
            }

            IndicatorValue::where('year_id', $yearId)
                ->where('month_id', $monthId)
                ->delete();

            $bintangCatId = (int) $categories->get('1');
            $nonBintangCatId = (int) $categories->get('2');
            // $totalCategoryId already resolved above
            $tpkId = (int) $indicators->get('TPK');
            $rlmtaId = (int) $indicators->get('RLMTA');
            $rlmtnId = (int) $indicators->get('RLMTN');
            $gprId = (int) $indicators->get('GPR');
            $tpttId = (int) $indicators->get('TPTT');

            $records = [];
            $now = now()->toDateTimeString();

            foreach (array_keys($byRegencyAndJenis) as $regencyId) {
                $agg1 = $byRegencyAndJenis[$regencyId][1] ?? null; // Bintang
                $agg2 = $byRegencyAndJenis[$regencyId][2] ?? null; // Non Bintang

                // TPK Bintang — jenis=1
                $tpkBintang = $agg1 !== null && (float) $agg1->sum_mkts > 0
                    ? round(100 * (float) $agg1->sum_mktj / (float) $agg1->sum_mkts, 2)
                    : null;
                $records[] = $this->makeIndicatorRecord($regencyId, $yearId, $monthId, $tpkId, $bintangCatId, $tpkBintang, $now);

                // TPK Non Bintang — jenis=2
                $tpkNonBintang = $agg2 !== null && (float) $agg2->sum_mkts > 0
                    ? round(100 * (float) $agg2->sum_mktj / (float) $agg2->sum_mkts, 2)
                    : null;
                $records[] = $this->makeIndicatorRecord($regencyId, $yearId, $monthId, $tpkId, $nonBintangCatId, $tpkNonBintang, $now);

                // RLMTA Bintang — jenis=1
                $rlmtaBintang = $agg1 !== null && (float) $agg1->sum_ta > 0
                    ? round((float) $agg1->sum_mta / (float) $agg1->sum_ta, 2)
                    : null;
                $records[] = $this->makeIndicatorRecord($regencyId, $yearId, $monthId, $rlmtaId, $bintangCatId, $rlmtaBintang, $now);

                // RLMTA Non Bintang — jenis=2
                $rlmtaNonBintang = $agg2 !== null && (float) $agg2->sum_ta > 0
                    ? round((float) $agg2->sum_mta / (float) $agg2->sum_ta, 2)
                    : null;
                $records[] = $this->makeIndicatorRecord($regencyId, $yearId, $monthId, $rlmtaId, $nonBintangCatId, $rlmtaNonBintang, $now);

                // RLMTN Bintang — jenis=1
                $rlmtnBintang = $agg1 !== null && (float) $agg1->sum_tnus > 0
                    ? round((float) $agg1->sum_mtnus / (float) $agg1->sum_tnus, 2)
                    : null;
                $records[] = $this->makeIndicatorRecord($regencyId, $yearId, $monthId, $rlmtnId, $bintangCatId, $rlmtnBintang, $now);

                // RLMTN Non Bintang — jenis=2
                $rlmtnNonBintang = $agg2 !== null && (float) $agg2->sum_tnus > 0
                    ? round((float) $agg2->sum_mtnus / (float) $agg2->sum_tnus, 2)
                    : null;
                $records[] = $this->makeIndicatorRecord($regencyId, $yearId, $monthId, $rlmtnId, $nonBintangCatId, $rlmtnNonBintang, $now);

                // GPR Bintang — jenis=1
                $gprBintang = $agg1 !== null && (float) $agg1->sum_mktj > 0
                    ? round((float) $agg1->sum_mtgab / (float) $agg1->sum_mktj, 2)
                    : null;
                $records[] = $this->makeIndicatorRecord($regencyId, $yearId, $monthId, $gprId, $bintangCatId, $gprBintang, $now);

                // GPR Non Bintang — jenis=2
                $gprNonBintang = $agg2 !== null && (float) $agg2->sum_mktj > 0
                    ? round((float) $agg2->sum_mtgab / (float) $agg2->sum_mktj, 2)
                    : null;
                $records[] = $this->makeIndicatorRecord($regencyId, $yearId, $monthId, $gprId, $nonBintangCatId, $gprNonBintang, $now);

                // TPTT Bintang — jenis=1
                $tpttBintang = $agg1 !== null && (float) $agg1->sum_bed > 0
                    ? round(100 * (float) $agg1->sum_mtgab / (float) $agg1->sum_bed, 2)
                    : null;
                $records[] = $this->makeIndicatorRecord($regencyId, $yearId, $monthId, $tpttId, $bintangCatId, $tpttBintang, $now);

                // TPTT Non Bintang — jenis=2
                $tpttNonBintang = $agg2 !== null && (float) $agg2->sum_bed > 0
                    ? round(100 * (float) $agg2->sum_mtgab / (float) $agg2->sum_bed, 2)
                    : null;
                $records[] = $this->makeIndicatorRecord($regencyId, $yearId, $monthId, $tpttId, $nonBintangCatId, $tpttNonBintang, $now);

                $totAgg = $totalAggregates->get($regencyId);

                // TPK Total — all jenis
                $tpkTotal = $totAgg !== null && (float) $totAgg->sum_mkts > 0
                    ? round(100 * (float) $totAgg->sum_mktj / (float) $totAgg->sum_mkts, 2)
                    : null;
                $records[] = $this->makeIndicatorRecord($regencyId, $yearId, $monthId, $tpkId, $totalCategoryId, $tpkTotal, $now);

                // RLMTA Total — all jenis
                $rlmtaTotal = $totAgg !== null && (float) $totAgg->sum_ta > 0
                    ? round((float) $totAgg->sum_mta / (float) $totAgg->sum_ta, 2)
                    : null;
                $records[] = $this->makeIndicatorRecord($regencyId, $yearId, $monthId, $rlmtaId, $totalCategoryId, $rlmtaTotal, $now);

                // RLMTN Total — all jenis
                $rlmtnTotal = $totAgg !== null && (float) $totAgg->sum_tnus > 0
                    ? round((float) $totAgg->sum_mtnus / (float) $totAgg->sum_tnus, 2)
                    : null;
                $records[] = $this->makeIndicatorRecord($regencyId, $yearId, $monthId, $rlmtnId, $totalCategoryId, $rlmtnTotal, $now);

                // GPR Total — all jenis
                $gprTotal = $totAgg !== null && (float) $totAgg->sum_mktj > 0
                    ? round((float) $totAgg->sum_mtgab / (float) $totAgg->sum_mktj, 2)
                    : null;
                $records[] = $this->makeIndicatorRecord($regencyId, $yearId, $monthId, $gprId, $totalCategoryId, $gprTotal, $now);

                // TPTT Total — all jenis
                $tpttTotal = $totAgg !== null && (float) $totAgg->sum_bed > 0
                    ? round(100 * (float) $totAgg->sum_mtgab / (float) $totAgg->sum_bed, 2)
                    : null;
                $records[] = $this->makeIndicatorRecord($regencyId, $yearId, $monthId, $tpttId, $totalCategoryId, $tpttTotal, $now);
            }

            foreach (array_chunk($records, 500) as $chunk) {
                IndicatorValue::insert($chunk);
            }
        } catch (Throwable $e) {
            throw new ImportException(
                'Indicator calculation failed: '.$e->getMessage(),
                'Indicator calculation failed'
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function makeIndicatorRecord(
        int|string $regencyId,
        int $yearId,
        int $monthId,
        int $indicatorId,
        int $categoryId,
        ?float $value,
        string $now
    ): array {
        return [
            'id' => (string) Str::uuid(),
            'regency_id' => (int) $regencyId,
            'year_id' => $yearId,
            'month_id' => $monthId,
            'indicator_id' => $indicatorId,
            'category_id' => $categoryId,
            'value' => $value,
            'created_at' => $now,
            'updated_at' => $now,
        ];
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
