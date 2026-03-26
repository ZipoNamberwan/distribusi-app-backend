<?php
// app/Http/Controllers/Auth/SsoController.php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\KeycloakProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class SsoController extends Controller
{
    public function redirect()
    {
        $provider = KeycloakProvider::make();

        $authUrl = $provider->getAuthorizationUrl();

        Session::put('oauth2state', $provider->getState());

        return redirect($authUrl);
    }

    public function callback(Request $request)
    {
        $provider = KeycloakProvider::make();

        // CSRF protection
        if (!$request->has('state') || $request->state !== Session::get('oauth2state')) {
            Session::forget('oauth2state');
            abort(403, 'Invalid state');
        }

        try {
            $token = $provider->getAccessToken('authorization_code', [
                'code' => $request->code
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Gagal mendapatkan token',
                'message' => $e->getMessage()
            ], 500);
        }

        try {
            $user = $provider->getResourceOwner($token);
            $data = $user->toArray();

            // 🔥 Mapping like your original code
            return response()->json([
                'nama' => $data['name'] ?? null,
                'email' => $data['email'] ?? null,
                'username' => $data['preferred_username'] ?? null,
                'nip' => $data['nip'] ?? null,
                'nip_baru' => $data['nip_baru'] ?? null,
                'kode_organisasi' => $data['kode_organisasi'] ?? null,
                'kode_provinsi' => $data['kode_provinsi'] ?? null,
                'kode_kabupaten' => $data['kode_kabupaten'] ?? null,
                'jabatan' => $data['jabatan'] ?? null,
                'golongan' => $data['golongan'] ?? null,
                'foto' => $data['url_foto'] ?? null,
                'access_token' => $token->getToken(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Gagal ambil user',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
