<?php

namespace App\Http\Controllers;

use App\Models\User;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class MajapahitController extends Controller
{
    public function redirect()
    {
        $redirectUrl = config('app.majapahit_url') . route('majapahit.callback.index');

        return redirect($redirectUrl);
    }

    public function callback(Request $request)
    {
        $jwt = $request->query('token');

        if (Auth::check()) {
            return redirect()->route('data.dashboard.index');
        }
        elseif ($jwt) {
            JWT::$leeway = 60;
            try {
                $key = config('app.majapahit_key');
                $decoded = JWT::decode($jwt, new Key($key, 'HS256'));

                $user = User::where('email', $decoded->email)->first();

                if (!$user) {
                    return Inertia::render('auth/Error', [
                        'error' => 'User Belum Terdaftar',
                        'description' => 'Akun Majapahit Anda belum terdaftar di aplikasi ini.',
                    ]);
                }
                else {
                    Auth::login($user, true);
                    $user->update(['name' => $decoded->nama]);
                    return redirect()->route('data.dashboard.index');
                }
            }
            catch (Exception $e) {
                if (str_contains($e->getMessage(), 'Expired token') || str_contains($e->getMessage(), 'Signature verification failed')) {
                    return Inertia::render('auth/Error', [
                        'error' => 'Gagal Mengambil Data User',
                        'description' => 'Token Majapahit tidak valid, silahkan buka ulang Majapahit',
                    ]);
                }
                else {
                    return Inertia::render('auth/Error', [
                        'error' => 'Gagal Mengambil Data User',
                        'description' => 'Terjadi kesalahan pada aplikasi ini, silahkan coba lagi',
                    ]);
                }
            }
        }

        return Inertia::render('auth/Error', [
            'error' => 'Gagal Mengambil Data User',
            'description' => 'Anda belum masuk ke akun Meniko Jatim, silahkan buka melalui Majapahit',
        ]);
    }
}