<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Enumeration;
use App\Models\ErrorSummary;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Models\Year;
use Illuminate\Http\Request;
use App\Models\Input;
use App\Models\Month;
use App\Models\Regency;
use App\Models\SampleTarget;
use App\Models\SyncStatus;
use App\Models\User;
use DateTime;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\JsonResponse;

class DataController extends Controller
{

    public function showDashboard()
    {
        $user = User::find(Auth::id());

        // ================= LATEST PERIOD =================
        $latestPeriod = IndicatorValue::query()
            ->select('year_id', 'month_id')
            ->where('regency_id', $user->regency_id)
            ->where('indicator_id', 1)
            ->orderByDesc('year_id')
            ->orderByDesc('month_id')
            ->first();

        // If no data → return default
        if (! $latestPeriod) {
            return Inertia::render('Dashboard', [
                'tpk' => 0,
                'enumeration' => 0,
                'error' => 0,
                'period' => null,
            ]);
        }

        // ================= PERIOD =================
        $latestMonth = Month::find($latestPeriod->month_id);
        $latestYear = Year::find($latestPeriod->year_id);

        // ================= TPK =================
        $tpkAggregate = IndicatorValue::query()
            ->selectRaw('SUM(numerator) as total_numerator, SUM(denominator) as total_denominator')
            ->where('year_id', $latestPeriod->year_id)
            ->where('month_id', $latestPeriod->month_id)
            ->where('regency_id', $user->regency_id)
            ->where('indicator_id', 1)
            ->first();

        $totalNumerator = $tpkAggregate?->total_numerator ?? 0;
        $totalDenominator = $tpkAggregate?->total_denominator ?? 0;

        $tpk = $totalDenominator != 0
            ? round(($totalNumerator / $totalDenominator) * 100, 2)
            : 0;

        // ================= ENUMERATION =================
        $enumerationData = Enumeration::query()
            ->selectRaw('SUM(value) as total_value')
            ->where('year_id', $latestPeriod->year_id)
            ->where('month_id', $latestPeriod->month_id)
            ->where('regency_id', $user->regency_id)
            ->first();

        $totalEnumeration = $enumerationData?->total_value ?? 0;

        // ================= SAMPLE TARGET (original fallback logic) =================
        $query = SampleTarget::query()
            ->selectRaw('SUM(value) as total_value')
            ->where('year_id', $latestPeriod->year_id)
            ->where('regency_id', $user->regency_id);

        // Try month first
        $sampleData = (clone $query)
            ->where('month_id', $latestPeriod->month_id)
            ->first();

        // If NULL result → fallback to year only
        if (!$sampleData || $sampleData->total_value === null) {
            $sampleData = $query->first();
        }

        $sampleValue = $sampleData?->total_value ?? 0;

        // ================= PERCENTAGE =================
        $percentage = $sampleValue != 0
            ? round(($totalEnumeration / $sampleValue) * 100, 2)
            : 0;

        // ================= ERROR =================
        $errorData = ErrorSummary::query()
            ->selectRaw('SUM(value) as total_value')
            ->where('year_id', $latestPeriod->year_id)
            ->where('month_id', $latestPeriod->month_id)
            ->where('regency_id', $user->regency_id)
            ->where('error_id', 2)
            ->first();

        $errorTotal = $errorData?->total_value ?? 0;

        // Card stop here


        // Map start here

        // ================= ENUMERATION =================
        $enumerations = Enumeration::query()
            ->selectRaw('regency_id, SUM(value) as total_value')
            ->where('year_id', $latestPeriod->year_id)
            ->where('month_id', $latestPeriod->month_id)
            ->groupBy('regency_id')
            ->get()
            ->keyBy('regency_id');

        // ================= REGENCIES =================
        $regencies = Regency::query()
            ->select('id', 'name', 'short_code', 'long_code')
            ->get();

        // ================= CALCULATE =================
        $result = $regencies->mapWithKeys(function ($regency) use ($enumerations, $latestPeriod) {

            $regencyId = $regency->id;

            // ================= ENUMERATION =================
            $totalEnumeration = $enumerations[$regencyId]->total_value ?? 0;

            // ================= SAMPLE TARGET (SUM + FALLBACK FIX) =================
            $baseQuery = SampleTarget::query()
                ->selectRaw('SUM(value) as total_value')
                ->where('year_id', $latestPeriod->year_id)
                ->where('regency_id', $regencyId);

            // Try month first
            $sampleData = (clone $baseQuery)
                ->where('month_id', $latestPeriod->month_id)
                ->first();

            // IMPORTANT: check VALUE, not object
            if (!$sampleData || $sampleData->total_value === null) {
                $sampleData = $baseQuery->first();
            }

            $sampleValue = $sampleData->total_value ?? 0;

            // ================= PERCENTAGE =================
            $percentage = $sampleValue != 0
                ? round(($totalEnumeration / $sampleValue) * 100, 2)
                : 0;

            return [
                $regencyId => [
                    'regency' => [
                        'id' => $regency->id,
                        'name' => $regency->name,
                        'short_code' => $regency->short_code,
                        'long_code' => $regency->long_code,
                    ],
                    'value' => $percentage,
                ]
            ];
        });

        // Map end here

        // ================= RESPONSE =================
        return Inertia::render('Dashboard', [
            'tpk' => $tpk,
            'enumeration' => $percentage,
            'error' => $errorTotal,
            'period' => ($latestMonth && $latestYear)
                ? [
                    'month' => $latestMonth,
                    'year' => $latestYear,
                ]
                : null,

            'mapData' => $result,
            'selectedRegency' => Regency::find($user->regency_id),
        ]);
    }

    public function showMap()
    {
        $path = "geojson/prov.geojson";

        if (!Storage::disk('local')->exists($path)) {
            return response()->json([
                'message' => 'GeoJSON file not found'
            ], 404);
        }

        $content = Storage::disk('local')->get($path);

        return response($content, 200);
    }

    public function showRawDataPage()
    {
        $user = User::find(Auth::id());

        $regencies = [];
        if ($user->hasRole('adminkab')) {
            $regencies = Regency::where('id', $user->regency_id)->get();
        } else {
            $regencies = Regency::all();
        }

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
        $user = User::find(Auth::id());

        $records = Input::with(['month', 'year', 'user', 'regency', 'syncStatus']);

        if ($user->hasRole('adminkab')) {
            $records->where('kode_kab', $user->regency_id);
        }

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

        if ($request->input('error_filter')) {
            if ($request->input('error_filter') == 'has_error') {
                $records->where('jumlah_error', '>', 0);
            } else if ($request->input('error_filter') == 'no_error') {
                $records->where('jumlah_error', 0);
            }
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
        $years = Year::where('name', '<=', (string) now()->year)->get();
        $categories = Category::whereNotNull('code')->orderBy('id')->get();
        $indicators = Indicator::orderBy('id')->get()->map(fn($ind) => array_merge(
            $ind->toArray(),
            ['categories' => $categories->toArray()]
        ));

        $date = new DateTime('first day of last month');

        $year = Year::where('code', $date->format('Y'))->first();
        $month = Month::where('code', $date->format('m'))->first();

        $regencies = Regency::all();

        return Inertia::render('indicator_values/Index', [
            'months' => $months,
            'years' => $years,
            'indicators' => $indicators->values(),
            'initialPeriod' => ['month' => $month, 'year' => $year],
            'regencies' => $regencies,
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

        if ($request->input('year') == null || $request->input('month') == null) {
            return response()->json(['data' => [], 'total' => 0]);
        }

        $year = Year::find($request->input('year'));
        $month = Month::find($request->input('month'));

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

        return response()->json([
            'data' => $data,
            'total' => $total,
            'period' => ['month' => $month, 'year' => $year]
        ]);
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
