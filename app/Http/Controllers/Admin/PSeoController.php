<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeoLandingPage;
use App\Models\SeoPageTemplate;
use App\Services\Seo\PSeoService;
use Illuminate\Http\Request;

/**
 * Admin control panel for the pSEO subsystem.
 *
 * Three concerns:
 *   1. Templates — toggle is_auto_enabled per type, edit patterns
 *      (title/description/body), tweak min_data_points threshold.
 *   2. Pages — list every generated landing page, sort by views,
 *      lock/unlock, publish/unpublish, hand-edit, manual delete.
 *   3. Bulk actions — regenerate all from templates, regenerate
 *      stale pages, etc.
 */
class PSeoController extends Controller
{
    public function __construct(private readonly PSeoService $svc) {}

    /* ───────── Dashboard ───────── */

    public function index()
    {
        $templates = SeoPageTemplate::orderBy('type')->get();

        $stats = [
            'total_pages'     => SeoLandingPage::count(),
            'published_pages' => SeoLandingPage::where('is_published', true)->count(),
            'locked_pages'    => SeoLandingPage::where('is_locked', true)->count(),
            'total_views'     => SeoLandingPage::sum('view_count'),
            'stale_pages'     => SeoLandingPage::stale()->count(),
        ];

        // Per-type breakdown for the stats grid
        $byType = SeoLandingPage::selectRaw('type, COUNT(*) as cnt, SUM(view_count) as views')
            ->groupBy('type')
            ->orderByDesc('cnt')
            ->get();

        // Top-traffic pages — focus list for the admin to monitor
        $topPages = SeoLandingPage::published()
            ->orderByDesc('view_count')
            ->limit(10)
            ->get();

        return view('admin.pseo.index', compact('templates', 'stats', 'byType', 'topPages'));
    }

    /* ───────── Templates ───────── */

    public function templateEdit(SeoPageTemplate $template)
    {
        return view('admin.pseo.template-edit', compact('template'));
    }

    public function templateUpdate(Request $request, SeoPageTemplate $template)
    {
        $validated = $request->validate([
            'name'                       => 'required|string|max:120',
            'is_auto_enabled'            => 'nullable|boolean',
            'title_pattern'              => 'required|string|max:500',
            'meta_description_pattern'   => 'required|string|max:500',
            'body_template'              => 'nullable|string',
            'h1_pattern'                 => 'nullable|string|max:500',
            'min_data_points'            => 'required|integer|min:1|max:1000',
            'schema_type'                => 'nullable|string|max:60',
        ]);
        $validated['is_auto_enabled'] = $request->boolean('is_auto_enabled');

        $template->update($validated);

        return back()->with('success', 'อัปเดต template สำเร็จ');
    }

    /** Quick-toggle endpoint (used by the dashboard switch). */
    public function templateToggle(SeoPageTemplate $template)
    {
        $template->update(['is_auto_enabled' => !$template->is_auto_enabled]);
        return back()->with('success',
            $template->is_auto_enabled ? "เปิด auto-gen สำหรับ {$template->name}" : "ปิด auto-gen สำหรับ {$template->name}"
        );
    }

    /* ───────── Pages list ───────── */

    public function pages(Request $request)
    {
        $pages = SeoLandingPage::query()
            ->when($request->type,    fn($q, $v) => $q->where('type', $v))
            ->when($request->status === 'published',   fn($q) => $q->where('is_published', true))
            ->when($request->status === 'unpublished', fn($q) => $q->where('is_published', false))
            ->when($request->status === 'locked',      fn($q) => $q->where('is_locked', true))
            ->when($request->status === 'stale',       fn($q) => $q->stale())
            ->when($request->q, fn($q, $v) => $q->where(function ($w) use ($v) {
                $w->where('slug', 'ilike', "%{$v}%")
                  ->orWhere('title', 'ilike', "%{$v}%");
            }))
            ->orderByDesc('view_count')
            ->paginate(50)
            ->withQueryString();

        $types = SeoPageTemplate::allTypes();

        return view('admin.pseo.pages', compact('pages', 'types'));
    }

    public function pageEdit(SeoLandingPage $page)
    {
        return view('admin.pseo.page-edit', compact('page'));
    }

    public function pageUpdate(Request $request, SeoLandingPage $page)
    {
        $validated = $request->validate([
            'title'            => 'required|string|max:500',
            'meta_description' => 'required|string|max:500',
            'h1'               => 'nullable|string|max:500',
            'body_html'        => 'nullable|string',
            'is_published'     => 'nullable|boolean',
            'is_locked'        => 'nullable|boolean',
            // Customization fields (added 2026-05-01)
            'theme'            => 'nullable|string|max:30',
            'hero_image'       => 'nullable|string|max:500',
            'og_image'         => 'nullable|string|max:500',
            'cta_text'         => 'nullable|string|max:100',
            'cta_url'          => 'nullable|string|max:500',
            'show_gallery'     => 'nullable|boolean',
            'show_related'     => 'nullable|boolean',
            'show_stats'       => 'nullable|boolean',
            'show_faq'         => 'nullable|boolean',
            'extra_sections'   => 'nullable|array',
            'extra_sections.*.type'  => 'nullable|string|max:30',
            'extra_sections.*.title' => 'nullable|string|max:200',
            'extra_sections.*.body'  => 'nullable|string|max:5000',
        ]);

        // Cast booleans (checkboxes only present when checked).
        foreach (['is_published', 'is_locked', 'show_gallery', 'show_related', 'show_stats', 'show_faq'] as $bool) {
            $validated[$bool] = $request->boolean($bool);
        }

        // Sanitize extra_sections — drop empty entries the user didn't fill.
        if (!empty($validated['extra_sections'])) {
            $validated['extra_sections'] = collect($validated['extra_sections'])
                ->filter(fn ($s) => !empty($s['title']) || !empty($s['body']))
                ->values()
                ->all();
        }

        $page->update($validated);

        return back()->with('success', 'บันทึกหน้าสำเร็จ');
    }

    public function pageDestroy(SeoLandingPage $page)
    {
        $page->delete();
        return redirect()->route('admin.pseo.pages')->with('success', 'ลบหน้าสำเร็จ');
    }

    /* ───────── Bulk actions ───────── */

    /** Regenerate every active template — full sweep. */
    public function regenerateAll()
    {
        $results = $this->svc->generateAll();
        $total = collect($results)->sum(fn($r) => $r['created'] + $r['updated']);
        return back()->with('success', "Regenerated {$total} pages across " . count($results) . " templates");
    }

    /** Regenerate just one template's pages. */
    public function regenerateTemplate(SeoPageTemplate $template)
    {
        $r = $this->svc->generateForTemplate($template);
        return back()->with('success', "{$template->name}: created {$r['created']}, updated {$r['updated']}, skipped {$r['skipped']}");
    }
}
