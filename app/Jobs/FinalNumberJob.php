<?php

namespace App\Jobs;

use App\Exceptions\ImportException;
use App\Models\Category;
use App\Models\FinalNumber;
use App\Models\Regency;
use App\Models\SyncStatus;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use Throwable;

class FinalNumberJob implements ShouldQueue
{
    use Queueable;

    protected SyncStatus $status;

    /**
     * Create a new job instance.
     */
    public function __construct(SyncStatus $status)
    {
        $this->status = $status;
        $this->status->update([
            'status' => 'loading',
            'system_message' => 'Sync process started',
            'user_message' => 'Sync process started',
        ]);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
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

            $relativePath = 'uploads/' . ltrim((string) $syncStatus->filename, '/');

            $extension = Str::lower((string) Str::of($syncStatus->filename)->afterLast('.'));
            if ($extension !== 'xlsx') {
                throw new ImportException('Uploaded file must be a .xlsx', 'Uploaded file must be .xlsx');
            }

            $imported = $this->importFromStorage($relativePath, $syncStatus);

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

    private function importFromStorage(string $relativePath, SyncStatus $syncStatus): int
    {
        if (! Storage::disk('local')->exists($relativePath)) {
            throw new ImportException("XLSX file not found: {$relativePath}", 'Uploaded file could not be found');
        }

        $absolutePath = Storage::disk('local')->path($relativePath);

        // Single shared reader instance reused across all chunk reads.
        $reader = new Xlsx();
        $reader->setReadDataOnly(true);

        $info = $reader->listWorksheetInfo($absolutePath);
        $first = $info[0] ?? null;
        if (! is_array($first)) {
            throw new ImportException('Unable to read XLSX metadata', 'Could not read the uploaded file');
        }

        $totalRows = (int) ($first['totalRows'] ?? 0);
        $totalColumns = (int) ($first['totalColumns'] ?? 0);

        if ($totalRows < 2 || $totalColumns < 1) {
            throw new ImportException('XLSX appears to be empty', 'The uploaded file is empty');
        }

        // Read and validate headers (row 1).
        $headersRow = $this->readXlsxRows($reader, $absolutePath, 1, 1);
        $rawHeaders = $headersRow[0] ?? [];
        if (! is_array($rawHeaders) || $rawHeaders === []) {
            throw new ImportException('XLSX header row is missing', 'File has no header row');
        }

        $headers = array_map(
            fn($v) => $this->normalizeHeader($this->stringifyCellValue($v)),
            $rawHeaders
        );

        // Required columns that must exist in the file.
        $required = ['kode_kab', 'bintang', 'non_bintang'];
        $headerIndex = array_flip(array_filter($headers, fn($h) => $h !== ''));

        foreach ($required as $col) {
            if (! isset($headerIndex[$col])) {
                throw new ImportException(
                    "Missing required column '{$col}' in file headers",
                    "File is missing the required '{$col}' column"
                );
            }
        }

        $colKodeKab    = $headerIndex['kode_kab'];
        $colBintang    = $headerIndex['bintang'];
        $colNonBintang = $headerIndex['non_bintang'];

        // year_id and month_id come from the sync status, not from the file.
        $yearId  = (int) $syncStatus->year_id;
        $monthId = (int) $syncStatus->month_id;

        // Build FK lookup maps.
        $maps = $this->buildForeignKeyMaps();

        $bintangCatId    = (int) $maps['categories']['Bintang'];
        $nonBintangCatId = (int) $maps['categories']['Non Bintang'];

        // Validate all kode_kab values in a single pre-pass before touching the DB.
        $this->validateRegencies($reader, $absolutePath, $totalRows, $colKodeKab, $maps['regencies']);

        // Delete existing targets for this period before re-importing.
        FinalNumber::where('year_id', $yearId)
            ->where('month_id', $monthId)
            ->delete();

        $insertBatchSize = 500;
        $buffer  = [];
        $imported = 0;
        $now     = now()->toDateTimeString();

        $chunkSize = 1000;
        $start = 2; // row 1 is headers

        while ($start <= $totalRows) {
            $end  = min($totalRows, $start + $chunkSize - 1);
            $rows = $this->readXlsxRows($reader, $absolutePath, $start, $end);

            foreach ($rows as $offset => $row) {
                if (! is_array($row) || $row === []) {
                    continue;
                }

                $rowNumber = $start + $offset;

                $kodeKab    = trim($this->stringifyCellValue($row[$colKodeKab] ?? ''));
                $bintang    = $row[$colBintang] ?? null;
                $nonBintang = $row[$colNonBintang] ?? null;

                // Skip entirely blank rows.
                if ($kodeKab === '' && $bintang === null && $nonBintang === null) {
                    continue;
                }

                if ($kodeKab === '') {
                    throw new ImportException(
                        "Missing kode_kab at row {$rowNumber}",
                        "Error at row {$rowNumber}: missing kode_kab"
                    );
                }

                $regencyId = $this->resolveRegencyId($kodeKab, $maps['regencies']);
                if ($regencyId === null) {
                    throw new ImportException(
                        "Unknown kode_kab '{$kodeKab}' at row {$rowNumber}",
                        "Error at row {$rowNumber}: unknown kode_kab '{$kodeKab}'"
                    );
                }

                // One record per category (Bintang and Non Bintang) per row.
                $buffer[] = [
                    'id'          => (string) Str::uuid(),
                    'month_id'    => $monthId,
                    'year_id'     => $yearId,
                    'category_id' => $bintangCatId,
                    'regency_id'  => $regencyId,
                    'value'       => $this->resolveDecimalValue($bintang),
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];

                $buffer[] = [
                    'id'          => (string) Str::uuid(),
                    'month_id'    => $monthId,
                    'year_id'     => $yearId,
                    'category_id' => $nonBintangCatId,
                    'regency_id'  => $regencyId,
                    'value'       => $this->resolveDecimalValue($nonBintang),
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];

                if (count(value: $buffer) >= $insertBatchSize) {
                    FinalNumber::insert($buffer);
                    $imported += count($buffer);
                    $buffer = [];
                }
            }

            unset($rows);
            $start = $end + 1;
        }

        if ($buffer !== []) {
            FinalNumber::insert($buffer);
            $imported += count($buffer);
        }

        // Return number of data rows (each row produces 2 records, so divide by 2
        // if you want "hotel rows"; keep as-is if you want total DB records inserted).
        return $imported;
    }

    /**
     * Scan all data rows and collect any unknown kode_kab values,
     * reporting them all at once before touching the DB.
     *
     * @param  array<string, int>  $regencyMap
     */
    private function validateRegencies(
        Xlsx $reader,
        string $absolutePath,
        int $totalRows,
        int $colKodeKab,
        array $regencyMap
    ): void {
        $missingRegencies = [];

        $chunkSize = 1000;
        $start = 2;

        while ($start <= $totalRows) {
            $end  = min($totalRows, $start + $chunkSize - 1);
            $rows = $this->readXlsxRows($reader, $absolutePath, $start, $end);

            foreach ($rows as $row) {
                if (! is_array($row) || $row === []) {
                    continue;
                }

                $kodeKab = trim($this->stringifyCellValue($row[$colKodeKab] ?? ''));

                if ($kodeKab === '') {
                    continue;
                }

                if ($this->resolveRegencyId($kodeKab, $regencyMap) === null) {
                    $missingRegencies[$kodeKab] = true;
                }
            }

            unset($rows);
            $start = $end + 1;
        }

        if ($missingRegencies !== []) {
            $codes = implode(', ', array_slice(array_keys($missingRegencies), 0, 25));
            throw new ImportException(
                "Unknown kode_kab long_codes: {$codes}",
                'File contains regency codes not found in the database'
            );
        }
    }

    /**
     * Build all FK lookup maps needed for the import.
     *
     * @return array{regencies: array<string,int>, categories: array<string,int>}
     */
    private function buildForeignKeyMaps(): array
    {
        /** @var array<string, int> $regencies */
        $regencies = Regency::query()->pluck('id', 'long_code')->all();

        /** @var array<string, int> $categories keyed by name, e.g. ['Bintang' => 1, 'Non Bintang' => 2] */
        $categories = Category::query()->pluck('id', 'name')->all();

        foreach (['Bintang', 'Non Bintang'] as $name) {
            if (! isset($categories[$name])) {
                throw new \RuntimeException("Category '{$name}' not found in database");
            }
        }

        return compact('regencies', 'categories');
    }

    /**
     * @param  array<string, int>  $map
     */
    private function resolveRegencyId(string $longCode, array $map): ?int
    {
        $longCode = trim($longCode);
        if ($longCode === '') {
            return null;
        }

        return isset($map[$longCode]) ? (int) $map[$longCode] : null;
    }

    /**
     * Resolve a cell value to a non-negative decimal (defaults to 0.00).
     */
    private function resolveDecimalValue(mixed $value): float
    {
        $string = trim($this->stringifyCellValue($value));

        // Replace comma with point for decimal separator
        $normalized = str_replace(',', '.', $string);

        // Remove any non-numeric except point
        $normalized = preg_replace('/[^0-9.]/', '', $normalized);

        // If multiple points, keep only the first
        $parts = explode('.', $normalized);
        if (count($parts) > 2) {
            $normalized = $parts[0] . '.' . implode('', array_slice($parts, 1));
        }

        // Parse as float, round to 2 decimals
        return is_numeric($normalized) ? round((float) $normalized, 2) : 0.00;
    }

    /**
     * Load only rows $startRow–$endRow from the XLSX file.
     * Frees the spreadsheet object immediately after extracting the array
     * so memory stays flat across chunks.
     *
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
        $highestRow    = $sheet->getHighestRow();

        $clampedEndRow = min($endRow, $highestRow);
        $range = 'A' . $startRow . ':' . $highestColumn . $clampedEndRow;
        $rows  = $sheet->rangeToArray($range, null, true, false);

        $spreadsheet->disconnectWorksheets();
        unset($sheet, $spreadsheet);
        gc_collect_cycles();

        return is_array($rows) ? $rows : [];
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

    private function normalizeHeader(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;

        return Str::snake(Str::lower(trim($value)));
    }
}
