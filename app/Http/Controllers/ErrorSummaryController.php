<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Error;
use App\Models\ErrorSummary;
use App\Models\Month;
use App\Models\Regency;
use App\Models\Year;
use DateTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ErrorSummaryController extends Controller
{
    public function showErrorSummaryPage(): Response
    {
        $months = Month::all();
        $years = Year::where('name', '<=', (string) now()->year)->get();
        $categories = Category::whereNotNull('code')->orderBy('id')->get();
        $errors = Error::whereNotNull('code')->orderBy('id')->get();

        $date = new DateTime('first day of last month');

        $year = Year::where('code', $date->format('Y'))->first();
        $month = Month::where('code', $date->format('m'))->first();

        $regencies = Regency::all();

        return Inertia::render('error_summaries/Index', [
            'months' => $months,
            'years' => $years,
            'categories' => $categories,
            'errors' => $errors,
            'initialPeriod' => ['month' => $month, 'year' => $year],
            'regencies' => $regencies,
        ]);
    }

    public function getErrorSummaryData(Request $request): JsonResponse
    {
        if ($request->input('year') == null || $request->input('month') == null) {
            return response()->json(['data' => [], 'total' => 0]);
        }

        $summaries = ErrorSummary::query()
            ->with('regency')
            ->when($request->month, fn($q) => $q->where('month_id', $request->month))
            ->when($request->year, fn($q) => $q->where('year_id', $request->year))
            ->orderBy('regency_id')
            ->get();

        $year = Year::find($request->input('year'));
        $month = Month::find($request->input('month'));

        $data = $summaries
            ->groupBy('regency_id')
            ->map(function ($items) {
                return [
                    'regency' => $items->first()->regency,
                    'values' => $items->mapWithKeys(fn($item) => [
                        $item->error_id . '_' . $item->category_id => $item->value,
                    ]),
                ];
            })
            ->values();

        return response()->json([
            'data' => $data,
            'total' => $data->count(),
            'period' => ['month' => $month, 'year' => $year]
        ]);
    }
}
