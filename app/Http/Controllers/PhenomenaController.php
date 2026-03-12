<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class PhenomenaController extends Controller
{
    public function showPhenomenaPage() {
        return Inertia::render('phenomena/Index');
    }
}
