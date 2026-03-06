<?php

namespace App\Http\Controllers;

use App\Jobs\SyncDataJob;
use App\Models\SyncStatus;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
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
        return Inertia::render('data/Upload');
    }

    public function downloadInputTemplate()
    {
        return Storage::download('template/template_vhts.xlsx');
    }

    public function downloadFile(Request $request)
    {
        $uploadId = $request->input('uploadId');
        $status = SyncStatus::findOrFail($uploadId);

        return Storage::download('uploads/'.$status->filename);
    }

    public function storeUpload(Request $request): JsonResponse
    {
        $validateArray = [
            'file' => 'required|file|mimes:xlsx|max:10240',
        ];

        $request->validate($validateArray);
        $user = User::find(Auth::id());

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $extension = $file->getClientOriginalExtension();
            $customFileName = now()->format('Ymd_His').'_'.Str::random(4).'.'.$extension;

            $storedPath = $file->storeAs('/uploads', $customFileName);
            $absolutePath = Storage::path($storedPath);

            $uuid = Str::uuid();
            $status = SyncStatus::create([
                'id' => $uuid,
                'user_id' => $user->id,
                'filename' => $customFileName,
                'status' => 'start',
            ]);

            try {
                //
                SyncDataJob::dispatch($status);

                return response()->json([
                    'message' => 'File telah diupload, cek status pada tabel di bawah!',
                    'status_id' => $status->id,
                ]);
            } catch (Exception $e) {
                $status->update([
                    'status' => 'failed',
                    'message' => $e->getMessage(),
                ]);

                return response()->json([
                    'error' => 'File gagal diupload: '.$e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'error' => 'File gagal diupload, tidak ada file yang dipilih',
        ]);
    }

    public function getUploadStatusData(Request $request): JsonResponse
    {
        $records = null;

        $records = SyncStatus::query();

        if ($request->status) {
            $records->whereIn('status', $request->status);
        }

        $orderColumn = 'created_at';
        $orderDir = 'desc';

        if (! empty($request->sortOrder) && ! empty($request->sortField)) {
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
