<?php

namespace App\Http\Controllers;

use App\Models\Indicator;
use App\Models\Year;
use Illuminate\Http\Request;
use App\Models\Input;
use App\Models\Month;
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
        $indicators = Indicator::all();

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
            'indicators'   => $indicators,
            'defaultMonth' => $latestPeriod?->month_id,
            'defaultYear'  => $latestPeriod?->year_id,
        ]);
    }

    public function getIndicatorValuesData(Request $request): JsonResponse
    {
        $base = DB::table('indicator_values as iv')
            ->join('regencies as r', 'r.id', '=', 'iv.regency_id')
            ->join('indicators as i', 'i.id', '=', 'iv.indicator_id')
            ->join('categories as c', 'c.id', '=', 'iv.category_id');

        if ($request->month) {
            $base->where('iv.month_id', $request->month);
        }

        if ($request->year) {
            $base->where('iv.year_id', $request->year);
        }

        $total = (clone $base)->distinct('iv.regency_id')->count('iv.regency_id');

        $orderColumn = 'r.long_code';
        $orderDir = 'asc';

        if (! empty($request->sortField) && ! empty($request->sortOrder)) {
            if ($request->sortField === 'regency') {
                $orderColumn = 'r.long_code';
            }
            $orderDir = $request->sortOrder === 'ascend' ? 'asc' : 'desc';
        }

        $query = (clone $base)
            ->selectRaw("
                r.id        AS regency_id,
                r.long_code AS regency_long_code,
                r.name      AS regency_name,

                MAX(CASE WHEN i.code = 'TPK'   AND c.code = '1'    THEN iv.value END) AS tpk_bintang,
                MAX(CASE WHEN i.code = 'TPK'   AND c.code = '2'    THEN iv.value END) AS tpk_non_bintang,
                MAX(CASE WHEN i.code = 'TPK'   AND c.code IS NULL  THEN iv.value END) AS tpk_total,

                MAX(CASE WHEN i.code = 'RLMTA' AND c.code = '1'    THEN iv.value END) AS rlmta_bintang,
                MAX(CASE WHEN i.code = 'RLMTA' AND c.code = '2'    THEN iv.value END) AS rlmta_non_bintang,
                MAX(CASE WHEN i.code = 'RLMTA' AND c.code IS NULL  THEN iv.value END) AS rlmta_total,

                MAX(CASE WHEN i.code = 'RLMTN' AND c.code = '1'    THEN iv.value END) AS rlmtn_bintang,
                MAX(CASE WHEN i.code = 'RLMTN' AND c.code = '2'    THEN iv.value END) AS rlmtn_non_bintang,
                MAX(CASE WHEN i.code = 'RLMTN' AND c.code IS NULL  THEN iv.value END) AS rlmtn_total,

                MAX(CASE WHEN i.code = 'GPR'   AND c.code = '1'    THEN iv.value END) AS gpr_bintang,
                MAX(CASE WHEN i.code = 'GPR'   AND c.code = '2'    THEN iv.value END) AS gpr_non_bintang,
                MAX(CASE WHEN i.code = 'GPR'   AND c.code IS NULL  THEN iv.value END) AS gpr_total,

                MAX(CASE WHEN i.code = 'TPTT'  AND c.code = '1'    THEN iv.value END) AS tptt_bintang,
                MAX(CASE WHEN i.code = 'TPTT'  AND c.code = '2'    THEN iv.value END) AS tptt_non_bintang,
                MAX(CASE WHEN i.code = 'TPTT'  AND c.code IS NULL  THEN iv.value END) AS tptt_total
            ")
            ->groupBy('r.id', 'r.long_code', 'r.name')
            ->orderBy($orderColumn, $orderDir);

        if ($request->length && (int) $request->length !== -1) {
            $query->skip((int) ($request->start ?? 0))->take((int) $request->length);
        }

        $offset = (int) ($request->start ?? 0);

        $data = $query->get()->map(function ($row, $index) use ($offset) {
            $arr = (array) $row;
            $arr['regency'] = '['.($arr['regency_long_code'] ?? '').'] '.($arr['regency_name'] ?? '');

            return array_merge(['no' => $offset + $index + 1], $arr);
        })->values();

        return response()->json(['data' => $data, 'total' => $total]);
    }
}
