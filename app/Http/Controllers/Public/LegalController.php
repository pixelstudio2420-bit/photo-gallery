<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\LegalPage;

/**
 * Public-facing legal pages — Privacy Policy, Terms of Service, Refund Policy, etc.
 *
 * Pages are edited in the admin CMS (Admin\LegalPageController). Only
 * `is_published = true` rows are visible here.
 */
class LegalController extends Controller
{
    /**
     * Show a legal page by slug.
     *
     * Also passes the full list of published pages so the view can render
     * a cross-nav between them ("see also: Terms of Service").
     */
    public function show(string $slug)
    {
        $page = LegalPage::published()->where('slug', $slug)->firstOrFail();

        $otherPages = LegalPage::published()
            ->where('id', '!=', $page->id)
            ->orderByRaw("CASE slug WHEN 'privacy-policy' THEN 3 WHEN 'terms-of-service' THEN 2 WHEN 'refund-policy' THEN 1 ELSE 0 END DESC")
            ->orderBy('title')
            ->get(['slug', 'title']);

        return view('public.legal.show', compact('page', 'otherPages'));
    }

    /**
     * Named aliases for the three canonical pages — keeps friendly URLs like
     * /privacy-policy and /terms-of-service even though the underlying data
     * model supports arbitrary slugs under /legal/{slug}.
     */
    public function privacyPolicy() { return $this->show('privacy-policy'); }
    public function termsOfService() { return $this->show('terms-of-service'); }
    public function refundPolicy()  { return $this->show('refund-policy'); }
}
