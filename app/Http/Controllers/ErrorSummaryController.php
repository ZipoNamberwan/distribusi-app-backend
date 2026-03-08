<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Error;
use App\Models\ErrorSummary;
use App\Models\Month;
use App\Models\Year;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ErrorSummaryController extends Controller
{
    public function showErrorSummaryPage(): Response
    {
        $months = Month::all();
        $years = Year::all();
        $categories = Category::whereNotNull('code')->orderBy('id')->get();
        $errors = Error::whereNotNull('code')->orderBy('id')->get();

        $latestPeriod = ErrorSummary::query()
            ->selectRaw('year_id, month_id')
            ->orderByDesc('year_id')
            ->orderByDesc('month_id')
            ->first();

        $defaultMonth = $latestPeriod?->month_id;
        $defaultYear = $latestPeriod?->year_id;

        return Inertia::render('error_summaries/Index', [
            'months' => $months,
            'years' => $years,
            'categories' => $categories,
            'errors' => $errors,
            'defaultMonth' => $defaultMonth,
            'defaultYear' => $defaultYear,
        ]);
    }

    public function getErrorSummaryData(Request $request): JsonResponse
    {
        $summaries = ErrorSummary::query()
            ->with('regency')
            ->when($request->month, fn ($q) => $q->where('month_id', $request->month))
            ->when($request->year, fn ($q) => $q->where('year_id', $request->year))
            ->orderBy('regency_id')
            ->get();

        $data = $summaries
            ->groupBy('regency_id')
            ->map(function ($items) {
                return [
                    'regency' => $items->first()->regency,
                    'values' => $items->mapWithKeys(fn ($item) => [
                        $item->error_id.'_'.$item->category_id => $item->value,
                    ]),
                ];
            })
            ->values();

        return response()->json([
            'data' => $data,
            'total' => $data->count(),
        ]);
    }
}
