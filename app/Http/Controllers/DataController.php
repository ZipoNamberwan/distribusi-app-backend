<?php

namespace App\Http\Controllers;

use App\Services\GoogleSheetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

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
