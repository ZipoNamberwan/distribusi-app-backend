<?php

use App\Http\Controllers\DataController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::get('data', [DataController::class, 'index'])->name('data.index');
    Route::get('data/sheet', [DataController::class, 'readSheet'])->name('data.sheet');

    Route::get('data/upload', [DataController::class, 'upload'])->name('data.upload');
    Route::post('data/upload', [DataController::class, 'storeUpload'])->name('data.upload.store');
});

require __DIR__.'/settings.php';
