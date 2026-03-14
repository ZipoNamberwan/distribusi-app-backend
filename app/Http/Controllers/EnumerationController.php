<?php

namespace App\Http\Controllers;

use App\Models\Year;
use App\Models\Month;
use App\Models\Category;
use App\Models\Enumeration;
use App\Models\Regency;
use App\Models\SampleTarget;
use DateTime;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class EnumerationController extends Controller
{
    public function showEnumerationPage()
    {
        $months = Month::all();
        $years = Year::where('name', '<=', (string) now()->year)->get();
        $categories = Category::whereNotNull('code')->orderBy('id')->get();

        $date = new DateTime('first day of last month');
        $year = Year::where('code', $date->format('Y'))->first();
        $month = Month::where('code', $date->format('m'))->first();

        $regencies = Regency::all();

        return Inertia::render('enumerations/Index', [
            'months' => $months,
            'years' => $years,
            'categories' => $categories,
            'regencies' => $regencies,
            'initialPeriod' => [
                'month' => $month,
                'year' => $year,
            ],
        ]);
    }

    public function getEnumerationData(Request $request)
    {
        $month = $request->month;
        $year = $request->year;

        if ($month == null || $year == null) {
            return response()->json([
                'data' => [],
                'total' => 0,
            ]);
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


        $year = Year::find($request->input('year'));
        $month = Month::find($request->input('month'));

        return response()->json([
            'data' => $data,
            'total' => $data->count(),
            'period' => [
                'month' => $month,
                'year' => $year,
            ],
        ]);
    }
}
