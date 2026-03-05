<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SyncStatus;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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

    public function storeUpload(Request $request): RedirectResponse
    {
        $validateArray = [
            'file' => 'required|file|mimes:xlsx|max:10240',
        ];

        $request->validate($validateArray);
        $user = User::find(Auth::id());

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $extension = $file->getClientOriginalExtension();
            $customFileName = $user->firstname . '_' . now()->format('Ymd_His') . '_' . Str::random(4) . '.' . $extension;

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
                // let this logic empty first, ee will do later
                
                return back()->with('success', 'File telah diupload, cek status pada tabel di bawah!');
            } catch (Exception $e) {
                $status->update([
                    'status' => 'failed',
                    'message' => $e->getMessage(),
                ]);
                
                return back()->with('error', 'File gagal diupload: ' . $e->getMessage());
            }
        }

        return back()->with('error', 'File gagal diupload, tidak ada file yang dipilih');
    }
    
    public function getUploadStatusData(Request $request): JsonResponse
    {
        $data = $request->all();

        return response()->json($data);
    }
}
