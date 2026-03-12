<?php

namespace App\Http\Controllers;

use App\Models\Regency;
use App\Models\Role;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\JsonResponse;

class UserController extends Controller
{
    public function showUserPage()
    {
        $regencies = Regency::all();
        $roles = Role::all();
        return Inertia::render(
            'user/Index',
            ['regencies' => $regencies, 'roles' => $roles]
        );
    }

    public function getUserData(Request $request): JsonResponse
    {
        $records = User::with(['regency', 'roles']);

        if ($request->input('regency')) {
            $records->whereIn('regency_id', $request->input('regency'));
        }

        if ($request->input(key: 'roles')) {
            $records->role($request->input('roles'));
        }

        if ($request->input('name')) {
            $search = is_array($request->input('name')) ? $request->input('name')[0] : $request->input('nama_komersial');
            $records->where('name', 'like', '%' . $search . '%');
        }

        if ($request->input('email')) {
            $search = is_array($request->input('email')) ? $request->input('email')[0] : $request->input('email');
            $records->where('email', 'like', '%' . $search . '%');
        }

        $orderColumn = 'name';
        $orderDir = 'desc';

        if (!empty($request->input('sortOrder')) && ! empty($request->input('sortField'))) {
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
        ]);
    }

    public function store(Request $request)
    {
        $validateArray = [
            'name' => 'required',
            'email' => 'required|email|unique:users,email',
            'regency' => 'required_if:type,monthly|exists:regencies,id',
            'role' => 'required|exists:roles,name',
        ];

        try {
            $request->validate($validateArray);
            $userId = $request->input('id');
            if ($userId) {
                // Update user
                $user = User::find($userId);
                if (!$user) {
                    return response()->json(['success' => false, 'error' => 'User tidak ditemukan'], 404);
                }
                $user->name = $request->input('name');
                $user->email = $request->input('email');
                $user->regency_id = $request->input('regency');
                $user->save();
                $user->syncRoles([$request->input('role')]);
                return response()->json(['success' => true, 'message' => 'User berhasil diupdate'], 200);
            } else {
                // Create user
                $user = User::create([
                    'name' => $request->input('name'),
                    'email' => $request->input('email'),
                    'regency_id' => $request->input('regency'),
                    'password' => bcrypt('passwordMenikoJatim2026'),
                ]);
                $user->assignRole($request->input('role'));
                
                return response()->json(['success' => true, 'message' => 'User berhasil dibuat'], 201);
            }
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => 'User gagal diproses: ' . $e->getMessage()], 500);
        }
    }
}
