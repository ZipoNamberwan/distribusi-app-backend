<?php

namespace App\Jobs;

use App\Models\Input;
use App\Models\SyncStatus;
use App\Models\Tabulation;
use App\Services\GoogleSheetService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SyncDataJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(GoogleSheetService $sheet): void
    {
        $syncStatus = SyncStatus::create([
            'status' => 'loading',
            'message' => 'Starting sync process',
        ]);

        try {
            $spreadsheetId = (string) config('services.google_sheets.spreadsheet_id', '');
            $spreadsheetId = $this->normalizeSpreadsheetId($spreadsheetId);

            if ($spreadsheetId === '') {
                throw new \Exception('Spreadsheet ID is not configured');
            }

            DB::beginTransaction();

            // Sync INPUT sheet
            $this->syncInputSheet($sheet, $spreadsheetId);

            // Sync TABULASI sheet
            $this->syncTabulasiSheet($sheet, $spreadsheetId);

            DB::commit();

            $syncStatus->update([
                'status' => 'success',
                'message' => 'Data synced successfully',
            ]);
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('Sync failed: '.$e->getMessage(), [
                'exception' => $e,
            ]);

            $syncStatus->update([
                'status' => 'failed',
                'message' => substr($e->getMessage(), 0, 1000),
            ]);
        }
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
            'kode_kab' => $this->getValue($row, ['kode_kab', 'kode kab']),
            'kode_kec' => $this->getValue($row, ['kode_kec', 'kode kec']),
            'kode_des' => $this->getValue($row, ['kode_des', 'kode des']),
            'status_kur' => $this->getValue($row, ['status_kur', 'status kur']) ?: null,
            'jenis_ako' => $this->getValue($row, ['jenis_ako', 'jenis ako']) ?: null,
            'kelas_ako' => $this->getValue($row, ['kelas_ako', 'kelas ako']) ?: null,
            'nama_kor' => $this->getValue($row, ['nama_kor', 'nama kor']),
            'alamat' => $this->getValue($row, ['alamat']),
            'room' => (int) ($this->getValue($row, ['room']) ?? 0),
            'bed' => (int) ($this->getValue($row, ['bed']) ?? 0),
            'room_yesterday' => (int) ($this->getValue($row, ['room_yesterday', 'room yesterday']) ?? 0),
            'room_in' => (int) ($this->getValue($row, ['room_in', 'room in']) ?? 0),
            'room_out' => (int) ($this->getValue($row, ['room_out', 'room out']) ?? 0),
            'day' => $this->getValue($row, ['day']) ?: null,
            'wna_yesterday' => (int) ($this->getValue($row, ['wna_yesterday', 'wna yesterday']) ?? 0),
            'wni_yesterday' => (int) ($this->getValue($row, ['wni_yesterday', 'wni yesterday']) ?? 0),
            'wna_in' => (int) ($this->getValue($row, ['wna_in', 'wna in']) ?? 0),
            'wni_in' => (int) ($this->getValue($row, ['wni_in', 'wni in']) ?? 0),
            'wna_out' => (int) ($this->getValue($row, ['wna_out', 'wna out']) ?? 0),
            'wni_out' => (int) ($this->getValue($row, ['wni_out', 'wni out']) ?? 0),
            'status' => $this->getValue($row, ['status']),
            'room_per' => (int) ($this->getValue($row, ['room_per', 'room per']) ?? 0),
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
