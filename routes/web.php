<?php

use App\Http\Controllers\SsoController;
use App\Http\Controllers\ConfirmationController;
use App\Http\Controllers\DataController;
use App\Http\Controllers\EnumerationController;
use App\Http\Controllers\ErrorSummaryController;
use App\Http\Controllers\FinalNumberController;
use App\Http\Controllers\InputController;
use App\Http\Controllers\PhenomenaController;
use App\Http\Controllers\PredictionController;
use App\Http\Controllers\TargetSampleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WebAdminController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::get('/sso/callback', [SsoController::class, 'callback'])->name('sso.callback.index');
Route::get('/sso/login', [SsoController::class, 'redirect'])->name('sso.login.index');
Route::get('/webadmin', [WebAdminController::class, 'login'])->name('webadmin.login.index');

Route::middleware(['auth', 'verified'])->group(function () {

    Route::get('/sso/logout', [SsoController::class, 'logout'])->name('sso.logout.index');

    Route::get('dashboard', [DataController::class, 'showDashboard'])->name('data.dashboard.index');

    Route::get('data', [DataController::class, 'showRawDataPage'])->name('data.index');
    Route::get('data/input', [DataController::class, 'getInputData'])->name('data.raw.index');
    Route::get('status/data/{type}', [DataController::class, 'getUploadStatusData'])->name('data.status.index');

    Route::get('map', [DataController::class, 'showMap'])->name('data.map.index');

    //group route only for adminprov
    Route::middleware('role:adminprov')->group(function () {
        Route::get('input/upload', [InputController::class, 'showUploadForm'])->name('upload.index');
        Route::get('input/template', [InputController::class, 'downloadInputTemplate'])->name('upload.template.index');
        Route::post('input/upload', [InputController::class, 'storeUpload'])->name('upload.store.index');
        Route::post('input/download', [InputController::class, 'downloadFile'])->name('upload.file.download');

        Route::get('user', [UserController::class, 'showUserPage'])->name('user.page.index');
        Route::post('user', [UserController::class, 'store'])->name('user.page.store');
        Route::delete('user/{id}', [UserController::class, 'delete'])->name('user.delete.index');
        Route::get('user/data', [UserController::class, 'getUserData'])->name('user.data.index');
    });

    Route::get('indicator', [DataController::class, 'showIndicatorValuesPage'])->name('indicator.table.index');
    Route::get('indicator/data', [DataController::class, 'getIndicatorValuesData'])->name('indicator.data.index');

    Route::get('error-summaries', [ErrorSummaryController::class, 'showErrorSummaryPage'])->name('error_summaries.page.index');
    Route::get('error-summaries/data', [ErrorSummaryController::class, 'getErrorSummaryData'])->name('error_summaries.data.index');

    Route::get('enumeration', [EnumerationController::class, 'showEnumerationPage'])->name('enumeration.page.index');
    Route::get('enumeration/data', [EnumerationController::class, 'getEnumerationData'])->name('enumeration.data.index');

    Route::get('sample/template', [TargetSampleController::class, 'downloadTemplate'])->name('sample.template.index');
    Route::get('sample/data', [TargetSampleController::class, 'getTargetSampleData'])->name('sample.data.index');
    Route::post('sample/upload', [TargetSampleController::class, 'storeUpload'])->name('sample.upload.store');

    Route::get('final/template', [FinalNumberController::class, 'downloadTemplate'])->name('final.template.index');
    Route::get('final/data', [FinalNumberController::class, 'getFinalNumberData'])->name('final.data.index');
    Route::post('final/upload', [FinalNumberController::class, 'storeUpload'])->name('final.upload.store');

    Route::get('prediction', [PredictionController::class, 'showPredictionPage'])->name('prediction.page.index');
    Route::get('prediction/data', [PredictionController::class, 'getPredictionData'])->name('prediction.data.index');

    Route::get('confirmation', [ConfirmationController::class, 'showConfirmationPage'])->name('confirmation.page.index');
    Route::get('confirmation/data', [ConfirmationController::class, 'getConfirmationData'])->name('confirmation.data.index');
    Route::post('confirmation', [ConfirmationController::class, 'confirm'])->name('confirmation.confirm.store');
    Route::post('approve', [ConfirmationController::class, 'approve'])->name('confirmation.approve.store');

    Route::get('phenomena', [PhenomenaController::class, 'showPhenomenaPage'])->name('phenomena.page.index');
    Route::get('phenomena/data', [PhenomenaController::class, 'getPhenomenaData'])->name('phenomena.data.index');
    Route::post('phenomena', [PhenomenaController::class, 'storePhenomena'])->name('phenomena.store.store');
});

require __DIR__ . '/settings.php';
