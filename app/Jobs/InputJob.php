<?php

namespace App\Jobs;

use App\Exceptions\ImportException;
use App\Models\Category;
use App\Models\Confirmation;
use App\Models\Enumeration;
use App\Models\Error as ErrorModel;
use App\Models\ErrorSummary;
use App\Models\ErrorType;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Models\Input;
use App\Models\Month;
use App\Models\Regency;
use App\Models\SyncStatus;
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
use Throwable;

class InputJob implements ShouldQueue
{
    use Queueable;

    protected SyncStatus $status;

    public function __construct(SyncStatus $status)
    {
        $this->status = $status;
        $this->status->update([
            'status' => 'loading',
            'system_message' => 'Sync process started',
            'user_message' => 'Sync process started',
        ]);
    }

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
            if (! in_array($extension, ['csv', 'xlsx'], true)) {
                throw new ImportException('Uploaded file must be a .csv or .xlsx', 'Uploaded file must be .csv or .xlsx');
            }

            $imported = match ($extension) {
                'xlsx' => $this->importInputXlsxFromStorage($relativePath, $syncStatus),
            };

            // Input::where('bulan', $syncStatus->month_id)
            //     ->where('tahun', $syncStatus->year_id)
            //     ->where('sync_status_id', '!=', (string) $syncStatus->id)
            //     ->delete();

            $this->calculateIndicatorValues($syncStatus);
            $this->summarizeErrors($syncStatus);
            $this->populateConfirmations($syncStatus);
            $this->calculateEnumerations($syncStatus);

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

    private function importInputXlsxFromStorage(string $relativePath, SyncStatus $syncStatus): int
    {
        if (! Storage::disk('local')->exists($relativePath)) {
            throw new ImportException("XLSX file not found: {$relativePath}", 'Uploaded file could not be found');
        }

        $absolutePath = Storage::disk('local')->path($relativePath);

        // FIX 1: Create a single shared reader instance reused across all chunk reads,
        // avoiding repeated object construction and file-handle churn.
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

        $headers = array_map([$this, 'normalizeHeader'], array_map(fn($v) => $this->stringifyCellValue($v), $rawHeaders));

        $computedColumns = [
            'mkts',
            'mktj',
            'tpk',
            'mta',
            'ta',
            'mtnus',
            'tnus',
            'rlmta',
            'rlmtnus',
            'mtgab',
            'tgab',
            'rlmtgab',
            'gpr',
            'tptt',
            'jumlah_hari',
            'error_tpk',
            'error_rlmta',
            'error_rlmtnus',
            'error_gpr',
            'error_tptt',
            'error_hari',
            'jumlah_error',
        ];

        $allowedColumns = array_values(array_diff(
            Input::getModel()->getConnection()
                ->getSchemaBuilder()
                ->getColumnListing((new Input)->getTable()),
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
        $fasihMaps = $this->buildIdFasihLookupMap($syncStatus->month_id, $syncStatus->year_id);
        // $indexes = $this->requiredInputForeignKeyIndexes($columnMap);
        $monthDay = (int) ($maps['months'][$syncStatus->month_id] ?? 0);

        // FIX 2: Removed separate validateInputForeignKeysInXlsx pre-pass.
        // Foreign-key validation is now inlined into the single import loop below,
        // collecting all unknown kode_kab values and throwing once at the end —
        // eliminating a full duplicate scan of the file (O(n) → O(n) instead of O(2n)).

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

        // FIX 3: Build the empty-record template once; use array union (+=) instead of
        // array_merge() inside the hot loop — avoids allocating a new merged array per row.
        $emptyRecord = array_fill_keys(array_values($columnMap), null);
        foreach ($defaultZeroIntegers as $col) {
            if (array_key_exists($col, $emptyRecord)) {
                $emptyRecord[$col] = 0;
            }
        }
        $emptyRecord['tahun'] = null;
        $emptyRecord['bulan'] = null;
        $emptyRecord['sync_status_id'] = null;

        $insertBatchSize = 500;
        $insertBuffer = [];
        $updateBuffer = [];
        $imported = 0;
        $missingRegencies = [];

        $fkCodeColumns = ['kode_kab' => true];

        $chunkSize = 1000;
        $start = 2;

        while ($start <= $totalRows) {
            $end = min($totalRows, $start + $chunkSize - 1);

            // FIX 1 (continued): Pass the shared $reader into readXlsxRows so it is
            // not re-instantiated on every chunk call.
            $rows = $this->readXlsxRows($reader, $absolutePath, $start, $end);

            foreach ($rows as $offset => $row) {
                if (! is_array($row) || $row === []) {
                    continue;
                }

                $rowNumber = $start + $offset;

                // FIX 3 (continued): Copy template with array union instead of array_merge.
                $record = $emptyRecord;
                $record['id'] = (string) Str::uuid();
                $record['tahun'] = $syncStatus->year_id;
                $record['bulan'] = $syncStatus->month_id;
                $record['created_at'] = now();
                $record['updated_at'] = now();
                $record['sync_status_id'] = (string) $syncStatus->id;

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

                // FIX 2 (continued): Inline FK validation — collect unknown regencies
                // rather than throwing immediately, so all bad codes surface at once.
                $kodeKabCode = trim((string) ($record['kode_kab'] ?? ''));
                if ($kodeKabCode === '') {
                    throw new ImportException('Missing kode_kab at XLSX row ' . $rowNumber, 'Error at row ' . $rowNumber);
                }

                $kodeKabId = $this->resolveRegencyId($kodeKabCode, $maps['regencies']);
                if ($kodeKabId === null) {
                    $missingRegencies[$kodeKabCode] = true;
                    continue; // skip invalid row; report all at end
                }

                $record['kode_kab'] = $kodeKabId;

                try {
                    $record = $this->calculateTabulation($record, $monthDay);
                } catch (Throwable $calcEx) {
                    throw new ImportException(
                        'Error calculating tabulation at XLSX row ' . $rowNumber . ': ' . $calcEx->getMessage(),
                        'Error calculating tabulation'
                    );
                }

                $idFasih = trim((string) ($record['id_fasih'] ?? ''));

                if ($idFasih !== '' && isset($fasihMaps[$idFasih])) {
                    $recordForUpdate = $record;
                    unset($recordForUpdate['id']);
                    unset($recordForUpdate['created_at']);
                    $recordForUpdate['_id'] = $fasihMaps[$idFasih];
                    $updateBuffer[] = $recordForUpdate;
                } else {
                    $insertBuffer[] = $record;
                    if ($idFasih !== '') {
                        $fasihMaps[$idFasih] = $record['id'];
                    }
                }

                if (count($insertBuffer) >= $insertBatchSize) {
                    Input::insert($insertBuffer);
                    $imported += count($insertBuffer);
                    $insertBuffer = [];
                }

                if (count($updateBuffer) >= $insertBatchSize) {
                    foreach ($updateBuffer as $updateRecord) {
                        $id = $updateRecord['_id'];
                        unset($updateRecord['_id']);
                        Input::where('id', $id)->update($updateRecord);
                    }
                    $imported += count($updateBuffer);
                    $updateBuffer = [];
                }
            }

            // FIX 4: Free the rows array immediately after processing each chunk
            // instead of waiting for the next loop iteration.
            unset($rows);

            $start = $end + 1;
        }

        // FIX 2 (continued): Report all missing regencies after the single pass.
        $this->throwIfMissingForeignKeys($missingRegencies);

        if ($insertBuffer !== []) {
            Input::insert($insertBuffer);
            $imported += count($insertBuffer);
        }

        if ($updateBuffer !== []) {
            foreach ($updateBuffer as $updateRecord) {
                $id = $updateRecord['_id'];
                unset($updateRecord['_id']);
                Input::where('id', $id)->update($updateRecord);
            }
            $imported += count($updateBuffer);
        }

        return $imported;
    }

    /**
     * @return array{regencies: array<string, int>, months: array<int|string, int>}
     */
    private function buildInputForeignKeyMaps(): array
    {
        /** @var array<string, int> $regencies */
        $regencies = Regency::query()->pluck('id', 'short_code')->all();

        /** @var array<int|string, int> $months */
        $months = Month::query()->pluck('day', 'id')->all();

        return [
            'regencies' => $regencies,
            'months' => $months,
        ];
    }

    private function buildIdFasihLookupMap($month, $year): array
    {
        return Input::where('bulan', $month)
            ->where('tahun', $year)
            ->pluck('id', 'id_fasih')
            ->all();
    }

    /**
     * @param  array<string, bool>  $missingRegencies
     */
    private function throwIfMissingForeignKeys(array $missingRegencies): void
    {
        if ($missingRegencies === []) {
            return;
        }

        $codes = array_slice(array_keys($missingRegencies), 0, 25);
        throw new ImportException(
            'Unknown kode_kab short_codes: ' . implode(', ', $codes),
            'File contains references not found in the database'
        );
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

        if (isset($map[$shortCode])) {
            return (int) $map[$shortCode];
        }

        if (is_numeric($shortCode)) {
            $normalized = (string) ((int) $shortCode);
            if (isset($map[$normalized])) {
                return (int) $map[$normalized];
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
        $range = 'A' . $startRow . ':' . $highestColumn . $clampedEndRow;
        $rows = $sheet->rangeToArray($range, null, true, false);

        // FIX 5: Explicitly free the spreadsheet object and all its worksheets
        // before returning, so the GC can reclaim cell cache memory immediately.
        $spreadsheet->disconnectWorksheets();
        unset($sheet, $spreadsheet);
        gc_collect_cycles();

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

    private function normalizeHeader(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
        $value = trim($value);

        return Str::snake(Str::lower($value));
    }

    /**
     * Calculate tabulation columns from raw input data.
     *
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function calculateTabulation(array $record, int $monthDay = 0): array
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

        $mkts = (int) ($room ?? 0);

        $mktjRaw = $roomYesterday + $roomIn - $roomOut;
        $mktj = $mktjRaw == 0 ? 0 : (int) $mktjRaw;

        $tpk = 0.0;
        if (! $namaKomersialEmpty) {
            $tpk = $mkts != 0 ? round(100 * $mktj / $mkts, 6) : 0.0;
        }

        $mta = 0;
        if (! $namaKomersialEmpty) {
            $mta = (int) ($wnaYesterday + $wnaIn - $wnaOut);
        }

        $ta = 0;
        if (! $namaKomersialEmpty) {
            $ta = (int) $wnaIn;
        }

        $mtnus = 0;
        if (! $namaKomersialEmpty) {
            $mtnus = (int) ($wniYesterday + $wniIn - $wniOut);
        }

        $tnus = 0;
        if (! $namaKomersialEmpty) {
            $tnus = (int) $wniIn;
        }

        $rlmta = 0.0;
        if (! $namaKomersialEmpty) {
            $rlmta = $ta != 0 ? round($mta / $ta, 6) : 0.0;
        }

        $rlmtnus = 0.0;
        if (! $namaKomersialEmpty) {
            $rlmtnus = $tnus != 0 ? round($mtnus / $tnus, 6) : 0.0;
        }

        $mtgab = 0;
        if (! $statusKunjunganEmpty) {
            $mtgab = $mta + $mtnus;
        }

        $tgab = 0;
        if (! $statusKunjunganEmpty) {
            $tgab = $ta + $tnus;
        }

        $rlmtgab = $tgab != 0 ? round($mtgab / $tgab, 6) : 0.0;
        $gpr = $mktj != 0 ? round($mtgab / $mktj, 6) : 0.0;

        $tptt = 0.0;
        if (! $namaKomersialEmpty) {
            $tptt = ($bed !== null && $bed != 0) ? round(100 * $mtgab / $bed, 6) : 0.0;
        }

        $errorTpk = 0;
        if (! $namaKomersialEmpty) {
            $errorTpk = ($tpk <= 0 || $tpk > 100) ? 1 : 0;
        }

        $errorRlmta = 0;
        if (! $namaKomersialEmpty && ($mta !== 0 || $ta !== 0)) {
            $errorRlmta = ($rlmta < 1 || $rlmta > 5) ? 1 : 0;
        }

        $errorRlmtnus = 0;
        if (! $namaKomersialEmpty && ($mtnus !== 0 || $tnus !== 0)) {
            $errorRlmtnus = ($rlmtnus < 1 || $rlmtnus > 5) ? 1 : 0;
        }

        $errorGpr = 0;
        if (! $namaKomersialEmpty && ($mtgab !== 0 || $mktj !== 0)) {
            if ($jenisAkomodasi !== null && $jenisAkomodasi >= 2) {
                $errorGpr = ($gpr < 1 || $gpr > 8) ? 1 : 0;
            } elseif ($jenisAkomodasi === 1) {
                $errorGpr = ($gpr < 1 || $gpr > 4) ? 1 : 0;
            }
        }

        $errorTptt = 0;
        if (! $namaKomersialEmpty && ($mtgab !== 0 || $mta !== 0)) {
            $errorTptt = ($tptt < 0 || $tptt > 100) ? 1 : 0;
        }

        $inputDay = isset($record['day']) ? (int) $record['day'] : 0;
        $errorHari = ($monthDay > 0 && $inputDay !== $monthDay) ? 1 : 0;

        $jumlahError = $errorTpk + $errorRlmta + $errorRlmtnus + $errorGpr + $errorTptt + $errorHari;

        $record['mkts'] = $mkts;
        $record['mktj'] = $mktj;
        $record['tpk'] = $tpk;
        $record['mta'] = $mta;
        $record['ta'] = $ta;
        $record['mtnus'] = $mtnus;
        $record['tnus'] = $tnus;
        $record['rlmta'] = $rlmta;
        $record['rlmtnus'] = $rlmtnus;
        $record['mtgab'] = $mtgab;
        $record['tgab'] = $tgab;
        $record['rlmtgab'] = $rlmtgab;
        $record['gpr'] = $gpr;
        $record['tptt'] = $tptt;
        $record['jumlah_hari'] = null;
        $record['error_tpk'] = $errorTpk;
        $record['error_rlmta'] = $errorRlmta;
        $record['error_rlmtnus'] = $errorRlmtnus;
        $record['error_gpr'] = $errorGpr;
        $record['error_tptt'] = $errorTptt;
        $record['error_hari'] = $errorHari;
        $record['jumlah_error'] = $jumlahError;

        return $record;
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
            if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})/', $dateString, $matches)) {
                $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                $year = $matches[3];

                return "{$year}-{$month}-{$day}";
            }

            if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $dateString)) {
                $date = new \DateTime($dateString);

                return $date->format('Y-m-d');
            }

            $date = new \DateTime($dateString);

            return $date->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Calculate and persist indicator values for the synced period.
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

            IndicatorValue::where('year_id', $yearId)
                ->where('month_id', $monthId)
                ->delete();

            $bintangCatId = (int) $categories->get('1');
            $nonBintangCatId = (int) $categories->get('2');
            $tpkId = (int) $indicators->get('TPK');
            $rlmtaId = (int) $indicators->get('RLMTA');
            $rlmtnId = (int) $indicators->get('RLMTN');
            $gprId = (int) $indicators->get('GPR');
            $tpttId = (int) $indicators->get('TPTT');

            // FIX 6: Build a flat map keyed by "regencyId_jenis" directly from the
            // aggregates collection — eliminates the intermediate $byRegencyAndJenis
            // nested array and the separate array_keys() loop over it.
            /** @var array<string, mixed> $aggMap */
            $aggMap = $aggregates->keyBy(fn($agg) => $agg->kode_kab . '_' . $agg->jenis_akomodasi)->all();

            // Collect unique regency IDs without building an extra collection.
            $regencyIds = $aggregates->pluck('kode_kab')->unique()->all();

            $records = [];
            $now = now()->toDateTimeString();

            foreach ($regencyIds as $regencyId) {
                $agg1 = $aggMap[$regencyId . '_1'] ?? null; // Bintang
                $agg2 = $aggMap[$regencyId . '_2'] ?? null; // Non Bintang

                // TPK
                $records[] = $this->makeIndicatorRecord(
                    $regencyId,
                    $yearId,
                    $monthId,
                    $tpkId,
                    $bintangCatId,
                    $agg1 ? (int) $agg1->sum_mktj : null,
                    $agg1 ? (int) $agg1->sum_mkts : null,
                    $now
                );
                $records[] = $this->makeIndicatorRecord(
                    $regencyId,
                    $yearId,
                    $monthId,
                    $tpkId,
                    $nonBintangCatId,
                    $agg2 ? (int) $agg2->sum_mktj : null,
                    $agg2 ? (int) $agg2->sum_mkts : null,
                    $now
                );

                // RLMTA
                $records[] = $this->makeIndicatorRecord(
                    $regencyId,
                    $yearId,
                    $monthId,
                    $rlmtaId,
                    $bintangCatId,
                    $agg1 ? (int) $agg1->sum_mta : null,
                    $agg1 ? (int) $agg1->sum_ta : null,
                    $now
                );
                $records[] = $this->makeIndicatorRecord(
                    $regencyId,
                    $yearId,
                    $monthId,
                    $rlmtaId,
                    $nonBintangCatId,
                    $agg2 ? (int) $agg2->sum_mta : null,
                    $agg2 ? (int) $agg2->sum_ta : null,
                    $now
                );

                // RLMTN
                $records[] = $this->makeIndicatorRecord(
                    $regencyId,
                    $yearId,
                    $monthId,
                    $rlmtnId,
                    $bintangCatId,
                    $agg1 ? (int) $agg1->sum_mtnus : null,
                    $agg1 ? (int) $agg1->sum_tnus : null,
                    $now
                );
                $records[] = $this->makeIndicatorRecord(
                    $regencyId,
                    $yearId,
                    $monthId,
                    $rlmtnId,
                    $nonBintangCatId,
                    $agg2 ? (int) $agg2->sum_mtnus : null,
                    $agg2 ? (int) $agg2->sum_tnus : null,
                    $now
                );

                // GPR
                $records[] = $this->makeIndicatorRecord(
                    $regencyId,
                    $yearId,
                    $monthId,
                    $gprId,
                    $bintangCatId,
                    $agg1 ? (int) $agg1->sum_mtgab : null,
                    $agg1 ? (int) $agg1->sum_mktj : null,
                    $now
                );
                $records[] = $this->makeIndicatorRecord(
                    $regencyId,
                    $yearId,
                    $monthId,
                    $gprId,
                    $nonBintangCatId,
                    $agg2 ? (int) $agg2->sum_mtgab : null,
                    $agg2 ? (int) $agg2->sum_mktj : null,
                    $now
                );

                // TPTT
                $records[] = $this->makeIndicatorRecord(
                    $regencyId,
                    $yearId,
                    $monthId,
                    $tpttId,
                    $bintangCatId,
                    $agg1 ? (int) $agg1->sum_mtgab : null,
                    $agg1 ? (int) $agg1->sum_bed : null,
                    $now
                );
                $records[] = $this->makeIndicatorRecord(
                    $regencyId,
                    $yearId,
                    $monthId,
                    $tpttId,
                    $nonBintangCatId,
                    $agg2 ? (int) $agg2->sum_mtgab : null,
                    $agg2 ? (int) $agg2->sum_bed : null,
                    $now
                );
            }

            foreach (array_chunk($records, 500) as $chunk) {
                IndicatorValue::insert($chunk);
            }
        } catch (Throwable $e) {
            throw new ImportException(
                'Indicator calculation failed: ' . $e->getMessage(),
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
        ?int $numerator,
        ?int $denominator,
        string $now
    ): array {
        return [
            'id' => (string) Str::uuid(),
            'regency_id' => (int) $regencyId,
            'year_id' => $yearId,
            'month_id' => $monthId,
            'indicator_id' => $indicatorId,
            'category_id' => $categoryId,
            'numerator' => $numerator,
            'denominator' => $denominator,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * Populate the error_summaries table for the synced period.
     */
    private function summarizeErrors(SyncStatus $syncStatus): void
    {
        try {
            $yearId = $syncStatus->year_id;
            $monthId = $syncStatus->month_id;

            $hotelErrorId = ErrorModel::query()->where('code', 'Hotel')->value('id');
            $indikatorErrorId = ErrorModel::query()->where('code', 'Indikator')->value('id');
            $categories = Category::query()->pluck('id', 'code');
            $bintangCatId = (int) $categories->get('1');
            $nonBintangCatId = (int) $categories->get('2');

            $regencyIds = Input::query()
                ->where('tahun', $yearId)
                ->where('bulan', $monthId)
                ->distinct()
                ->pluck('kode_kab');

            if ($regencyIds->isEmpty()) {
                return;
            }

            $aggregates = Input::query()
                ->select([
                    'kode_kab as regency_id',
                    'jenis_akomodasi',
                    DB::raw('COUNT(CASE WHEN jumlah_error > 0 THEN 1 END) as hotel_error_count'),
                    DB::raw('SUM(jumlah_error) as indikator_error_sum'),
                ])
                ->where('tahun', $yearId)
                ->where('bulan', $monthId)
                ->whereIn('jenis_akomodasi', [1, 2])
                ->groupBy('kode_kab', 'jenis_akomodasi')
                ->get()
                ->keyBy(fn($row) => $row->regency_id . '_' . $row->jenis_akomodasi);

            ErrorSummary::query()
                ->where('month_id', $monthId)
                ->where('year_id', $yearId)
                ->delete();

            $now = now()->toDateTimeString();
            $records = [];

            foreach ($regencyIds as $regencyId) {
                foreach ([1 => $bintangCatId, 2 => $nonBintangCatId] as $jenis => $categoryId) {
                    $row = $aggregates->get($regencyId . '_' . $jenis);

                    $records[] = [
                        'id' => (string) Str::uuid(),
                        'regency_id' => (int) $regencyId,
                        'month_id' => $monthId,
                        'year_id' => $yearId,
                        'error_id' => $hotelErrorId,
                        'category_id' => $categoryId,
                        'value' => (int) ($row->hotel_error_count ?? 0),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $records[] = [
                        'id' => (string) Str::uuid(),
                        'regency_id' => (int) $regencyId,
                        'month_id' => $monthId,
                        'year_id' => $yearId,
                        'error_id' => $indikatorErrorId,
                        'category_id' => $categoryId,
                        'value' => (int) ($row->indikator_error_sum ?? 0),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            foreach (array_chunk($records, 500) as $chunk) {
                ErrorSummary::insert($chunk);
            }
        } catch (Throwable $e) {
            throw new ImportException(
                'Error summarization failed: ' . $e->getMessage(),
                'Summarizing error failed'
            );
        }
    }

    private function populateConfirmations(SyncStatus $syncStatus): void
    {
        try {
            $yearId = $syncStatus->year_id;
            $monthId = $syncStatus->month_id;

            // Get indicator IDs mapped by code
            $errorTypes = ErrorType::query()
                ->pluck('id', 'column_name');

            // Get all input IDs for this month/year
            $inputIds = Input::query()
                ->where('tahun', $yearId)
                ->where('bulan', $monthId)
                ->pluck('id');

            if ($inputIds->isEmpty()) {
                return;
            }

            // Step 1: Set is_active = false for all existing confirmations
            Confirmation::query()
                ->whereIn('input_id', $inputIds)
                ->update(['is_active' => false]);

            $errorColumns = $errorTypes->keys()->toArray();
            // Step 2: Get inputs with errors
            $inputsWithErrors = Input::query()
                ->select(array_merge(['id'], $errorColumns)) // include 'id' plus all error columns
                ->where('tahun', $yearId)
                ->where('bulan', $monthId)
                ->where(function ($query) use ($errorColumns) {
                    foreach ($errorColumns as $column) {
                        $query->orWhere($column, '>', 0);
                    }
                })
                ->get();

            if ($inputsWithErrors->isEmpty()) {
                return;
            }

            // Step 3: Collect all input_id + indicator_id combinations that should exist
            $requiredCombinations = [];
            foreach ($inputsWithErrors as $input) {
                foreach ($errorTypes as $errorColumn => $errorTypeId) {
                    if ($input->{$errorColumn} > 0) {
                        $requiredCombinations[] = [
                            'input_id' => $input->id,
                            'error_type_id' => $errorTypeId,
                        ];
                    }
                }
            }

            if (empty($requiredCombinations)) {
                return;
            }

            // Step 4: Get existing confirmations for these combinations
            $existingConfirmations = Confirmation::query()
                ->whereIn('input_id', $inputIds)
                ->get()
                ->keyBy(fn($c) => $c->input_id . '_' . $c->error_type_id);

            // Step 5: Prepare records to insert and update
            $recordsToInsert = [];
            $idsToActivate = [];
            $now = now()->toDateTimeString();

            foreach ($requiredCombinations as $combination) {
                $key = $combination['input_id'] . '_' . $combination['error_type_id'];

                if (isset($existingConfirmations[$key])) {
                    // Existing record - mark for activation
                    $idsToActivate[] = $existingConfirmations[$key]->id;
                } else {
                    // New record - prepare for insert
                    $recordsToInsert[] = [
                        'id' => (string) Str::uuid(),
                        'input_id' => $combination['input_id'],
                        'error_type_id' => $combination['error_type_id'],
                        'status' => 'not_confirmed',
                        'notes' => null,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            // Step 6: Activate existing records
            if (!empty($idsToActivate)) {
                Confirmation::query()
                    ->whereIn('id', $idsToActivate)
                    ->update(['is_active' => true, 'updated_at' => $now]);
            }

            // Step 7: Insert new records in batches
            if (!empty($recordsToInsert)) {
                foreach (array_chunk($recordsToInsert, 500) as $chunk) {
                    Confirmation::insert($chunk);
                }
            }
        } catch (Throwable $e) {
            throw new ImportException(
                'Confirmation population failed: ' . $e->getMessage(),
                'Populating confirmations failed'
            );
        }
    }

    /**
     * Populate the enumerations table for the synced period.
     */
    private function calculateEnumerations(SyncStatus $syncStatus): void
    {
        try {
            $yearId = $syncStatus->year_id;
            $monthId = $syncStatus->month_id;

            $categories = Category::query()->pluck('id', 'code');
            $bintangCatId = (int) $categories->get('1');
            $nonBintangCatId = (int) $categories->get('2');

            $regencyIds = Input::query()
                ->where('tahun', $yearId)
                ->where('bulan', $monthId)
                ->distinct()
                ->pluck('kode_kab');

            if ($regencyIds->isEmpty()) {
                return;
            }

            $aggregates = Input::query()
                ->select([
                    'kode_kab as regency_id',
                    'jenis_akomodasi',
                    DB::raw('COUNT(*) as count'),
                ])
                ->where('tahun', $yearId)
                ->where('bulan', $monthId)
                ->whereIn('jenis_akomodasi', [1, 2])
                ->groupBy('kode_kab', 'jenis_akomodasi')
                ->get()
                ->keyBy(fn($row) => $row->regency_id . '_' . $row->jenis_akomodasi);

            Enumeration::query()
                ->where('month_id', $monthId)
                ->where('year_id', $yearId)
                ->delete();

            $now = now()->toDateTimeString();
            $records = [];

            foreach ($regencyIds as $regencyId) {
                foreach ([1 => $bintangCatId, 2 => $nonBintangCatId] as $jenis => $categoryId) {
                    $row = $aggregates->get($regencyId . '_' . $jenis);

                    $records[] = [
                        'id' => (string) Str::uuid(),
                        'regency_id' => (int) $regencyId,
                        'month_id' => $monthId,
                        'year_id' => $yearId,
                        'category_id' => $categoryId,
                        'value' => (int) ($row->count ?? 0),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            foreach (array_chunk($records, 500) as $chunk) {
                Enumeration::insert($chunk);
            }
        } catch (Throwable $e) {
            throw new ImportException(
                'Progress calculation failed: ' . $e->getMessage(),
                'Progress calculation failed'
            );
        }
    }
}
