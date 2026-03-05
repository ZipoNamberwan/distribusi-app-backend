<?php

use App\Http\Controllers\DataController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::get('data', [DataController::class, 'index'])->name('data.index');
    Route::get('data/sheet', [DataController::class, 'readSheet'])->name('data.sheet');

    Route::get('upload', [UploadController::class, 'showUploadForm'])->name('upload.index');
    Route::get('template', [UploadController::class, 'downloadInputTemplate'])->name('upload.template.index');
    Route::post('upload', [UploadController::class, 'storeUpload'])->name('upload.store.index');
    Route::get('upload/status/data', [UploadController::class, 'getUploadStatusData'])->name('upload.status.index');

});

require __DIR__.'/settings.php';
