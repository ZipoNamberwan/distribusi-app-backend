<?php

namespace App\Http\Controllers;

use App\Models\Year;
use App\Models\Month;
use App\Models\Category;
use App\Models\FinalNumber;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Models\Regency;
use DateTime;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\JsonResponse;

class PredictionController extends Controller
{
    public function showPredictionPage()
    {
        $months = Month::all();
        $years = Year::where('name', '<=', (string) now()->year)->get();
        $categories = Category::whereNotNull('code')->orderBy('id')->get();
        $indicators = Indicator::where('name', 'TPK')->get()->map(fn($ind) => array_merge(
            $ind->toArray(),
            ['categories' => $categories->toArray()]
        ));

        $date = new DateTime('first day of last month');

        $year = Year::where('code', $date->format('Y'))->first();
        $month = Month::where('code', $date->format('m'))->first();

        $prevMonthDate = clone $date;
        $prevMonthDate->modify('-1 month');

        $prevMonth = Month::where('code', $prevMonthDate->format('m'))->first();
        $prevMonthYear = Year::where('code', $prevMonthDate->format('Y'))->first();

        $prevYearDate = clone $date;
        $prevYearDate->modify('-1 year');

        $sameMonthPrevYear = Month::where('code', $prevYearDate->format('m'))->first();
        $prevYear = Year::where('code', $prevYearDate->format('Y'))->first();

        $regencies = Regency::all();
        return Inertia::render('predictions/Index', [
            'months' => $months,
            'years' => $years,
            'categories' => $categories,
            'indicators' => $indicators,
            'regencies' => $regencies,
            'initialPeriod' => [
                'current' => ['month' => $month, 'year' => $year],
                'previousMonth' => [
                    'month' => $prevMonth,
                    'year' => $prevMonthYear,
                ],
                'sameMonthPreviousYear' => [
                    'month' => $sameMonthPrevYear,
                    'year' => $prevYear,
                ],
            ],
        ]);
    }

    public function getPredictionData(Request $request): JsonResponse
    {
        $query = IndicatorValue::query()
            ->with(['regency', 'indicator', 'category'])
            ->whereHas('indicator', function ($q) {
                $q->where('name', 'TPK');
            });

        $month = $request->input('month');
        $year = $request->input('year');

        if ($month == null || $year == null) {
            return response()->json([
                'data' => [],
                'total' => 0,
            ]);
        }

        if ($month) {
            $query->where('month_id', $month);
        }

        if ($year) {
            $query->where('year_id', $year);
        }

        $orderDir = (! empty($request->sortOrder) && $request->sortOrder === 'descend') ? 'desc' : 'asc';
        $query->orderBy('regency_id', $orderDir);

        $rows = $query->get();

        $total = $rows->unique(fn($row) => $row->regency->id)->count();

        $data = $rows->groupBy(fn($row) => $row->regency->id)->map(function ($regencyRows) use ($month, $year) {
            $first = $regencyRows->first();
            $values = $regencyRows->mapWithKeys(fn($row) => [
                "{$row->indicator_id}_{$row->category_id}" => [
                    'num' => $row->numerator,
                    'den' => $row->denominator,
                ],
            ]);

            $mom = [];
            $yoy = [];

            foreach ($regencyRows as $row) {
                $catId = $row->category_id;
                $current = ($row->denominator && $row->numerator !== null) ? ($row->numerator / $row->denominator) * 100 : null;

                // Calculate previous period (month)
                $prevMonth = $month - 1;
                $prevYear = $year;
                if ($prevMonth < 1) {
                    $prevMonth = 12;
                    $prevYear = $year - 1;
                }
                $prevFinal = FinalNumber::where('regency_id', $row->regency_id)
                    ->where('category_id', $catId)
                    ->where('month_id', $prevMonth)
                    ->where('year_id', $prevYear)
                    ->first();
                $prev = isset($prevFinal?->value) ? (float) $prevFinal->value : null;
                $mom[(string)$catId] = [
                    'current' => $current,
                    'prev' => $prev,
                ];

                // Calculate previous year (same month)
                $yoyFinal = FinalNumber::where('regency_id', $row->regency_id)
                    ->where('category_id', $catId)
                    ->where('month_id', $month)
                    ->where('year_id', $year - 1)
                    ->first();

                $yoyPrev = isset($yoyFinal?->value) ? (float) $yoyFinal->value : null;
                $yoy[(string)$catId] = [
                    'current' => $current,
                    'prev' => $yoyPrev,
                ];
            }

            return [
                'regency' => [
                    'id'        => $first->regency->id,
                    'name'      => $first->regency->name,
                    'long_code' => $first->regency->long_code,
                ],
                'values' => $values,
                'mom' => $mom,
                'yoy' => $yoy,
            ];
        })->values();

        $month = Month::find($month);
        $year = Year::find($year);
        $date = new DateTime("$year->name-$month->code-01");

        $prevMonthDate = clone $date;
        $prevMonthDate->modify('-1 month');
        $prevMonth = Month::where('code', $prevMonthDate->format('m'))->first();
        $prevMonthYear = Year::where('code', $prevMonthDate->format('Y'))->first();

        $prevYearDate = clone $date;
        $prevYearDate->modify('-1 year');
        $sameMonthPrevYear = Month::where('code', $prevYearDate->format('m'))->first();
        $prevYear = Year::where('code', $prevYearDate->format('Y'))->first();



        return response()->json([
            'data' => $data,
            'total' => $total,
            'period' => [
                'current' => ['month' => $month, 'year' => $year],
                'previousMonth' => ['month' => $prevMonth, 'year' => $prevMonthYear],
                'sameMonthPreviousYear' => ['month' => $sameMonthPrevYear, 'year' => $prevYear],
            ],
        ]);
    }
}
