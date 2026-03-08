<?php

namespace App\Http\Controllers;

use App\Models\Year;
use App\Models\Month;
use App\Models\Category;
use App\Models\Enumeration;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Http\Request;

class EnumerationController extends Controller
{
    public function showEnumerationPage()
    {
        $months = Month::all();
        $years = Year::all();
        $categories = Category::whereNotNull('code')->orderBy('id')->get();

        $latestPeriod = DB::table('enumerations as e')
            ->join('years as y', 'y.id', '=', 'e.year_id')
            ->join('months as m', 'm.id', '=', 'e.month_id')
            ->orderByDesc('y.name')
            ->orderByDesc('m.id')
            ->select('e.month_id', 'e.year_id')
            ->first();

        return Inertia::render('enumerations/Index', [
            'months' => $months,
            'years' => $years,
            'categories' => $categories,
            'defaultMonth' => $latestPeriod?->month_id,
            'defaultYear' => $latestPeriod?->year_id,
        ]);
    }

    public function getEnumerationData(Request $request)
    {
        $progress = Enumeration::query()
            ->with('regency')
            ->when($request->month, fn($q) => $q->where('month_id', $request->month))
            ->when($request->year, fn($q) => $q->where('year_id', $request->year))
            ->orderBy('regency_id')
            ->get();

        $data = $progress
            ->groupBy('regency_id')
            ->map(function ($items) {
                return [
                    'regency' => $items->first()->regency,
                    'values' => $items->mapWithKeys(fn($item) => [
                        $item->category_id => $item->value,
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
