<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class DataController extends Controller
{
    public function index(Request $request): Response
    {
        $dataType = $request->string('data_type')->trim()->toString();

        $rows = [
            [
                'id' => 1,
                'code' => '3201',
                'name' => 'Bogor',
                'value' => '10',
                'data_type' => 'populasi',
            ],
            [
                'id' => 2,
                'code' => '3202',
                'name' => 'Sukabumi',
                'value' => '20',
                'data_type' => 'populasi',
            ],
            [
                'id' => 3,
                'code' => '3203',
                'name' => 'Cianjur',
                'value' => '30',
                'data_type' => 'luas',
            ],
            [
                'id' => 4,
                'code' => '3204',
                'name' => 'Bandung',
                'value' => '40',
                'data_type' => 'luas',
            ],
        ];

        $dataTypeOptions = collect($rows)
            ->map(fn (array $row) => (string) Arr::get($row, 'data_type', ''))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($dataType !== '') {
            $rows = array_values(array_filter($rows, static fn (array $row) => ($row['data_type'] ?? '') === $dataType));
        }

        return Inertia::render('data/Index', [
            'rows' => $rows,
            'dataTypeOptions' => $dataTypeOptions,
            'filters' => [
                'data_type' => $dataType !== '' ? $dataType : null,
            ],
        ]);
    }
}
