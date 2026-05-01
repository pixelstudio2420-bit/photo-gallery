<?php

namespace App\Services\Seo;

use App\Models\SeoLandingPage;
use App\Models\SeoPageTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Programmatic-SEO engine.
 *
 * Walks the source data (events, photographers, categories, provinces)
 * and produces SeoLandingPage rows by substituting variables into the
 * patterns defined on each SeoPageTemplate.
 *
 * Page types supported:
 *   • location      — /events-in-{province-slug}
 *   • category      — /{category-slug}-photographers
 *   • combo         — /{category-slug}-photographers-in-{province-slug}
 *   • photographer  — /photographers/{slug} enhanced
 *   • event_archive — /events-archive
 *
 * Each generator method:
 *   1. Pulls source rows from the DB
 *   2. Filters by template.min_data_points (thin-content guard)
 *   3. Builds a slug + variable bag
 *   4. Renders title + meta + body via the template patterns
 *   5. updateOrCreate on seo_landing_pages keyed by slug
 *
 * The is_locked flag on landing-page rows protects admin-edited pages
 * from being stomped on the next regen pass.
 */
class PSeoService
{
    /* ═══════════════════════════════════════════════════════════════
     * Public API — bulk generation entry points
     * ═══════════════════════════════════════════════════════════════ */

    /**
     * Run every active template's generator. Returns a per-type summary
     * of created / updated / skipped counts so the console command can
     * print a useful table.
     */
    public function generateAll(): array
    {
        $results = [];
        foreach (SeoPageTemplate::where('is_auto_enabled', true)->get() as $template) {
            $results[$template->type] = $this->generateForTemplate($template);
        }
        return $results;
    }

    /**
     * Run a single template's generator. Routes to the right type-
     * specific method based on template.type.
     */
    public function generateForTemplate(SeoPageTemplate $template): array
    {
        return match ($template->type) {
            SeoPageTemplate::TYPE_LOCATION      => $this->generateLocationPages($template),
            SeoPageTemplate::TYPE_CATEGORY      => $this->generateCategoryPages($template),
            SeoPageTemplate::TYPE_COMBO         => $this->generateComboPages($template),
            SeoPageTemplate::TYPE_PHOTOGRAPHER  => $this->generatePhotographerPages($template),
            SeoPageTemplate::TYPE_EVENT_ARCHIVE => $this->generateEventArchive($template),
            default                              => ['created' => 0, 'updated' => 0, 'skipped' => 0],
        };
    }

    /* ═══════════════════════════════════════════════════════════════
     * Generators per page type
     * ═══════════════════════════════════════════════════════════════ */

    /**
     * Location pages — one per Thai province that has ≥ N events.
     *
     * URL: /events-in-{province-slug}
     * e.g. /events-in-bangkok, /events-in-chiang-mai
     */
    public function generateLocationPages(SeoPageTemplate $template): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        // Pull provinces with their event counts in one query.
        $rows = DB::table('thai_provinces as p')
            ->leftJoin('event_events as e', 'e.province_id', '=', 'p.id')
            ->select('p.id', 'p.name_th', 'p.name_en')
            ->selectRaw('COUNT(DISTINCT e.id) FILTER (WHERE e.status = ? AND e.visibility = ?) as event_count', ['active', 'public'])
            ->selectRaw('COUNT(DISTINCT e.photographer_id) FILTER (WHERE e.status = ? AND e.visibility = ?) as photographer_count', ['active', 'public'])
            ->groupBy('p.id', 'p.name_th', 'p.name_en')
            ->get();

        foreach ($rows as $row) {
            if ((int) $row->event_count < $template->min_data_points) {
                $stats['skipped']++;
                continue;
            }

            $slug = 'events-in-' . $this->slugify($row->name_en);
            $vars = [
                'location'       => $row->name_th,
                'location_en'    => $row->name_en,
                'event_count'    => (int) $row->event_count,
                'photographer_count' => (int) $row->photographer_count,
                'brand'          => config('app.name', 'Loadroop'),
                'year'           => now()->year,
            ];

            $page = $this->upsertPage($template, $slug, $vars, [
                'province_id'  => $row->id,
                'data_count'   => (int) $row->event_count,
            ]);

            $stats[$page->wasRecentlyCreated ? 'created' : 'updated']++;
        }

        return $stats;
    }

    /**
     * Category pages — one per event_category that has ≥ N events.
     *
     * URL: /{category-slug}-photographers
     */
    public function generateCategoryPages(SeoPageTemplate $template): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        $rows = DB::table('event_categories as c')
            ->leftJoin('event_events as e', 'e.category_id', '=', 'c.id')
            ->select('c.id', 'c.name', 'c.slug')
            ->selectRaw('COUNT(DISTINCT e.id) FILTER (WHERE e.status = ? AND e.visibility = ?) as event_count', ['active', 'public'])
            ->selectRaw('COUNT(DISTINCT e.photographer_id) FILTER (WHERE e.status = ? AND e.visibility = ?) as photographer_count', ['active', 'public'])
            ->where('c.status', '!=', 'inactive')
            ->groupBy('c.id', 'c.name', 'c.slug')
            ->get();

        foreach ($rows as $row) {
            if ((int) $row->event_count < $template->min_data_points) {
                $stats['skipped']++;
                continue;
            }

            $slug = $this->slugify($row->slug ?? $row->name) . '-photographers';
            $vars = [
                'category'           => $this->extractThai($row->name),
                'category_full'      => $row->name,
                'event_count'        => (int) $row->event_count,
                'photographer_count' => (int) $row->photographer_count,
                'brand'              => config('app.name', 'Loadroop'),
                'year'               => now()->year,
            ];

            $page = $this->upsertPage($template, $slug, $vars, [
                'category_id' => $row->id,
                'data_count'  => (int) $row->event_count,
            ]);

            $stats[$page->wasRecentlyCreated ? 'created' : 'updated']++;
        }

        return $stats;
    }

    /**
     * Combo pages — every (category, province) pair that has ≥ N events.
     *
     * URL: /{category-slug}-photographers-in-{province-slug}
     * Highest-traffic page type — these match long-tail intent like
     * "wedding photographers in chiang mai".
     */
    public function generateComboPages(SeoPageTemplate $template): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        $rows = DB::table('event_events as e')
            ->join('event_categories as c', 'c.id', '=', 'e.category_id')
            ->join('thai_provinces as p', 'p.id', '=', 'e.province_id')
            ->where('e.status', 'active')
            ->where('e.visibility', 'public')
            ->where('c.status', '!=', 'inactive')
            ->select('c.id as category_id', 'c.name as category_name', 'c.slug as category_slug',
                     'p.id as province_id', 'p.name_th as province_th', 'p.name_en as province_en')
            ->selectRaw('COUNT(DISTINCT e.id) as event_count')
            ->selectRaw('COUNT(DISTINCT e.photographer_id) as photographer_count')
            ->groupBy('c.id', 'c.name', 'c.slug', 'p.id', 'p.name_th', 'p.name_en')
            ->get();

        foreach ($rows as $row) {
            if ((int) $row->event_count < $template->min_data_points) {
                $stats['skipped']++;
                continue;
            }

            $slug = $this->slugify($row->category_slug ?? $row->category_name)
                  . '-photographers-in-' . $this->slugify($row->province_en);
            $vars = [
                'category'           => $this->extractThai($row->category_name),
                'category_full'      => $row->category_name,
                'location'           => $row->province_th,
                'location_en'        => $row->province_en,
                'event_count'        => (int) $row->event_count,
                'photographer_count' => (int) $row->photographer_count,
                'brand'              => config('app.name', 'Loadroop'),
                'year'               => now()->year,
            ];

            $page = $this->upsertPage($template, $slug, $vars, [
                'category_id' => $row->category_id,
                'province_id' => $row->province_id,
                'data_count'  => (int) $row->event_count,
            ]);

            $stats[$page->wasRecentlyCreated ? 'created' : 'updated']++;
        }

        return $stats;
    }

    /**
     * Photographer profile pages — enhanced SEO for active photographers.
     *
     * URL: /photographers/{slug}
     * NOTE: this complements (doesn't replace) the existing photographer
     * profile route. We just write SEO meta into seo_landing_pages so
     * the controller can pull from one place.
     */
    public function generatePhotographerPages(SeoPageTemplate $template): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        $rows = DB::table('photographer_profiles as pp')
            ->leftJoin('event_events as e', 'e.photographer_id', '=', 'pp.user_id')
            ->where('pp.status', 'approved')
            ->select('pp.user_id', 'pp.display_name', 'pp.bio')
            ->selectRaw('COUNT(DISTINCT e.id) FILTER (WHERE e.status = ?) as event_count', ['active'])
            ->groupBy('pp.user_id', 'pp.display_name', 'pp.bio')
            ->get();

        foreach ($rows as $row) {
            if ((int) $row->event_count < $template->min_data_points) {
                $stats['skipped']++;
                continue;
            }

            $slug = 'photographers/' . $this->slugify($row->display_name . '-' . $row->user_id);
            $vars = [
                'name'        => $row->display_name,
                'event_count' => (int) $row->event_count,
                'bio'         => Str::limit($row->bio ?? '', 100),
                'brand'       => config('app.name', 'Loadroop'),
                'year'        => now()->year,
            ];

            $page = $this->upsertPage($template, $slug, $vars, [
                'photographer_id' => $row->user_id,
                'data_count'      => (int) $row->event_count,
            ]);

            $stats[$page->wasRecentlyCreated ? 'created' : 'updated']++;
        }

        return $stats;
    }

    /**
     * Event archive — single page listing all events grouped by month.
     */
    public function generateEventArchive(SeoPageTemplate $template): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        $count = DB::table('event_events')
            ->where('status', 'active')
            ->where('visibility', 'public')
            ->count();

        if ($count < $template->min_data_points) {
            $stats['skipped']++;
            return $stats;
        }

        $vars = [
            'event_count' => $count,
            'brand'       => config('app.name', 'Loadroop'),
            'year'        => now()->year,
        ];

        $page = $this->upsertPage($template, 'events-archive', $vars, [
            'data_count' => $count,
        ]);

        $stats[$page->wasRecentlyCreated ? 'created' : 'updated']++;
        return $stats;
    }

    /* ═══════════════════════════════════════════════════════════════
     * Internal helpers
     * ═══════════════════════════════════════════════════════════════ */

    /**
     * Substitute {variable} placeholders in a pattern with the values
     * from $vars. Missing variables become empty string so half-rendered
     * patterns don't accidentally embed "{location}" into live SEO meta.
     */
    public function renderPattern(?string $pattern, array $vars): string
    {
        if ($pattern === null || $pattern === '') return '';

        return preg_replace_callback('/\{(\w+)\}/', function ($m) use ($vars) {
            $key = $m[1];
            return isset($vars[$key]) ? (string) $vars[$key] : '';
        }, $pattern);
    }

    /**
     * Persist (or update) a landing-page row from a template + variable
     * bag. Respects is_locked: locked rows are NOT overwritten on
     * regenerate, only their view stats are touched.
     *
     * Auto-themes the page based on category-name keywords so wedding
     * pages get pink, sport pages get blue, etc. Existing rows keep
     * their admin-set theme (auto-theme only fills the gap on first
     * generate).
     */
    private function upsertPage(SeoPageTemplate $template, string $slug, array $vars, array $sourceMeta): SeoLandingPage
    {
        $existing = SeoLandingPage::where('slug', $slug)->first();

        // Hand-locked page → leave it alone.
        if ($existing && $existing->is_locked) {
            $existing->wasRecentlyCreated = false;
            return $existing;
        }

        $title = Str::limit($this->renderPattern($template->title_pattern, $vars), 200, '');
        $desc  = Str::limit($this->renderPattern($template->meta_description_pattern, $vars), 250, '');
        $h1    = $this->renderPattern($template->h1_pattern, $vars);
        $body  = $this->renderPattern($template->body_template, $vars);

        $payload = [
            'template_id'      => $template->id,
            'type'             => $template->type,
            'title'            => $title ?: $h1 ?: $slug,
            'meta_description' => $desc,
            'h1'               => $h1,
            'body_html'        => $body,
            'source_meta'      => $sourceMeta,
            'is_published'     => true,
            'regenerated_at'   => now(),
        ];

        // Auto-theme — only set on FIRST generate, never overwrite the
        // admin's manual theme choice. Maps category/keywords → theme.
        if (!$existing || empty($existing->theme) || $existing->theme === 'default') {
            $payload['theme'] = $this->autoTheme($template, $vars);
        }

        return SeoLandingPage::updateOrCreate(['slug' => $slug], $payload);
    }

    /**
     * Pick a theme based on the page type + variables. Looks at the
     * category name first (most specific), then page type, falling
     * back to 'photography' for the marketplace-wide default.
     *
     * Themes available: default / wedding / sport / concert /
     * corporate / portrait / festival / photography.
     */
    private function autoTheme(SeoPageTemplate $template, array $vars): string
    {
        $hint = strtolower((string) (
            $vars['category'] ?? $vars['category_full'] ?? ''
        ));

        return match (true) {
            str_contains($hint, 'wedding') || str_contains($hint, 'แต่ง')      => 'wedding',
            str_contains($hint, 'sport')   || str_contains($hint, 'กีฬา')      => 'sport',
            str_contains($hint, 'concert') || str_contains($hint, 'บันเทิง')   => 'concert',
            str_contains($hint, 'corporate') || str_contains($hint, 'องค์กร') => 'corporate',
            str_contains($hint, 'portrait') || str_contains($hint, 'พอร์ต')    => 'portrait',
            str_contains($hint, 'festival') || str_contains($hint, 'เทศกาล')   => 'festival',
            str_contains($hint, 'education') || str_contains($hint, 'การศึกษา')=> 'corporate',
            $template->type === 'photographer'                                  => 'portrait',
            $template->type === 'event_archive'                                 => 'photography',
            default                                                             => 'photography',
        };
    }

    /**
     * URL-safe slug. Falls back to a stripped Thai-text slug when ASCII
     * is empty so Thai-only category names still produce something.
     */
    private function slugify(?string $text): string
    {
        if (!$text) return Str::random(6);
        $slug = Str::slug($text, '-', 'en');
        if ($slug === '') {
            // Thai input → strip non-alpha for a fallback. Better than
            // nothing; admins can override the resulting slug manually.
            $slug = preg_replace('/[^a-z0-9-]/i', '', strtolower($text));
        }
        return $slug ?: 'page';
    }

    /**
     * Extract the Thai portion from "ไทย / English" formatted category
     * names so titles read more naturally to Thai users.
     */
    private function extractThai(string $name): string
    {
        $parts = explode('/', $name);
        return trim($parts[0] ?? $name);
    }
}
