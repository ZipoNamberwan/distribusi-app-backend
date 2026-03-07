<?php

namespace App\Http\Controllers;

use App\Jobs\SyncDataJob;
use App\Models\Month;
use App\Models\SyncStatus;
use App\Models\User;
use App\Models\Year;
use App\Models\Regency;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class UploadController extends Controller
{
    public function showUploadForm(): Response
    {
        $months = Month::all();
        $currentYear = now()->year;
        $years = Year::whereIn('name', [$currentYear, $currentYear - 1])->orderByDesc('name')->get();

        $statuses = [
            ['title' => 'Start', 'value' => 'start', 'color' => 'default'],
            ['title' => 'Loading', 'value' => 'loading', 'color' => 'processing'],
            ['title' => 'Success', 'value' => 'success', 'color' => 'success'],
            ['title' => 'Failed', 'value' => 'failed', 'color' => 'error'],
            ['title' => 'Success with Error', 'value' => 'success with error', 'color' => 'warning'],
        ];
        $regencies = Regency::all();

        return Inertia::render('data/Upload', [
            'months' => $months,
            'years' => $years,
            'statuses' => $statuses,
            'regencies' => $regencies
        ]);
    }

    public function downloadInputTemplate()
    {
        return Storage::download('template/template.xlsx');
    }

    public function downloadFile(Request $request)
    {
        $uploadId = $request->input('uploadId');
        $status = SyncStatus::findOrFail($uploadId);

        return Storage::download('uploads/'.$status->filename);
    }

    public function storeUpload(Request $request): RedirectResponse
    {
        $validateArray = [
            'file' => 'required|file|mimes:xlsx|max:10240',
            'month' => 'required|exists:months,id',
            'year' => 'required|exists:years,id',
        ];

        $request->validate($validateArray);
        $user = User::find(Auth::id());

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $extension = $file->getClientOriginalExtension();
            $customFileName = now()->format('Ymd_His').'_'.Str::random(4).'.'.$extension;

            $file->storeAs('/uploads', $customFileName);

            $uuid = Str::uuid();
            $status = SyncStatus::create([
                'id' => $uuid,
                'user_id' => $user->id,
                'month_id' => $request->input('month'),
                'year_id' => $request->input('year'),
                'filename' => $customFileName,
                'status' => 'start',
            ]);

            try {
                SyncDataJob::dispatch($status);

                return redirect()->back()->with('success', 'File telah diupload, cek status dengan menekan tombol Status Upload');
            } catch (Exception $e) {
                $status->update([
                    'status' => 'failed',
                    'system_message' => $e->getMessage(),
                    'user_message' => $e->getMessage(),
                ]);

                return redirect()->back()->with('error', 'File gagal diupload: '.$e->getMessage());
            }
        }

        return redirect()->back()->with('error', 'File gagal diupload, tidak ada file yang dipilih');
    }

    public function getUploadStatusData(Request $request): JsonResponse
    {
        $records = null;

        $records = SyncStatus::with(['month', 'year']);

        if ($request->status) {
            $records->whereIn('status', $request->status);
        }

        $orderColumn = 'created_at';
        $orderDir = 'desc';

        if (!empty($request->sortOrder) && ! empty($request->sortField)) {
            $orderColumn = $request->sortField;
            $direction = $request->sortOrder === 'ascend' ? 'asc' : 'desc';
            $orderDir = $direction;
        }

        $recordsTotal = $records->count();

        // Pagination
        if ($request->length != -1) {
            $records->skip($request->start)
                ->take($request->length);
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
