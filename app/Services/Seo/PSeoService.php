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
    /**
     * Resolve an R2/S3 object key to a publicly-fetchable URL.
     *
     * Avatars and event cover images are stored as bare keys (e.g.
     * "system/avatars/user_3/uuid.png"). Dropping that into a <link
     * href> or background-image URL causes a 404 because the file
     * lives on R2, not the local public disk. R2MediaService knows
     * the public R2 hostname and produces a usable URL.
     *
     * Falls through to a /storage/{key} prefix if R2 isn't configured,
     * which works on local dev with `php artisan storage:link`.
     * Already-absolute URLs pass through unchanged.
     */
    private function resolveMediaUrl(?string $key): ?string
    {
        if (!$key) return null;
        if (preg_match('#^(?:https?:)?//#i', $key)) return $key;

        try {
            $url = (string) app(\App\Services\Media\R2MediaService::class)->url($key);
            if ($url && preg_match('#^(?:https?:)?//#i', $url)) {
                return $url;
            }
        } catch (\Throwable) {
            // Fall through.
        }
        return '/storage/' . ltrim($key, '/');
    }

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
            SeoPageTemplate::TYPE_EVENT         => $this->generateEventPages($template),
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

        // Pull approved photographers along with the new enrichment
        // fields (province, headline, specialties, etc) so the rendered
        // landing page can showcase a real bio rather than a placeholder.
        // event_count drives the min_data_points gate.
        $rows = DB::table('photographer_profiles as pp')
            ->leftJoin('event_events as e', 'e.photographer_id', '=', 'pp.user_id')
            ->leftJoin('thai_provinces as p', 'p.id', '=', 'pp.province_id')
            ->where('pp.status', 'approved')
            ->select(
                'pp.user_id', 'pp.display_name', 'pp.headline', 'pp.bio',
                'pp.specialties', 'pp.years_experience', 'pp.profile_completion',
                'pp.avatar', 'pp.instagram_handle',
                'p.name_th as province_th', 'p.name_en as province_en'
            )
            ->selectRaw('COUNT(DISTINCT e.id) FILTER (WHERE e.status = ?) as event_count', ['active'])
            ->groupBy(
                'pp.user_id', 'pp.display_name', 'pp.headline', 'pp.bio',
                'pp.specialties', 'pp.years_experience', 'pp.profile_completion',
                'pp.avatar', 'pp.instagram_handle',
                'p.name_th', 'p.name_en'
            )
            ->get();

        foreach ($rows as $row) {
            if ((int) $row->event_count < $template->min_data_points) {
                $stats['skipped']++;
                continue;
            }

            // Skip photographers with very low profile completion — no
            // point publishing thin content. Threshold matches Google's
            // helpful-content guidance.
            if ((int) ($row->profile_completion ?? 0) < 40) {
                $stats['skipped']++;
                continue;
            }

            // Pretty specialty list ("งานแต่ง · ปริญญา · กีฬา").
            $specialties = is_string($row->specialties) ? json_decode($row->specialties, true) : ($row->specialties ?? []);
            $specialtyText = is_array($specialties) ? implode(' · ', array_slice($specialties, 0, 5)) : '';

            $slug = 'photographers/' . $this->slugify($row->display_name . '-' . $row->user_id);
            $vars = [
                'name'         => $row->display_name,
                'headline'     => $row->headline ?? $specialtyText ?? 'ช่างภาพมืออาชีพ',
                'event_count'  => (int) $row->event_count,
                'bio'          => Str::limit($row->bio ?? '', 200),
                'specialties'  => $specialtyText,
                'experience'   => (int) ($row->years_experience ?? 0),
                'location'     => $row->province_th ?? '',
                'location_en'  => $row->province_en ?? '',
                'instagram'    => $row->instagram_handle ?? '',
                'brand'        => config('app.name', 'Loadroop'),
                'year'         => now()->year,
            ];

            // Lookup province_id for the photographer (kept on
            // photographer_profiles, not surfaced via the LEFT JOIN
            // above — fetch separately to keep the main query simple).
            $provId = DB::table('photographer_profiles')
                ->where('user_id', $row->user_id)
                ->value('province_id');

            $page = $this->upsertPage($template, $slug, $vars, [
                'photographer_id' => $row->user_id,
                'data_count'      => (int) $row->event_count,
                'province_id'     => $provId,
            ]);

            // Auto-set hero_image to avatar (only on first generate).
            // Resolve through R2MediaService so the rendered <img src>
            // is a real URL, not a bare R2 object key that 404s.
            if ($page->wasRecentlyCreated && !empty($row->avatar)) {
                $page->update(['hero_image' => $this->resolveMediaUrl($row->avatar)]);
            }

            $stats[$page->wasRecentlyCreated ? 'created' : 'updated']++;
        }

        return $stats;
    }

    /**
     * Per-event landing pages — one SEO-rich landing per active event.
     *
     * URL: /event-{slug}
     * Distinct from /events/{slug} (the existing detail page) — these
     * are dedicated marketing landings with custom title/meta/schema
     * that drive search traffic, then funnel buyers to the actual
     * event page via a CTA. Hero uses the event's cover_image.
     *
     * Skipped when:
     *   • event has no cover_image (thin without a hero photo)
     *   • event has < min_data_points photos uploaded
     */
    public function generateEventPages(SeoPageTemplate $template): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        $events = \App\Models\Event::query()
            ->where('status', 'active')
            ->where('visibility', 'public')
            ->withCount('photos')
            ->with([
                'category:id,name,slug',
                'photographerProfile:user_id,display_name',
                'province:id,name_th,name_en',
            ])
            ->get();

        foreach ($events as $event) {
            // Thin-content guard — event needs at least N photos uploaded
            // to justify a landing page; otherwise the page would have
            // no gallery content and Google's helpful-content update
            // would deindex / penalize.
            $photoCount = (int) ($event->photos_count ?? 0);
            if ($photoCount < $template->min_data_points) {
                $stats['skipped']++;
                continue;
            }

            $slug = 'event-' . $this->slugify($event->slug ?: ($event->name . '-' . $event->id));

            // Highlights — short selling points that often beat
            // description for SERP click-through. Drop into a
            // " · "-joined string the template can use as :highlights
            // anywhere it likes (typically meta description tail).
            $highlights = is_array($event->highlights)
                ? array_slice($event->highlights, 0, 3)
                : [];
            $highlightsLine = implode(' · ', $highlights);

            // Tag line — feeds the :keywords placeholder so the meta
            // keywords tag (and our internal full-text index) gets
            // free signal from photographer-curated tags.
            $tagsLine = is_array($event->tags)
                ? implode(', ', array_slice($event->tags, 0, 8))
                : '';

            $vars = [
                'event_name'   => $event->name,
                'event_date'   => $event->shoot_date ? \Carbon\Carbon::parse($event->shoot_date)->format('d/m/Y') : '',
                'category'     => $this->extractThai(optional($event->category)->name ?? ''),
                'location'     => optional($event->province)->name_th ?? ($event->location ?? ''),
                'photographer' => optional($event->photographerProfile)->display_name ?? '',
                'photo_count'  => $photoCount,
                'description'  => \Illuminate\Support\Str::limit(strip_tags($event->description ?? ''), 200),
                'brand'        => config('app.name', 'Loadroop'),
                'year'         => now()->year,
                // Enriched fields (2026-05-01) — available to admin-
                // editable templates as :placeholder tokens.
                'venue'        => (string) ($event->venue_name ?? ''),
                'organizer'    => (string) ($event->organizer ?? ''),
                'event_type'   => (string) ($event->event_type ?? ''),
                'attendees'    => (int) ($event->expected_attendees ?? 0),
                'highlights'   => $highlightsLine,
                'tags'         => $tagsLine,
                'start_time'   => $event->start_time ? substr((string) $event->start_time, 0, 5) : '',
                'end_time'     => $event->end_time   ? substr((string) $event->end_time, 0, 5)   : '',
            ];

            $page = $this->upsertPage($template, $slug, $vars, [
                'event_id'    => $event->id,
                'category_id' => $event->category_id,
                'province_id' => $event->province_id,
                'data_count'  => $photoCount,
            ]);

            // Auto-set hero_image to the event's cover (only on first
            // generate, never overwrite admin's choice). Resolve the
            // R2 key into a publicly-fetchable URL so the front-end
            // can render it directly without a 404.
            if ($page->wasRecentlyCreated || empty($page->hero_image)) {
                if ($event->cover_image) {
                    $page->update(['hero_image' => $this->resolveMediaUrl($event->cover_image)]);
                }
            }

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
