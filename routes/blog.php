<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Public\BlogController;
use App\Http\Controllers\Public\AffiliateRedirectController;

/*
|--------------------------------------------------------------------------
| Blog Routes
|--------------------------------------------------------------------------
|
| This file is included from web.php and defines all routes for the
| public blog, affiliate redirects, and admin blog management.
|
*/

/* ═══════════════════════════════════════════════════════════════════════
 *  Public blog routes (no auth required)
 * ═══════════════════════════════════════════════════════════════════════ */
Route::prefix('blog')->name('blog.')->group(function () {
    Route::get('/',               [BlogController::class, 'index'])->name('index');
    Route::get('/search',         [BlogController::class, 'search'])->name('search');
    Route::get('/feed',           [BlogController::class, 'feed'])->name('feed');
    Route::get('/category/{slug}', [BlogController::class, 'category'])->name('category');
    Route::get('/tag/{slug}',     [BlogController::class, 'tag'])->name('tag');
    Route::get('/{slug}',         [BlogController::class, 'show'])->name('show');
});

/* ═══════════════════════════════════════════════════════════════════════
 *  Affiliate redirect (short URL)
 * ═══════════════════════════════════════════════════════════════════════ */
Route::get('/go/{slug}', [AffiliateRedirectController::class, 'redirect'])->name('affiliate.redirect');

/* ═══════════════════════════════════════════════════════════════════════
 *  Admin blog routes (inside admin middleware)
 * ═══════════════════════════════════════════════════════════════════════ */
Route::prefix('admin/blog')->name('admin.blog.')->middleware(['admin', 'no.back'])->group(function () {

    // ── Posts ──
    Route::resource('posts', \App\Http\Controllers\Admin\BlogPostController::class);
    Route::post('posts/{id}/toggle-featured', [\App\Http\Controllers\Admin\BlogPostController::class, 'toggleFeatured'])->name('posts.toggle-featured');
    Route::post('posts/{id}/toggle-status',   [\App\Http\Controllers\Admin\BlogPostController::class, 'toggleStatus'])->name('posts.toggle-status');
    Route::post('posts/bulk-action',           [\App\Http\Controllers\Admin\BlogPostController::class, 'bulkAction'])->name('posts.bulk-action');
    Route::post('posts/{id}/duplicate',        [\App\Http\Controllers\Admin\BlogPostController::class, 'duplicate'])->name('posts.duplicate');

    // Tiptap inline image upload (toolbar / drag-drop / paste)
    Route::post('posts/upload-inline-image',   [\App\Http\Controllers\Admin\BlogPostController::class, 'uploadInlineImage'])->name('posts.upload-inline-image');

    // AI endpoints for posts
    Route::post('posts/ai/generate',          [\App\Http\Controllers\Admin\BlogPostController::class, 'aiGenerate'])->name('posts.ai.generate');
    Route::post('posts/ai/rewrite',           [\App\Http\Controllers\Admin\BlogPostController::class, 'aiRewrite'])->name('posts.ai.rewrite');
    Route::post('posts/ai/summarize',         [\App\Http\Controllers\Admin\BlogPostController::class, 'aiSummarize'])->name('posts.ai.summarize');
    Route::post('posts/ai/seo-analyze',       [\App\Http\Controllers\Admin\BlogPostController::class, 'aiSeoAnalyze'])->name('posts.ai.seo-analyze');
    Route::post('posts/ai/suggest-keywords',  [\App\Http\Controllers\Admin\BlogPostController::class, 'aiSuggestKeywords'])->name('posts.ai.suggest-keywords');
    Route::post('posts/ai/generate-meta',     [\App\Http\Controllers\Admin\BlogPostController::class, 'aiGenerateMeta'])->name('posts.ai.generate-meta');

    // ── Categories ──
    Route::resource('categories', \App\Http\Controllers\Admin\BlogCategoryController::class)->except(['show']);
    Route::post('categories/{id}/toggle-active', [\App\Http\Controllers\Admin\BlogCategoryController::class, 'toggleActive'])->name('categories.toggle-active');

    // ── Tags ──
    Route::get('tags/suggest', [\App\Http\Controllers\Admin\BlogTagController::class, 'suggest'])->name('tags.suggest');
    Route::get('tags/search', [\App\Http\Controllers\Admin\BlogTagController::class, 'suggest'])->name('tags.search');
    Route::post('tags/bulk-delete', [\App\Http\Controllers\Admin\BlogTagController::class, 'bulkDelete'])->name('tags.bulk-delete');
    Route::resource('tags', \App\Http\Controllers\Admin\BlogTagController::class)->except(['create', 'show']);

    // ── Affiliate ──
    Route::get('affiliate/dashboard', [\App\Http\Controllers\Admin\BlogAffiliateController::class, 'dashboard'])->name('affiliate.dashboard');
    Route::resource('affiliate', \App\Http\Controllers\Admin\BlogAffiliateController::class);
    Route::get('affiliate/{id}/stats', [\App\Http\Controllers\Admin\BlogAffiliateController::class, 'stats'])->name('affiliate.stats');

    // CTA Buttons
    Route::get('cta-buttons',       [\App\Http\Controllers\Admin\BlogAffiliateController::class, 'ctaButtons'])->name('cta.index');
    Route::post('cta-buttons',      [\App\Http\Controllers\Admin\BlogAffiliateController::class, 'storeCta'])->name('cta.store');
    Route::put('cta-buttons/{id}',  [\App\Http\Controllers\Admin\BlogAffiliateController::class, 'updateCta'])->name('cta.update');
    Route::delete('cta-buttons/{id}', [\App\Http\Controllers\Admin\BlogAffiliateController::class, 'destroyCta'])->name('cta.destroy');

    // ── AI Tools ──
    Route::prefix('ai')->name('ai.')->group(function () {
        Route::get('/',                  [\App\Http\Controllers\Admin\BlogAiController::class, 'index'])->name('index');
        Route::post('/generate-article', [\App\Http\Controllers\Admin\BlogAiController::class, 'generateArticle'])->name('generate');
        Route::post('/summarize',        [\App\Http\Controllers\Admin\BlogAiController::class, 'summarize'])->name('summarize');
        Route::post('/rewrite',          [\App\Http\Controllers\Admin\BlogAiController::class, 'rewrite'])->name('rewrite');
        Route::post('/research',         [\App\Http\Controllers\Admin\BlogAiController::class, 'research'])->name('research');
        Route::post('/keyword-suggest',  [\App\Http\Controllers\Admin\BlogAiController::class, 'keywordSuggest'])->name('keywords');
        Route::post('/seo-analyze',      [\App\Http\Controllers\Admin\BlogAiController::class, 'seoAnalyze'])->name('seo');
        Route::get('/history',           [\App\Http\Controllers\Admin\BlogAiController::class, 'taskHistory'])->name('history');
        Route::get('/history/{id}',      [\App\Http\Controllers\Admin\BlogAiController::class, 'taskShow'])->name('history.show');
        Route::get('/cost-report',       [\App\Http\Controllers\Admin\BlogAiController::class, 'costReport'])->name('cost');
        Route::post('/settings',         [\App\Http\Controllers\Admin\BlogAiController::class, 'saveSettings'])->name('settings');
        Route::post('/process',          [\App\Http\Controllers\Admin\BlogAiController::class, 'process'])->name('process');
        // Toggles: enable/disable AI tools + providers
        Route::get('/toggles',           [\App\Http\Controllers\Admin\BlogAiController::class, 'toggles'])->name('toggles');
        Route::post('/toggles',          [\App\Http\Controllers\Admin\BlogAiController::class, 'saveToggles'])->name('toggles.save');
    });

    // ── News Aggregation ──
    Route::prefix('news')->name('news.')->group(function () {
        Route::get('/',                   [\App\Http\Controllers\Admin\BlogNewsController::class, 'index'])->name('index');
        Route::post('/sources',           [\App\Http\Controllers\Admin\BlogNewsController::class, 'storeSource'])->name('sources.store');
        Route::put('/sources/{id}',       [\App\Http\Controllers\Admin\BlogNewsController::class, 'updateSource'])->name('sources.update');
        Route::delete('/sources/{id}',    [\App\Http\Controllers\Admin\BlogNewsController::class, 'deleteSource'])->name('sources.delete');
        Route::post('/sources/{id}/fetch', [\App\Http\Controllers\Admin\BlogNewsController::class, 'fetchNow'])->name('sources.fetch');
        Route::post('/fetch-all',         [\App\Http\Controllers\Admin\BlogNewsController::class, 'fetchAll'])->name('fetch-all');
        Route::get('/items',              [\App\Http\Controllers\Admin\BlogNewsController::class, 'items'])->name('items');
        Route::get('/items/{id}',         [\App\Http\Controllers\Admin\BlogNewsController::class, 'itemShow'])->name('items.show');
        Route::post('/items/{id}/summarize', [\App\Http\Controllers\Admin\BlogNewsController::class, 'summarizeItem'])->name('items.summarize');
        Route::post('/items/{id}/publish',   [\App\Http\Controllers\Admin\BlogNewsController::class, 'publishItem'])->name('items.publish');
        Route::post('/items/{id}/dismiss',   [\App\Http\Controllers\Admin\BlogNewsController::class, 'dismissItem'])->name('items.dismiss');
        Route::post('/items/bulk-action',    [\App\Http\Controllers\Admin\BlogNewsController::class, 'bulkAction'])->name('items.bulk-action');
        Route::post('/bulk-action',          [\App\Http\Controllers\Admin\BlogNewsController::class, 'bulkAction'])->name('bulk-action');
    });
});
