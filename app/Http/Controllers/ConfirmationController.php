<?php

namespace App\Http\Controllers;

use App\Models\Confirmation;
use App\Models\Month;
use App\Models\Year;
use App\Models\Regency;
use App\Models\ErrorType;
use App\Models\User;
use DateTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\RedirectResponse;

class ConfirmationController extends Controller
{
    public function showConfirmationPage()
    {
        $months = Month::all();
        $years = Year::where('name', '<=', (string) now()->year)->get();
        $regencies = Regency::all();
        $errorTypes = ErrorType::all();
        $statuses = ['not_confirmed', 'confirmed', 'approved', 'rejected'];

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
        $user = User::find(Auth::id());
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

        // Filter by user's regency
        if ($user->hasRole('adminkab')) {
            $records->whereHas('input', function ($query) use ($user) {
                $query->where('kode_kab', $user->regency_id);
            });
        }

        // Filter by input month
        if ($request->input('month') !== null) {
            $records->whereHas('input', function ($query) use ($request) {
                $query->where('bulan', $request->input('month')); // or 'month_id' if you named it that
            });
        }

        if ($request->input('search') !== null) {
            $records->whereHas('input', function ($query) use ($request) {
                $query->where('nama_komersial', 'like', '%' . $request->input('search') . '%'); 
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

    public function confirm(Request $request): RedirectResponse
    {
        $user =  User::find(Auth::id());
        $validateArray = [
            'id' => [
                'required',
                'exists:confirmations,id',
            ],
            'notes' => ['required', 'string', function ($attribute, $value, $fail) use ($user, $request) {
                $confirmation = Confirmation::with('input')->find($request->input('id'));

                if (!$confirmation || $confirmation->input->kode_kab !== $user->regency_id) {
                    $fail('Anda tidak diijinkan mengkonfirmasi error ini.');
                }
            }],
        ];
        $request->validate($validateArray);

        $confirmation = Confirmation::find($request->input('id'));

        $confirmation->notes = $request->input('notes');
        $confirmation->status = 'confirmed';
        $confirmation->sent_by_id = $user->id;
        $confirmation->save();

        return redirect()->back()->with('success', 'File telah diupload, cek status dengan menekan tombol Status Upload');
    }

    public function approve(Request $request): RedirectResponse
    {
        $user =  User::find(Auth::id());
        if (!$user->hasRole('adminprov')) {
            return back()->with('error', 'Anda tidak diijinkan mengapprove/reject error.');
        }

        $validateArray = [
            'ids' => [
                'required',
                'array',
                'min:1',
            ],
            'ids.*' => [
                'required',
                'exists:confirmations,id',
            ],
            'status' => ['required', 'in:approved,rejected'],
        ];
        $request->validate($validateArray);

        $ids = $request->input('ids');
        $status = $request->input('status');
        $updated = 0;
        foreach ($ids as $id) {
            $confirmation = Confirmation::find($id);
            if ($confirmation) {
                $confirmation->status = $status;
                $confirmation->approved_by_id = $user->id;
                $confirmation->save();
                $updated++;
            }
        }

        if (count($ids) === 1) {
            return back()->with('success', 'Konfirmasi berhasil di-' . $status);
        }
        return back()->with('success', $updated . ' konfirmasi berhasil di-' . $status);
    }
}
