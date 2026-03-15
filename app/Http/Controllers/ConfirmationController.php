<?php

namespace App\Http\Controllers;

use App\Models\Confirmation;
use App\Models\Month;
use App\Models\Year;
use App\Models\Regency;
use App\Models\ErrorType;
use DateTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ConfirmationController extends Controller
{
    public function showConfirmationPage()
    {
        $months = Month::all();
        $years = Year::where('name', '<=', (string) now()->year)->get();
        $regencies = Regency::all();
        $errorTypes = ErrorType::all();
        $statuses = ['not_confirmed', 'confirmed', 'approved'];

        $date = new DateTime('first day of last month');
        $year = Year::where('code', $date->format('Y'))->first();
        $month = Month::where('code', $date->format('m'))->first();

        return Inertia::render('confirmation/Index', [
            'months' => $months,
            'years' => $years,
            'regencies' => $regencies,
            'errorTypes' => $errorTypes,
            'statuses' => $statuses,
            'initialPeriod' => ['month' => $month, 'year' => $year],
        ]);
    }

    public function getConfirmationData(Request $request): JsonResponse
    {
        if ($request->input('year') == null || $request->input('month') == null) {
            return response()->json(['data' => [], 'total' => 0]);
        }

        $records = Confirmation::with([
            'input.regency',
            'input.month',
            'input.year',
            'errorType',
            'sentBy',
            'approvedBy'
        ])->where('is_active', true);

        // Filter by input month
        if ($request->input('month') !== null) {
            $records->whereHas('input', function ($query) use ($request) {
                $query->where('bulan', $request->input('month')); // or 'month_id' if you named it that
            });
        }

        // Filter by input year
        if ($request->input('year') !== null) {
            $records->whereHas('input', function ($query) use ($request) {
                $query->where('tahun', $request->input('year')); // or 'year_id' if you named it that
            });
        }

        // Filter by multiple regencies
        if ($request->filled('regencies') && count($request->input('regencies')) > 0) {
            $records->whereHas('input', function ($query) use ($request) {
                $query->whereIn('kode_kab', $request->input('regencies'));
            });
        }

        // Filter by status
        if ($request->input('status') !== null) {
            $records->where('status', $request->input('status'));
        }

        // Filter by error type
        if ($request->input('errorType') !== null) {
            $records->where('error_type_id', $request->input('errorType'));
        }

        $year = Year::find($request->input('year'));
        $month = Month::find($request->input('month'));

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
            'period' => ['month' => $month, 'year' => $year]
        ]);
    }
}
