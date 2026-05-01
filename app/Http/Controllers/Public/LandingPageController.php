<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\SeoLandingPage;
use App\Services\Seo\PSeoSchemaBuilder;
use Illuminate\Http\Request;

/**
 * Renders pSEO-generated (and admin-created) landing pages.
 *
 * One catch-all route maps slugs in seo_landing_pages.slug to this
 * controller. The page row already holds the resolved title / meta
 * description / body, so this controller is mostly a thin renderer
 * that:
 *
 *   1. Looks up the page by slug (404 when missing or unpublished)
 *   2. Bumps view_count + last_viewed_at (denormalized counter)
 *   3. Pulls related data based on type (events for location pages,
 *      photographers for category pages, etc.) so the rendered page
 *      has real items to list, not just generated copy
 *   4. Builds the schema.org JSON-LD payload
 *   5. Renders public.landing.show
 */
class LandingPageController extends Controller
{
    public function show(Request $request, string $slug)
    {
        $page = SeoLandingPage::where('slug', $slug)
            ->where('is_published', true)
            ->first();

        if (!$page) {
            abort(404);
        }

        // Bump view stats — denormalized counter, no page-load delay.
        // increment() runs a single UPDATE without touching updated_at.
        try {
            SeoLandingPage::where('id', $page->id)->update([
                'view_count'     => $page->view_count + 1,
                'last_viewed_at' => now(),
            ]);
        } catch (\Throwable) {
            // Counter is denormalized — never block the render.
        }

        // Pull related data based on page type. Each branch returns a
        // small Collection that the Blade view renders as a list/grid.
        $relatedItems = $this->loadRelated($page);

        // Schema.org JSON-LD — pre-rendered if cached, else build now.
        $schemaJson = $page->schema_json
            ?: app(PSeoSchemaBuilder::class)->buildFor($page, $relatedItems);

        return view('public.landing.show', compact('page', 'relatedItems', 'schemaJson'));
    }

    /**
     * Pull related data items based on the page's type + source_meta.
     * Returns an empty collection if no source data is referenced.
     */
    private function loadRelated(SeoLandingPage $page)
    {
        $meta = $page->source_meta ?? [];

        return match ($page->type) {
            'location' => $this->fetchEventsByProvince($meta['province_id'] ?? null),
            'category' => $this->fetchEventsByCategory($meta['category_id'] ?? null),
            'combo'    => $this->fetchEventsByCategoryAndProvince(
                $meta['category_id'] ?? null,
                $meta['province_id'] ?? null
            ),
            'photographer'  => $this->fetchPhotographerEvents($meta['photographer_id'] ?? null),
            'event_archive' => $this->fetchAllRecentEvents(),
            'event'         => $this->fetchEventPhotos($meta['event_id'] ?? null),
            default         => collect(),
        };
    }

    /**
     * For per-event landing pages: load up to 24 photos from the event
     * itself (instead of related events). The blade template detects
     * type='event' and renders these as a masonry photo grid with a
     * link to the full /events/{slug} page.
     */
    private function fetchEventPhotos(?int $eventId)
    {
        if (!$eventId) return collect();
        return \App\Models\EventPhoto::where('event_id', $eventId)
            ->where('status', '!=', 'deleted')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit(24)
            ->get(['id', 'event_id', 'thumbnail_path', 'watermarked_path', 'caption', 'width', 'height']);
    }

    /**
     * NOTE: `display_name` lives on `photographer_profiles`, not on
     * `auth_users` (the table the `photographer()` relation points to).
     * Eager-load via `photographerProfile` to avoid an
     *   "column display_name does not exist" 42703 error
     * which previously bubbled up as a 500 on every category/combo
     * landing page.
     */
    private function fetchEventsByProvince(?int $provinceId)
    {
        if (!$provinceId) return collect();
        return \App\Models\Event::where('province_id', $provinceId)
            ->where('status', 'active')
            ->where('visibility', 'public')
            ->with(['category:id,name,slug', 'photographerProfile:user_id,display_name'])
            ->orderByDesc('shoot_date')
            ->limit(24)
            ->get();
    }

    private function fetchEventsByCategory(?int $categoryId)
    {
        if (!$categoryId) return collect();
        return \App\Models\Event::where('category_id', $categoryId)
            ->where('status', 'active')
            ->where('visibility', 'public')
            ->with(['photographerProfile:user_id,display_name'])
            ->orderByDesc('shoot_date')
            ->limit(24)
            ->get();
    }

    private function fetchEventsByCategoryAndProvince(?int $categoryId, ?int $provinceId)
    {
        if (!$categoryId || !$provinceId) return collect();
        return \App\Models\Event::where('category_id', $categoryId)
            ->where('province_id', $provinceId)
            ->where('status', 'active')
            ->where('visibility', 'public')
            ->with(['photographerProfile:user_id,display_name'])
            ->orderByDesc('shoot_date')
            ->limit(24)
            ->get();
    }

    private function fetchPhotographerEvents(?int $photographerId)
    {
        if (!$photographerId) return collect();
        return \App\Models\Event::where('photographer_id', $photographerId)
            ->where('status', 'active')
            ->where('visibility', 'public')
            ->orderByDesc('shoot_date')
            ->limit(24)
            ->get();
    }

    private function fetchAllRecentEvents()
    {
        return \App\Models\Event::where('status', 'active')
            ->where('visibility', 'public')
            ->with(['category:id,name', 'photographerProfile:user_id,display_name'])
            ->orderByDesc('shoot_date')
            ->limit(48)
            ->get();
    }
}
