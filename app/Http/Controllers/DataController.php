<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Input;
use App\Models\Tabulation;
use App\Services\GoogleSheetService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReader;

class DataController extends Controller
{
    public function index(Request $request, GoogleSheetService $sheet): Response
    {
        $dataType = $request->string('data_type')->trim()->toString();

        $values = $this->readSheet($sheet)->getData(true);

        $headers = $this->headersFromSheetValues($values);
        $rows = $this->rowsFromSheetValues($values, $headers);

        $dataTypeColumn = $this->resolveDataTypeColumn($headers);
        $dataTypeOptions = $dataTypeColumn === null
            ? []
            : collect($rows)
                ->map(fn (array $row) => (string) Arr::get($row, $dataTypeColumn, ''))
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all();

        if ($dataType !== '' && $dataTypeColumn !== null) {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row) => (string) ($row[$dataTypeColumn] ?? '') === $dataType
            ));
        }

        return Inertia::render('data/Index', [
            'headers' => $headers,
            'rows' => $rows,
            'dataTypeOptions' => $dataTypeOptions,
            'filters' => [
                'data_type' => $dataType !== '' ? $dataType : null,
            ],
        ]);
    }

    public function readSheet(GoogleSheetService $sheet): JsonResponse
    {
        $spreadsheetId = (string) config('services.google_sheets.spreadsheet_id', '');
        $range = (string) config('services.google_sheets.range', 'INPUT!A1:AE100');

        $spreadsheetId = $this->normalizeSpreadsheetId($spreadsheetId);
        $range = trim($range) !== '' ? trim($range) : 'INPUT!A1:AE500';

        if ($spreadsheetId === '') {
            return response()->json([]);
        }

        $data = $sheet->read($spreadsheetId, $range);

        return response()->json($data);
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
     * @return array<int, array<int, mixed>>
     */
    private function readUploadValues(string|false $path): array
    {
        if ($path === false || trim($path) === '') {
            throw ValidationException::withMessages([
                'file' => 'Unable to read uploaded file.',
            ]);
        }

        $reader = $this->createSpreadsheetReader($path);
        $reader->setReadDataOnly(true);

        $spreadsheet = $reader->load($path);

        try {
            /**
             * Numeric-indexed rows, like Google Sheets values.
             *
             * @var array<int, array<int, mixed>> $values
             */
            $values = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

            return $values;
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    private function createSpreadsheetReader(string $path): IReader
    {
        try {
            return IOFactory::createReaderForFile($path);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'file' => 'Unsupported file type. Please upload a .xlsx or .csv file.',
            ]);
        }
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
        $columnC = $headers[2] ?? null;

        if ($columnC === null) {
            return $rows;
        }

        return array_values(array_filter($rows, function (array $row) use ($columnC) {
            $value = $row[$columnC] ?? null;

            return $value !== null && trim($value) !== '';
        }));
    }

    /**
     * @param  array<int, array<string, string|null>>  $rows
     * @param  array<int, string>  $headers
     */
    private function importInputRows(array $rows, array $headers): void
    {
        Input::query()->truncate();

        $requiredField = 'kode_kab';

        $insertBatchSize = 500;
        $mappedRows = [];

        foreach ($rows as $row) {
            $mappedRow = $this->mapInputRow($row, $headers);

            if ($mappedRow[$requiredField] === null || trim((string) $mappedRow[$requiredField]) === '') {
                $rowNumber = $row['__row'] ?? null;

                throw ValidationException::withMessages([
                    'file' => $rowNumber === null
                        ? 'Missing required column value: kode_kab.'
                        : "Missing required column value: kode_kab (row {$rowNumber}).",
                ]);
            }

            $mappedRow['id'] = (string) Str::uuid();
            $mappedRow['created_at'] = now();
            $mappedRow['updated_at'] = now();

            $mappedRows[] = $mappedRow;

            if (count($mappedRows) >= $insertBatchSize) {
                Input::insert($mappedRows);
                $mappedRows = [];
            }
        }

        if ($mappedRows !== []) {
            Input::insert($mappedRows);
        }
    }

    /**
     * @param  array<int, array<string, string|null>>  $rows
     * @param  array<int, string>  $headers
     */
    private function importTabulationRows(array $rows, array $headers): void
    {
        Tabulation::query()->truncate();

        $insertBatchSize = 500;
        $mappedRows = [];

        foreach ($rows as $row) {
            $mappedRow = $this->mapTabulationRow($row, $headers);
            $mappedRow['created_at'] = now();
            $mappedRow['updated_at'] = now();

            $mappedRows[] = $mappedRow;

            if (count($mappedRows) >= $insertBatchSize) {
                Tabulation::insert($mappedRows);
                $mappedRows = [];
            }
        }

        if ($mappedRows !== []) {
            Tabulation::insert($mappedRows);
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
            'kode_kab' => $this->getValue($row, ['kode_kab', 'kode kab']) ?: null,
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
     * @param  array<string, string|null>  $row
     * @param  array<int, string>  $possibleKeys
     */
    private function getValue(array $row, array $possibleKeys): ?string
    {
        foreach ($possibleKeys as $key) {
            if (isset($row[$key])) {
                return $row[$key];
            }

            foreach (array_keys($row) as $rowKey) {
                if (Str::lower($rowKey) === Str::lower($key)) {
                    return $row[$rowKey];
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $headers
     */
    private function resolveDataTypeColumn(array $headers): ?string
    {
        foreach ($headers as $header) {
            if (Str::of($header)->lower()->snake()->toString() === 'data_type') {
                return $header;
            }
        }

        return null;
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
