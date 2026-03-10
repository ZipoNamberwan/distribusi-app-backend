<?php

namespace App\Http\Controllers;

use App\Models\Year;
use App\Models\Month;
use App\Models\Category;
use App\Models\Enumeration;
use App\Models\SampleTarget;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

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
        $month = $request->month;
        $year = $request->year;

        if ($month == null || $year == null) {
            $latestPeriod = DB::table('enumerations as e')
                ->join('years as y', 'y.id', '=', 'e.year_id')
                ->join('months as m', 'm.id', '=', 'e.month_id')
                ->orderByDesc('y.name')
                ->orderByDesc('m.id')
                ->select('e.month_id', 'e.year_id')
                ->first();

            $month = $latestPeriod?->month_id;
            $year = $latestPeriod?->year_id;
        }

        $progress = Enumeration::query()
            ->with('regency')
            ->when($month, fn($q) => $q->where('month_id', $month))
            ->when($year, fn($q) => $q->where('year_id', $year))
            ->orderBy('regency_id')
            ->get();

        // Get sample targets
        $sampleTargets = SampleTarget::query()
            ->when($month, fn($q) => $q->where('month_id', $month))
            ->when($year, fn($q) => $q->where('year_id', $year))
            ->get();

        // If no rows match month+year, fallback to year only
        if ($sampleTargets->isEmpty() && $year) {
            $sampleTargets = SampleTarget::query()
                ->where('year_id', $year)
                ->get();
        }

        // Group sample targets by regency_id
        $targetsByRegency = $sampleTargets
            ->groupBy('regency_id')
            ->map(function ($items) {
                return $items->mapWithKeys(fn($item) => [
                    $item->category_id => $item->value,
                ]);
            });

        $data = $progress
            ->groupBy('regency_id')
            ->map(function ($items) use ($targetsByRegency) {
                $regencyId = $items->first()->regency_id;
                return [
                    'regency' => $items->first()->regency,
                    'realization' => $items->mapWithKeys(fn($item) => [
                        $item->category_id => $item->value,
                    ]),
                    'target' => $targetsByRegency[$regencyId] ?? new Collection(),
                ];
            })
            ->values();

        return response()->json([
            'data' => $data,
            'total' => $data->count(),
        ]);
    }
}
