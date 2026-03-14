<?php

namespace App\Http\Controllers;

use App\Jobs\SampleTargetJob;
use App\Models\Category;
use App\Models\SampleTarget;
use App\Models\SyncStatus;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\JsonResponse;

class TargetSampleController extends Controller
{
    public function downloadTemplate()
    {
        return Storage::download('template/template_sample.xlsx');
    }

    public function storeUpload(Request $request)
    {
        $validateArray = [
            'type' => 'required|in:default,monthly',
            'file' => 'required|file|mimes:xlsx|max:10240',
            'month' => 'required_if:type,monthly',
            'year' => 'required|exists:years,id',
        ];
        $request->validate($validateArray);
        $user = User::find(Auth::id());

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $extension = $file->getClientOriginalExtension();
            $customFileName = now()->format('Ymd_His') . '_' . Str::random(4) . '.' . $extension;

            $file->storeAs('/uploads', $customFileName);

            $uuid = Str::uuid();
            $status = SyncStatus::create([
                'id' => $uuid,
                'user_id' => $user->id,
                'month_id' => $request->type === 'monthly' ? $request->input('month') : null,
                'year_id' => $request->input('year'),
                'type' => 'sample',
                'filename' => $customFileName,
                'status' => 'start',
            ]);

            try {
                SampleTargetJob::dispatch($status);

                return redirect()->back()->with('success', 'File telah diupload, cek status dengan menekan tombol Status Upload');
            } catch (Exception $e) {
                $status->update([
                    'status' => 'failed',
                    'system_message' => $e->getMessage(),
                    'user_message' => $e->getMessage(),
                ]);

                return redirect()->back()->with('error', 'File gagal diupload: ' . $e->getMessage());
            }
        }

        return redirect()->back()->with('error', 'File gagal diupload, tidak ada file yang dipilih');
    }

    public function getTargetSampleData(Request $request): JsonResponse
    {
        // Get all sample targets with relationships
        $categories = Category::all();
        $query = SampleTarget::with(['regency', 'month', 'year', 'category'])
            ->join('regencies', 'sample_targets.regency_id', '=', 'regencies.id')
            ->orderBy('regencies.long_code', 'asc')
            ->select('sample_targets.*');
        if ($request->has('is_default')) {
            $isDefault = $request->input('is_default');
            if (is_array($isDefault)) {
                $query->whereIn('is_default', $isDefault);
            } else {
                $query->where('is_default', $isDefault);
            }
        } else {
            $query->where('is_default', true);
        }
        if ($request->has('regency')) {
            $regencies = $request->input('regency');
            if (is_array($regencies)) {
                $query->whereIn('regency_id', $regencies);
            } else {
                $query->where('regency_id', $regencies);
            }
        }
        if ($request->has('year')) {
            $years = $request->input('year');
            if (is_array($years)) {
                $query->whereIn('year_id', $years);
            } else {
                $query->where('year_id', $years);
            }
        }
        if ($request->has('month')) {
            $months = $request->input('month');
            if (is_array($months)) {
                $query->whereIn('month_id', $months);
            } else {
                $query->where('month_id', $months);
            }
        }
        $sampleTargets = $query->get();

        // Group by regency, month, year
        $grouped = $sampleTargets->groupBy(function ($item) {
            return $item->regency_id . '-' . $item->month_id . '-' . $item->year_id;
        });

        $data = [];
        foreach ($grouped as $group) {
            $first = $group->first();
            $row = [
                'regency' => $first->regency,
                'month' => $first->month,
                'year' => $first->year,
                'is_default' => $first->is_default,
            ];
            foreach ($categories as $category) {
                $target = $group->firstWhere('category_id', $category->id);
                $row[$category->id] = $target ? $target->value : 0;
            }
            $data[] = $row;
        }

        // Handle sorting for category columns
        $sortField = $request->input('sortField');
        $sortOrder = $request->input('sortOrder');
        if ($sortField && isset($row[$sortField])) {
            usort($data, function ($a, $b) use ($sortField, $sortOrder) {
                $valA = $a[$sortField] ?? 0;
                $valB = $b[$sortField] ?? 0;
                if ($sortOrder === 'descend') {
                    return $valB <=> $valA;
                } else {
                    return $valA <=> $valB;
                }
            });
        }

        $total = count($data);
        return response()->json(['data' => $data, 'total' => $total]);
    }
}
