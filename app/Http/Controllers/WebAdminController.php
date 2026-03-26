<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WebAdminController extends Controller
{
    public function login()
    {
        return inertia('auth/AdminLogin', [
            'canResetPassword' => false,
            'canRegister' => false,
        ]);
    }
}
