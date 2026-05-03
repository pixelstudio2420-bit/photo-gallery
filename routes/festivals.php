<?php

use App\Http\Controllers\Admin\FestivalController as AdminFestivalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Festival routes
|--------------------------------------------------------------------------
|
| Admin-only — festivals themselves are seeded canonically (Songkran,
| Loy Krathong, NYE, etc.) and admins only tweak content, dates, theme.
|
| The customer-facing dismissal endpoint lives in web.php alongside
| the announcement dismissal so both popup endpoints sit together.
*/

Route::prefix('admin/festivals')
    ->name('admin.festivals.')
    ->middleware(['admin', 'no.back'])
    ->group(function () {
        Route::get('/',                 [AdminFestivalController::class, 'index'])->name('index');
        Route::post('/',                [AdminFestivalController::class, 'store'])->name('store');
        Route::put('/{id}',             [AdminFestivalController::class, 'update'])->name('update');
        Route::delete('/{id}',          [AdminFestivalController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/toggle',     [AdminFestivalController::class, 'toggle'])->name('toggle');
        Route::post('/{id}/bump-year',  [AdminFestivalController::class, 'bumpYear'])->name('bump-year');
        Route::post('/{id}/duplicate',  [AdminFestivalController::class, 'duplicate'])->name('duplicate');

        // Calendar sync — re-apply authoritative dates from the
        // multi-year table (admin-triggered on top of the monthly cron)
        Route::post('/sync',            [AdminFestivalController::class, 'syncFromCalendar'])->name('sync');

        // Google Calendar integration
        Route::post('/google-config',   [AdminFestivalController::class, 'saveGoogleConfig'])->name('google-config');
        Route::post('/google-test',     [AdminFestivalController::class, 'testGoogleConnection'])->name('google-test');
    });
