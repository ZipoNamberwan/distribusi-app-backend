<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class ConfirmationController extends Controller
{
    public function showConfirmationPage()
    {
        return Inertia::render('confirmation/Index');
    }
}
