<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AnnouncementController as AdminAnnouncementController;
use App\Http\Controllers\Photographer\AnnouncementFeedController;
use App\Http\Controllers\Public\AnnouncementController as PublicAnnouncementController;

/*
|--------------------------------------------------------------------------
| Announcements routes
|--------------------------------------------------------------------------
|
| Three audience-scoped surfaces:
|   • Admin CRUD (under /admin)
|   • Photographer feed (under /photographer, photographer auth)
|   • Public/customer feed (root paths, no auth)
|
| Included from web.php so the same middleware stack as the rest of the
| app applies (csrf, session, locale).
*/

/* ═══════════════════════════════════════════════════════════════════════
 *  Admin CRUD
 * ═══════════════════════════════════════════════════════════════════════ */
Route::prefix('admin/announcements')
    ->name('admin.announcements.')
    ->middleware(['admin', 'no.back'])
    ->group(function () {
        Route::get('/',                  [AdminAnnouncementController::class, 'index'])->name('index');
        Route::get('/create',            [AdminAnnouncementController::class, 'create'])->name('create');
        Route::post('/',                 [AdminAnnouncementController::class, 'store'])->name('store');
        Route::get('/{id}/edit',         [AdminAnnouncementController::class, 'edit'])->name('edit');
        Route::put('/{id}',              [AdminAnnouncementController::class, 'update'])->name('update');
        Route::delete('/{id}',           [AdminAnnouncementController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/restore',     [AdminAnnouncementController::class, 'restore'])->name('restore');

        // Quick actions
        Route::post('/{id}/publish',     [AdminAnnouncementController::class, 'publish'])->name('publish');
        Route::post('/{id}/archive',     [AdminAnnouncementController::class, 'archive'])->name('archive');
        Route::post('/{id}/pin',         [AdminAnnouncementController::class, 'pin'])->name('pin');

        // Attachments
        Route::post('/{id}/attachments',                 [AdminAnnouncementController::class, 'storeAttachment'])->name('attachments.store');
        Route::delete('/attachments/{attachmentId}',     [AdminAnnouncementController::class, 'destroyAttachment'])->name('attachments.destroy');
    });

/* ═══════════════════════════════════════════════════════════════════════
 *  Photographer feed (photographer auth required)
 * ═══════════════════════════════════════════════════════════════════════ */
Route::prefix('photographer/announcements')
    ->name('photographer.announcements.')
    ->middleware(['photographer'])
    ->group(function () {
        Route::get('/',         [AnnouncementFeedController::class, 'index'])->name('index');
        Route::get('/{slug}',   [AnnouncementFeedController::class, 'show'])->name('show');
    });

/* ═══════════════════════════════════════════════════════════════════════
 *  Public/customer feed (no auth required)
 * ═══════════════════════════════════════════════════════════════════════ */
Route::prefix('announcements')
    ->name('announcements.')
    ->group(function () {
        Route::get('/',         [PublicAnnouncementController::class, 'index'])->name('index');
        Route::get('/{slug}',   [PublicAnnouncementController::class, 'show'])->name('show');
    });
