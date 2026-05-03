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
        Route::put('/{id}',             [AdminFestivalController::class, 'update'])->name('update');
        Route::post('/{id}/toggle',     [AdminFestivalController::class, 'toggle'])->name('toggle');
        Route::post('/{id}/bump-year',  [AdminFestivalController::class, 'bumpYear'])->name('bump-year');
    });
