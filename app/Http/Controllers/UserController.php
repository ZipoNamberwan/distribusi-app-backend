<?php

namespace App\Http\Controllers;

use App\Models\Regency;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\JsonResponse;
use Illuminate\Validation\Rule;
use Exception;

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

    /**
     * Delete a user by ID.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function delete(String $id)
    {
        try {
            $user = User::findOrFail($id);
            $user->delete();
            return back()->with('success', 'User berhasil dihapus');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
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
            if ($request->input('sortField') == 'regency') {
                $orderColumn = 'regency_id';
            
            }
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
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'id')->ignore($request->input('id')),
            ],
            'regency' => 'required_if:type,monthly|exists:regencies,id',
            'role' => 'required|exists:roles,name',
        ];

        $request->validate($validateArray);

        try {
            $userId = $request->input('id');

            if ($userId) {
                $user = User::findOrFail($userId);

                $user->update([
                    'name' => $request->input('name'),
                    'email' => $request->input('email'),
                    'regency_id' => $request->input('regency'),
                ]);

                $user->syncRoles([$request->input('role')]);

                return back()->with('success', 'User berhasil diupdate');
            }

            $user = User::create([
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'regency_id' => $request->input('regency'),
                'password' => bcrypt('passwordMenikoJatim2026'),
            ]);

            $user->assignRole($request->input('role'));

            return back()->with('success', 'User berhasil dibuat');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
