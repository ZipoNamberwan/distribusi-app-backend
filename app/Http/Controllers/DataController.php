<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Indicator;
use App\Models\Year;
use Illuminate\Http\Request;
use App\Models\Input;
use App\Models\Month;
use App\Models\SyncStatus;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\JsonResponse;

class DataController extends Controller
{
    public function getInputData(Request $request)
    {
        $records = null;

        $records = Input::with(['month', 'year', 'user', 'regency', 'syncStatus']);

        if ($request->month) {
            $records->where('bulan', $request->month);
        }
        if ($request->year) {
            $records->where('tahun', $request->year);
        }

        if ($request->status) {
            $records->whereIn('status', $request->status);
        }

        if ($request->regency) {
            $records->whereIn('kode_kab', $request->regency);
        }

        if ($request->nama_komersial) {
            $search = is_array($request->nama_komersial) ? $request->nama_komersial[0] : $request->nama_komersial;
            $records->where('nama_komersial', 'like', '%' . $search . '%');
        }

        if ($request->alamat) {
            $search = is_array($request->alamat) ? $request->alamat[0] : $request->alamat;
            $records->where('alamat', 'like', '%' . $search . '%');
        }

        if ($request->kode_kec) {
            $search = is_array($request->kode_kec) ? $request->kode_kec[0] : $request->kode_kec;
            $records->where('kode_kec', 'like', '%' . $search . '%');
        }

        if ($request->kode_desa) {
            $search = is_array($request->kode_desa) ? $request->kode_desa[0] : $request->kode_desa;
            $records->where('kode_desa', 'like', '%' . $search . '%');
        }

        if ($request->status) {
            $search = is_array($request->status) ? $request->status[0] : $request->status;
            $records->where('status', 'like', '%' . $search . '%');
        }

        $orderColumn = 'created_at';
        $orderDir = 'desc';

        if (!empty($request->sortOrder) && ! empty($request->sortField)) {
            $orderColumn = $request->sortField;
            if ($request->sortField == 'regency') {
                $orderColumn = 'kode_kab';
            }
            $direction = $request->sortOrder === 'ascend' ? 'asc' : 'desc';
            $orderDir = $direction;
        }

        $recordsTotal = $records->count();

        // Pagination
        if ($request->length != -1) {
            $records->skip($request->start)
                ->take($request->length);
        }

        // Order
        $records->orderBy($orderColumn, $orderDir);

        $data = $records->get();

        return response()->json([
            'total' => $recordsTotal,
            'data' => $data,
        ]);
    }

    public function showIndicatorValues()
    {
        $months = Month::all();
        $years = Year::all();
        $categories = Category::whereNotNull('code')->orderBy('id')->get();
        $indicators = Indicator::orderBy('id')->get()->map(fn($ind) => array_merge(
            $ind->toArray(),
            ['categories' => $categories->toArray()]
        ));

        $latestPeriod = DB::table('indicator_values as iv')
            ->join('years as y', 'y.id', '=', 'iv.year_id')
            ->join('months as m', 'm.id', '=', 'iv.month_id')
            ->orderByDesc('y.name')
            ->orderByDesc('m.id')
            ->select('iv.month_id', 'iv.year_id')
            ->first();

        return Inertia::render('indicator_values/Index', [
            'months'       => $months,
            'years'        => $years,
            'indicators'   => $indicators->values(),
            'defaultMonth' => $latestPeriod?->month_id,
            'defaultYear'  => $latestPeriod?->year_id,
        ]);
    }

    public function getIndicatorValuesData(Request $request): JsonResponse
    {
        $query = DB::table('indicator_values as iv')
            ->join('regencies as r', 'r.id', '=', 'iv.regency_id')
            ->select(
                'r.id as regency_id',
                'r.name as regency_name',
                'r.long_code as regency_long_code',
                'iv.indicator_id',
                'iv.category_id',
                'iv.numerator',
                'iv.denominator',
            );

        if ($request->month) {
            $query->where('iv.month_id', $request->month);
        }

        if ($request->year) {
            $query->where('iv.year_id', $request->year);
        }

        $orderDir = (! empty($request->sortOrder) && $request->sortOrder === 'descend') ? 'desc' : 'asc';

        $query->orderBy('r.long_code', $orderDir);

        $rows = $query->get();

        $total = $rows->unique('regency_id')->count();

        $data = $rows->groupBy('regency_id')->map(function ($regencyRows) {
            $first = $regencyRows->first();

            $values = $regencyRows->mapWithKeys(fn($row) => [
                "{$row->indicator_id}_{$row->category_id}" => [
                    'num' => $row->numerator,
                    'den' => $row->denominator,
                ],
            ]);

            return [
                'regency' => [
                    'id'        => $first->regency_id,
                    'name'      => $first->regency_name,
                    'long_code' => $first->regency_long_code,
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

        if ($request->status) {
            $records->whereIn('status', $request->status);
        }

        $orderColumn = 'created_at';
        $orderDir = 'desc';

        if (! empty($request->sortOrder) && ! empty($request->sortField)) {
            $orderColumn = $request->sortField;
            $direction = $request->sortOrder === 'ascend' ? 'asc' : 'desc';
            $orderDir = $direction;
        }

        $recordsTotal = $records->count();

        // Pagination
        if ($request->length != -1) {
            $records->skip($request->start)
                ->take($request->length);
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
