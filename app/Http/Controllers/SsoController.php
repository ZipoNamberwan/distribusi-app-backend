<?php
// app/Http/Controllers/Auth/SsoController.php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\KeycloakProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

            $user = User::where('email', $data['email'])->first();
            if (!$user) {
                return response()->json([
                    'error' => 'User not registered'
                ], 403);
            } else {
                // Update token
                $user->update([
                    'name' => $data['name'] ?? $user->name,
                ]);

                Auth::login($user);

                return redirect()->route('data.dashboard.index');
            }
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Gagal ambil user',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        // 1. Logout from Laravel
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // 2. Redirect to SSO logout
        return redirect(
            KeycloakProvider::getLogoutUrl([
                'redirect_uri' => route('login'),
            ])
        );
    }
}
