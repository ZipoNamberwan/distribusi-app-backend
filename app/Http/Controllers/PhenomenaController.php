<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Category;
use App\Models\Indicator;
use App\Models\Year;
use App\Models\Month;
use App\Models\Phenomena;
use App\Models\Regency;
use App\Models\User;
use DateTime;
use Illuminate\Support\Facades\Auth;

class PhenomenaController extends Controller
{
    public function showPhenomenaPage()
    {
        $months = Month::all();
        $years = Year::where('name', '<=', (string)now()->year)->get();
        $categories = Category::whereNotNull('code')->orderBy('id')->get();
        $indicators = Indicator::orderBy('id')->get()->map(fn($ind) => array_merge(
        $ind->toArray(),
        ['categories' => $categories->toArray()]
        ));

        $date = new DateTime('first day of last month');

        $year = Year::where('code', $date->format('Y'))->first();
        $month = Month::where('code', $date->format('m'))->first();

        $user = User::find(Auth::id());
        if ($user->hasRole('adminkab')) {
            $regencies = Regency::where('id', $user->regency_id)->get();
        } else {
            $regencies = Regency::where('level', 'regency')->orderBy('long_code')->get();
        }

        return Inertia::render('phenomena/Index', [
            'months' => $months,
            'years' => $years,
            'indicators' => $indicators,
            'regencies' => $regencies,
            'initialPeriod' => [
                'month' => $month,
                'year' => $year,
            ],
        ]);
    }

    public function storePhenomena(Request $request)
    {
        $user = User::find(Auth::id());

        if ($user->regency_id != $request->input('regency_id')) {
            return back()->with('error', 'Anda tidak memiliki hak akses untuk melakukan aksi ini');
        }

        $request->validate([
            'description' => 'required',
            'regency_id' => 'required',
            'year_id' => 'required',
            'month_id' => 'required',
        ]);

        $data = [
            'description' => $request->input('description'),
            'regency_id' => $request->input('regency_id'),
            'year_id' => $request->input('year_id'),
            'month_id' => $request->input('month_id'),
        ];

        if ($request->filled('id')) {
            $phenomena = Phenomena::findOrFail($request->input('id'));
            $phenomena->update($data);
        }
        else {
            $existingPhenomena = Phenomena::where('regency_id', $request->input('regency_id'))
                ->where('year_id', $request->input('year_id'))
                ->where('month_id', $request->input('month_id'))
                ->first();

            if ($existingPhenomena) {
                $existingPhenomena->update($data);
            }
            else {
                Phenomena::create($data);
            }
        }
        return back()->with('success', 'Fenomena berhasil disimpan');
    }

    public function getPhenomenaData(Request $request)
    {
        if ($request->input('year') == null || $request->input('month') == null) {
            return response()->json(['data' => [], 'total' => 0]);
        }

        $user = User::find(Auth::id());

        $yearId = $request->input('year');
        $monthId = $request->input('month');

        $year = Year::find($yearId);
        $month = Month::find($monthId);

        if ($user->hasRole('adminkab')) {
            $query = Regency::leftJoin('phenomenas', function ($join) use ($yearId, $monthId) {
                $join->on('regencies.id', '=', 'phenomenas.regency_id')
                    ->where('phenomenas.year_id', $yearId)
                    ->where('phenomenas.month_id', $monthId);
            })->where('regencies.level', 'regency')->where('regencies.id', $user->regency_id);
        } else {
            $query = Regency::leftJoin('phenomenas', function ($join) use ($yearId, $monthId) {
                $join->on('regencies.id', '=', 'phenomenas.regency_id')
                    ->where('phenomenas.year_id', $yearId)
                    ->where('phenomenas.month_id', $monthId);
            })->where('regencies.level', 'regency');
        }

        // Optional filter
        if ($request->input('regency')) {
            $query->whereIn('regencies.id', $request->input('regency'));
        }

        // Sorting
        $orderColumn = 'regencies.id';
        $orderDir = 'asc';

        if (!empty($request->input('sortOrder')) && !empty($request->input('sortField'))) {
            $orderColumn = $request->input('sortField');

            if ($orderColumn === 'regency') {
                $orderColumn = 'regencies.long_code';
            }

            $orderDir = $request->input('sortOrder') === 'ascend' ? 'asc' : 'desc';
        }

        $query->orderBy($orderColumn, $orderDir);

        // Select all regency + all phenomena (aliased)
        $records = $query->select(
            'regencies.*',

            // alias ALL phenomena columns to avoid collision
            'phenomenas.id as phenomena_id',
            'phenomenas.regency_id as phenomena_regency_id',
            'phenomenas.year_id as phenomena_year_id',
            'phenomenas.month_id as phenomena_month_id',
            'phenomenas.description as phenomena_description',
            'phenomenas.created_at as phenomena_created_at',
            'phenomenas.updated_at as phenomena_updated_at'
            // 👉 add other columns if exist
        )->get();

        // Transform
        $data = $records->map(function ($row) use ($month, $year) {
            return [
            'regency' => [
            'id' => $row->id,
            'name' => $row->name,
            'long_code' => $row->long_code,
            'short_code' => $row->short_code,
            'level' => $row->level,
            'parent_id' => $row->parent_id,
            ],
            'phenomena' => $row->phenomena_id ? [
            'id' => $row->phenomena_id,
            'year_id' => $row->phenomena_year_id,
            'month_id' => $row->phenomena_month_id,
            'description' => $row->phenomena_description ?? null,
            'created_at' => $row->phenomena_created_at,
            ] : null,

            // ✅ ADD THIS
            'period' => [
            'month' => $month,
            'year' => $year,
            ],

            // ✅ Optional (very useful for UI)
            'period_text' => $month->name . ' ' . $year->name,
            ];
        });

        return response()->json([
            'total' => $records->count(),
            'data' => $data,
            'period' => [
                'month' => $month,
                'year' => $year
            ]
        ]);
    }
}