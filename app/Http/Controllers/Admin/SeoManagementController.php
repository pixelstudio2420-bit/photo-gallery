<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeoPage;
use App\Models\SeoPageRevision;
use App\Services\Seo\SeoValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\View\View;

/**
 * Per-page SEO management — the "CMS" surface for SEO.
 *
 * Routes (registered in routes/web.php under /admin/seo):
 *   GET    /admin/seo               index        — dashboard + list
 *   GET    /admin/seo/create        create       — pick a route to override
 *   POST   /admin/seo               store        — save new override
 *   GET    /admin/seo/{seoPage}     show         — preview + history
 *   GET    /admin/seo/{seoPage}/edit edit
 *   PATCH  /admin/seo/{seoPage}     update
 *   DELETE /admin/seo/{seoPage}     destroy
 *   POST   /admin/seo/bulk          bulkUpdate   — apply to many at once
 *   POST   /admin/seo/{seoPage}/restore/{rev}  rollback to a revision
 *   GET    /admin/seo/audit         audit        — scan for issues
 *
 * Why one controller (not split per concern)
 * ------------------------------------------
 * The 8 actions here are a tight CRUD-with-extras cluster. Splitting
 * across controllers (e.g. SeoBulkController) would scatter the same
 * authorization + validation rules across files. Keep it together;
 * each method is short.
 */
class SeoManagementController extends Controller
{
    public function __construct(
        private readonly SeoValidator $validator,
    ) {}

    /* ──────────────────────────────────────────────────────────────────
       INDEX — dashboard + filterable table
       ────────────────────────────────────────────────────────────────── */

    public function index(Request $request): View
    {
        $q = SeoPage::query();

        if ($search = trim((string) $request->input('q'))) {
            $q->where(function ($w) use ($search) {
                $w->where('route_name', 'like', '%' . $search . '%')
                  ->orWhere('title',       'like', '%' . $search . '%')
                  ->orWhere('description', 'like', '%' . $search . '%');
            });
        }
        if ($locale = $request->input('locale')) {
            $q->where('locale', $locale);
        }
        if ($request->boolean('warnings_only')) {
            $q->whereNotNull('validation_warnings');
        }
        if ($request->has('active')) {
            $q->where('is_active', $request->boolean('active'));
        }

        $pages = $q->orderByDesc('updated_at')->paginate(25)->withQueryString();

        return view('admin.seo.index', [
            'pages'      => $pages,
            'summary'    => $this->validator->dashboardSummary(),
            'filters'    => $request->only(['q', 'locale', 'warnings_only', 'active']),
            'locales'    => ['th' => 'ไทย', 'en' => 'English'],
        ]);
    }

    /* ──────────────────────────────────────────────────────────────────
       CREATE / STORE — add new override
       ────────────────────────────────────────────────────────────────── */

    public function create(): View
    {
        // Enumerate all named GET routes so the admin can pick one.
        // We exclude api/admin/photographer/auth routes (no SEO impact).
        $routes = collect(RouteFacade::getRoutes())
            ->filter(function ($r) {
                if (!$r->getName()) return false;
                $methods = $r->methods();
                if (!in_array('GET', $methods, true)) return false;
                $uri = $r->uri();
                $excluded = ['api/', 'admin/', 'photographer/', '_ignition', 'sanctum/', 'horizon/', 'telescope/'];
                foreach ($excluded as $bad) {
                    if (str_starts_with($uri, $bad)) return false;
                }
                return true;
            })
            ->map(fn($r) => [
                'name' => $r->getName(),
                'uri'  => '/' . ltrim($r->uri(), '/'),
            ])
            ->sortBy('uri')
            ->values()
            ->all();

        return view('admin.seo.edit', [
            'page'    => new SeoPage(['locale' => 'th', 'is_active' => true]),
            'routes'  => $routes,
            'isNew'   => true,
            'warnings' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);
        $data['structured_data'] = $this->parseJsonField($request->input('structured_data_text'));
        $data['alt_text_map']    = $this->parseJsonField($request->input('alt_text_map_text'));

        $page = new SeoPage($data);
        $page->changeReason = $request->input('change_reason') ?: 'created';
        $page->save();

        // Run validation post-save and persist warnings.
        $this->refreshValidation($page);

        return redirect()
            ->route('admin.seo.edit', $page)
            ->with('success', 'สร้าง SEO override สำหรับ ' . $page->route_name . ' เรียบร้อย');
    }

    /* ──────────────────────────────────────────────────────────────────
       SHOW / EDIT / UPDATE
       ────────────────────────────────────────────────────────────────── */

    public function show(SeoPage $seoPage): View
    {
        $revisions = $seoPage->revisions()->orderByDesc('id')->limit(20)->get();

        return view('admin.seo.show', [
            'page'      => $seoPage,
            'revisions' => $revisions,
            'warnings'  => $this->validator->validate($seoPage),
        ]);
    }

    public function edit(SeoPage $seoPage): View
    {
        return view('admin.seo.edit', [
            'page'     => $seoPage,
            'routes'   => [],   // editing existing → route is locked
            'isNew'    => false,
            'warnings' => $this->validator->validate($seoPage),
        ]);
    }

    public function update(Request $request, SeoPage $seoPage): RedirectResponse
    {
        if ($seoPage->is_locked && !$request->boolean('force_unlock')) {
            return back()->withErrors(['_root' => 'หน้านี้ถูก lock — ติ๊ก force_unlock เพื่อแก้']);
        }

        $data = $this->validatePayload($request, ignoreRoute: true);
        $data['structured_data'] = $this->parseJsonField($request->input('structured_data_text'));
        $data['alt_text_map']    = $this->parseJsonField($request->input('alt_text_map_text'));

        $seoPage->changeReason = $request->input('change_reason') ?: 'updated';
        $seoPage->fill($data)->save();

        $this->refreshValidation($seoPage);

        return back()->with('success', 'บันทึก SEO override เรียบร้อย (version ' . $seoPage->version . ')');
    }

    public function destroy(SeoPage $seoPage): RedirectResponse
    {
        if ($seoPage->is_locked) {
            return back()->withErrors(['_root' => 'ลบไม่ได้ — หน้านี้ถูก lock']);
        }

        $name = $seoPage->route_name;
        $seoPage->delete();

        return redirect()->route('admin.seo.index')
            ->with('success', "ลบ SEO override ของ {$name} เรียบร้อย — ระบบจะกลับไปใช้ค่าจาก controller");
    }

    /* ──────────────────────────────────────────────────────────────────
       BULK UPDATE
       ────────────────────────────────────────────────────────────────── */

    public function bulkUpdate(Request $request): RedirectResponse
    {
        $request->validate([
            'ids'        => 'required|array|min:1',
            'ids.*'      => 'integer|exists:seo_pages,id',
            'field'      => 'required|in:meta_robots,is_active,og_image,append_keywords',
            'value'      => 'nullable',
            'append_with'=> 'nullable|string|max:300',
        ]);

        $pages   = SeoPage::whereIn('id', $request->input('ids'))->where('is_locked', false)->get();
        $field   = $request->input('field');
        $value   = $request->input('value');
        $changed = 0;

        foreach ($pages as $page) {
            switch ($field) {
                case 'meta_robots':
                    $page->meta_robots = $value;
                    break;
                case 'is_active':
                    $page->is_active = (bool) $value;
                    break;
                case 'og_image':
                    $page->og_image = $value;
                    break;
                case 'append_keywords':
                    $extra = trim((string) $request->input('append_with'));
                    if ($extra === '') break;
                    $existing = (string) $page->keywords;
                    $page->keywords = trim($existing === '' ? $extra : ($existing . ', ' . $extra), ', ');
                    break;
            }
            $page->changeReason = "bulk: {$field}";
            $page->save();
            $changed++;
        }

        return back()->with('success', "อัปเดต {$changed} หน้าเรียบร้อย — ฟิลด์ {$field}");
    }

    /* ──────────────────────────────────────────────────────────────────
       ROLLBACK
       ────────────────────────────────────────────────────────────────── */

    public function rollback(Request $request, SeoPage $seoPage, int $revisionId): RedirectResponse
    {
        $rev = SeoPageRevision::where('id', $revisionId)
            ->where('seo_page_id', $seoPage->id)
            ->firstOrFail();

        // Apply the snapshot back onto the page row.
        $seoPage->fill($rev->snapshot);
        $seoPage->changeReason = "rollback to v{$rev->version}";
        $seoPage->save();

        $this->refreshValidation($seoPage);

        return back()->with('success', "ย้อนกลับไปยัง revision v{$rev->version} เรียบร้อย");
    }

    /* ──────────────────────────────────────────────────────────────────
       AUDIT — list issues across the whole table
       ────────────────────────────────────────────────────────────────── */

    public function audit(): View
    {
        $issues = SeoPage::orderBy('updated_at', 'desc')->get()
            ->map(fn($p) => [
                'page'     => $p,
                'warnings' => $this->validator->validate($p),
            ])
            ->filter(fn($r) => !empty($r['warnings']))
            ->values();

        return view('admin.seo.audit', [
            'issues'  => $issues,
            'summary' => $this->validator->dashboardSummary(),
        ]);
    }

    /* ──────────────────────────────────────────────────────────────────
       Private helpers
       ────────────────────────────────────────────────────────────────── */

    /**
     * Common payload validation for create / update. `ignoreRoute=true`
     * skips the route_name check on update because the form locks it.
     */
    private function validatePayload(Request $request, bool $ignoreRoute = false): array
    {
        $rules = [
            'title'           => 'nullable|string|max:200',
            'description'     => 'nullable|string|max:500',
            'keywords'        => 'nullable|string|max:500',
            'canonical_url'   => 'nullable|string|max:500',
            'meta_robots'     => 'nullable|string|max:100',
            'og_title'        => 'nullable|string|max:200',
            'og_description'  => 'nullable|string|max:500',
            'og_image'        => 'nullable|string|max:500',
            'og_type'         => 'nullable|string|max:40',
            'path_preview'    => 'nullable|string|max:500',
            'is_active'       => 'sometimes|boolean',
            'is_locked'       => 'sometimes|boolean',
            'locale'          => 'required|string|max:8',
        ];
        if (!$ignoreRoute) {
            $rules['route_name']   = 'required|string|max:200';
            $rules['route_params'] = 'nullable|array';
        }

        $data = $request->validate($rules);
        // Cast booleans defensively (HTML checkboxes send "1" or absent).
        $data['is_active'] = (bool) ($request->input('is_active', false));
        $data['is_locked'] = (bool) ($request->input('is_locked', false));

        return $data;
    }

    /**
     * Decode a textarea-supplied JSON string. Returns null when the
     * payload is empty or invalid (caller should treat that as "no
     * change" rather than failing the whole save).
     */
    private function parseJsonField(?string $raw): ?array
    {
        if ($raw === null || trim($raw) === '') return null;
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function refreshValidation(SeoPage $page): void
    {
        $warnings = $this->validator->validate($page);
        $page->validation_warnings = $warnings ?: null;
        $page->last_validated_at   = now();
        $page->saveQuietly();   // don't fire observer again
    }
}
