<?php

namespace App\Http\Controllers;

use App\Jobs\InputJob;
use App\Models\Month;
use App\Models\Regency;
use App\Models\SyncStatus;
use App\Models\User;
use App\Models\Year;
use App\Models\Category;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class InputController extends Controller
{
    public function showUploadForm(): Response
    {
        $months = Month::all();
        $currentYear = now()->year;
        $years = Year::whereIn('name', [$currentYear, $currentYear - 1])->orderByDesc('name')->get();

        $prev = Carbon::now()->subMonth();
        $prevMonthCode = str_pad((string) $prev->month, 2, '0', STR_PAD_LEFT);

        $defaultMonth = Month::where('code', $prevMonthCode)->value('id');
        $defaultYear = Year::where('name', (string) $prev->year)->value('id');

        $statuses = [
            ['title' => 'Start', 'value' => 'start', 'color' => 'default'],
            ['title' => 'Loading', 'value' => 'loading', 'color' => 'processing'],
            ['title' => 'Success', 'value' => 'success', 'color' => 'success'],
            ['title' => 'Failed', 'value' => 'failed', 'color' => 'error'],
            ['title' => 'Success with Error', 'value' => 'success with error', 'color' => 'warning'],
        ];
        $regencies = Regency::all();
        $categories = Category::all();

        return Inertia::render('data/Upload', [
            'months' => $months,
            'years' => $years,
            'statuses' => $statuses,
            'regencies' => $regencies,
            'defaultMonth' => $defaultMonth,
            'defaultYear' => $defaultYear,
            'categories' => $categories,
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
                'type' => 'input',
                'filename' => $customFileName,
                'status' => 'start',
            ]);

            try {
                InputJob::dispatch($status);

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

}
