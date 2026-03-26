<?php

// app/Http/Controllers/Auth/SsoController.php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\KeycloakProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;

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
        if (! $request->has('state') || $request->state !== Session::get('oauth2state')) {
            Session::forget('oauth2state');
            abort(403, 'Invalid state');
        }

        try {
            $token = $provider->getAccessToken('authorization_code', [
                'code' => $request->code,
            ]);
        } catch (\Exception $e) {
            return Inertia::render('auth/Error', [
                'error' => 'Gagal Mendapatkan Token',
                'description' => $e->getMessage(),
            ]);
        }

        try {
            $user = $provider->getResourceOwner($token);
            $data = $user->toArray();

            $user = User::where('email', $data['email'])->first();
            if (! $user) {
                return Inertia::render('auth/Error', [
                    'error' => 'User Belum Terdaftar',
                    'description' => 'Akun SSO Anda belum terdaftar di aplikasi ini.',
                ]);
            } else {
                // Update token
                $user->update([
                    'name' => $data['name'] ?? $user->name,
                ]);

                Auth::login($user);

                return redirect()->route('data.dashboard.index');
            }
        } catch (\Exception $e) {
            return Inertia::render('auth/Error', [
                'error' => 'Gagal Mengambil Data User',
                'description' => $e->getMessage(),
            ]);
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
