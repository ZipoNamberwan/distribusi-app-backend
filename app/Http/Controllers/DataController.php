<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Models\Year;
use Illuminate\Http\Request;
use App\Models\Input;
use App\Models\Month;
use App\Models\Regency;
use App\Models\SyncStatus;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\JsonResponse;

class DataController extends Controller
{
    public function showRawDataPage()
    {
        $regencies = Regency::all();
        $months = Month::all();
        $years = Year::all();
        return Inertia::render('data/Index', [
            'regencies' => $regencies,
            'months' => $months,
            'years' => $years,
        ]);
    }
    public function getInputData(Request $request)
    {
        $records = null;

        $records = Input::with(['month', 'year', 'user', 'regency', 'syncStatus']);

        if ($request->input('month')) {
            $records->where('bulan', $request->input('month'));
        }
        if ($request->input('year')) {
            $records->where('tahun', $request->input('year'));
        }

        if ($request->input('status')) {
            $records->whereIn('status', $request->input('status'));
        }

        if ($request->input('regency')) {
            $records->whereIn('kode_kab', $request->input('regency'));
        }

        if ($request->input('nama_komersial')) {
            $search = is_array($request->input('nama_komersial')) ? $request->input('nama_komersial')[0] : $request->input('nama_komersial');
            $records->where('nama_komersial', 'like', '%' . $search . '%');
        }

        if ($request->input('alamat')) {
            $search = is_array($request->input('alamat')) ? $request->input('alamat')[0] : $request->input('alamat');
            $records->where('alamat', 'like', '%' . $search . '%');
        }

        if ($request->input('kode_kec')) {
            $search = is_array($request->input('kode_kec')) ? $request->input('kode_kec')[0] : $request->input('kode_kec');
            $records->where('kode_kec', 'like', '%' . $search . '%');
        }

        if ($request->input('kode_desa')) {
            $search = is_array($request->input('kode_desa')) ? $request->input('kode_desa')[0] : $request->input('kode_desa');
            $records->where('kode_desa', 'like', '%' . $search . '%');
        }

        if ($request->input('status')) {
            $search = is_array($request->input('status')) ? $request->input('status')[0] : $request->input('status');
            $records->where('status', 'like', '%' . $search . '%');
        }

        $orderColumn = 'created_at';
        $orderDir = 'desc';

        if (!empty($request->input('sortOrder')) && ! empty($request->input('sortField'))) {
            $orderColumn = $request->input('sortField');
            if ($request->input('sortField') == 'regency') {
                $orderColumn = 'kode_kab';
            }
            $direction = $request->input('sortOrder') === 'ascend' ? 'asc' : 'desc';
            $orderDir = $direction;
        }

        $recordsTotal = $records->count();

        // Pagination
        if ($request->input('length') != -1) {
            $records->skip($request->input('start'))
                ->take($request->input('length'));
        }

        // Order
        $records->orderBy($orderColumn, $orderDir);

        $data = $records->get();

        return response()->json([
            'total' => $recordsTotal,
            'data' => $data,
        ]);
    }

    public function showIndicatorValuesPage()
    {
        $months = Month::all();
        $years = Year::all();
        $categories = Category::whereNotNull('code')->orderBy('id')->get();
        $indicators = Indicator::orderBy('id')->get()->map(fn($ind) => array_merge(
            $ind->toArray(),
            ['categories' => $categories->toArray()]
        ));

        $latestValue = IndicatorValue::with(['year', 'month'])
            ->orderByDesc('year_id')
            ->orderByDesc('month_id')
            ->first();

        return Inertia::render('indicator_values/Index', [
            'months'       => $months,
            'years'        => $years,
            'indicators'   => $indicators->values(),
            'defaultMonth' => $latestValue?->month_id,
            'defaultYear'  => $latestValue?->year_id,
        ]);
    }

    public function getIndicatorValuesData(Request $request): JsonResponse
    {
        $query = IndicatorValue::query()
            ->with(['regency', 'indicator', 'category']);

        if ($request->input('month') !== null) {
            $query->where('month_id', $request->input('month'));
        }

        if ($request->input('year') !== null) {
            $query->where('year_id', $request->input('year'));
        }

        $orderDir = (! empty($request->input('sortOrder')) && $request->input('sortOrder') === 'descend') ? 'desc' : 'asc';

        $rows = $query->get()->sortBy(fn($row) => $row->regency->long_code, $orderDir === 'desc');

        $total = $rows->unique(fn($row) => $row->regency->id)->count();

        $data = $rows->groupBy(fn($row) => $row->regency->id)->map(function ($regencyRows) {
            $first = $regencyRows->first();
            $values = $regencyRows->mapWithKeys(fn($row) => [
                "{$row->indicator_id}_{$row->category_id}" => [
                    'num' => $row->numerator,
                    'den' => $row->denominator,
                ],
            ]);
            return [
                'regency' => [
                    'id'        => $first->regency->id,
                    'name'      => $first->regency->name,
                    'long_code' => $first->regency->long_code,
                ],
                'values' => $values,
            ];
        })->values();

        return response()->json(['data' => $data, 'total' => $total]);
    }

    public function getUploadStatusData($type, Request $request): JsonResponse
    {
        $records = null;

        $records = SyncStatus::with(['month', 'year'])->where('type', $type ?? 'input');

        if ($request->input('status')) {
            $records->whereIn('status', $request->input('status'));
        }

        $orderColumn = 'created_at';
        $orderDir = 'desc';

        if (! empty($request->input('sortOrder')) && ! empty($request->input('sortField'))) {
            $orderColumn = $request->input('sortField');
            $direction = $request->input('sortOrder') === 'ascend' ? 'asc' : 'desc';
            $orderDir = $direction;
        }

        $recordsTotal = $records->count();

        // Pagination
        if ($request->input('length') != -1) {
            $records->skip($request->input('start'))
                ->take($request->input('length'));
        }

        // Order
        $records->orderBy($orderColumn, $orderDir);

        $data = $records->get();

        return response()->json([
            'total' => $recordsTotal,
            'data' => $data,
        ]);
    }
}
