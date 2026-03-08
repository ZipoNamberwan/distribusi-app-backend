<?php

use App\Http\Controllers\DataController;
use App\Http\Controllers\ErrorSummaryController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::get('data', [DataController::class, 'index'])->name('data.index');
    Route::get('data/input', [DataController::class, 'getInputData'])->name('data.raw.index');

    Route::get('upload', [UploadController::class, 'showUploadForm'])->name('upload.index');
    Route::get('template', [UploadController::class, 'downloadInputTemplate'])->name('upload.template.index');
    Route::post('upload', [UploadController::class, 'storeUpload'])->name('upload.store.index');
    Route::post('download', [UploadController::class, 'downloadFile'])->name('upload.file.download');
    Route::get('upload/status/data', [UploadController::class, 'getUploadStatusData'])->name('upload.status.index');

    Route::get('indicator', [DataController::class, 'showIndicatorValues'])->name('indicator.table.index');
    Route::get('indicator/data', [DataController::class, 'getIndicatorValuesData'])->name('indicator.data.index');

    Route::get('error-summaries', [ErrorSummaryController::class, 'showErrorSummaryPage'])->name('error_summaries.page.index');
    Route::get('error-summaries/data', [ErrorSummaryController::class, 'getErrorSummaryData'])->name('error_summaries.data.index');
});

require __DIR__ . '/settings.php';
