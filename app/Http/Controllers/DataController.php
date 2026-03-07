<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Input;

class DataController extends Controller
{
    public function getInputData(Request $request){
         $records = null;

        $records = Input::with(['month', 'year', 'user', 'regency', 'syncStatus']);

        if ($request->month) {
            $records->where('bulan', $request->month);
        }
        if ($request->year) {
            $records->where('tahun', $request->year);
        }

        if ($request->status) {
            $records->whereIn('status', $request->status);
        }

        if ($request->regency) {
            $records->whereIn('kode_kab', $request->regency);
        }
        
        if ($request->nama_komersial) {
            $search = is_array($request->nama_komersial) ? $request->nama_komersial[0] : $request->nama_komersial;
            $records->where('nama_komersial', 'like', '%' . $search . '%');
        }

        if ($request->alamat) {
            $search = is_array($request->alamat) ? $request->alamat[0] : $request->alamat;
            $records->where('alamat', 'like', '%' . $search . '%');
        }

        if ($request->kode_kec) {
            $search = is_array($request->kode_kec) ? $request->kode_kec[0] : $request->kode_kec;
            $records->where('kode_kec', 'like', '%' . $search . '%');
        }

        if ($request->kode_desa) {
            $search = is_array($request->kode_desa) ? $request->kode_desa[0] : $request->kode_desa;
            $records->where('kode_desa', 'like', '%' . $search . '%');
        }

        if ($request->status) {
            $search = is_array($request->status) ? $request->status[0] : $request->status;
            $records->where('status', 'like', '%' . $search . '%');
        }

        $orderColumn = 'created_at';
        $orderDir = 'desc';

        if (!empty($request->sortOrder) && ! empty($request->sortField)) {
            $orderColumn = $request->sortField;
            if ($request->sortField == 'regency'){
                $orderColumn = 'kode_kab';
            }
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
