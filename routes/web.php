<?php

use App\Http\Controllers\DataController;
use App\Http\Controllers\EnumerationController;
use App\Http\Controllers\ErrorSummaryController;
use App\Http\Controllers\InputController;
use App\Http\Controllers\TargetSampleController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::get('data', [DataController::class, 'index'])->name('data.index');
    Route::get('data/input', [DataController::class, 'getInputData'])->name('data.raw.index');
    Route::get('status/data/{type}', [DataController::class, 'getUploadStatusData'])->name('data.status.index');

    Route::get('input/upload', [InputController::class, 'showUploadForm'])->name('upload.index');
    Route::get('input/template', [InputController::class, 'downloadInputTemplate'])->name('upload.template.index');
    Route::post('input/upload', [InputController::class, 'storeUpload'])->name('upload.store.index');
    Route::post('input/download', [InputController::class, 'downloadFile'])->name('upload.file.download');

    Route::get('indicator', [DataController::class, 'showIndicatorValues'])->name('indicator.table.index');
    Route::get('indicator/data', [DataController::class, 'getIndicatorValuesData'])->name('indicator.data.index');

    Route::get('error-summaries', [ErrorSummaryController::class, 'showErrorSummaryPage'])->name('error_summaries.page.index');
    Route::get('error-summaries/data', [ErrorSummaryController::class, 'getErrorSummaryData'])->name('error_summaries.data.index');

    Route::get('enumeration', [EnumerationController::class, 'showEnumerationPage'])->name('enumeration.page.index');
    Route::get('enumeration/data', [EnumerationController::class, 'getEnumerationData'])->name('enumeration.data.index');

    Route::get('sample/template', [TargetSampleController::class, 'downloadTemplate'])->name('sample.template.index');
    Route::get('sample/data', [TargetSampleController::class, 'getTargetSampleData'])->name('sample.data.index');
    Route::post('sample/upload', [TargetSampleController::class, 'storeUpload'])->name('sample.upload.store');
});

require __DIR__ . '/settings.php';
