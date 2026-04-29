<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogAffiliateClick;
use App\Models\BlogAffiliateLink;
use App\Models\BlogCtaButton;
use App\Services\Media\Exceptions\InvalidMediaFileException;
use App\Services\Media\R2MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BlogAffiliateController extends Controller
{
    /* ================================================================
     *  INDEX -- list affiliate links
     * ================================================================ */
    public function index(Request $request)
    {
        $links = BlogAffiliateLink::query()
            ->withCount('clicks')
            ->when($request->provider, fn ($q, $v) => $q->where('provider', $v))
            ->when($request->has('is_active') && $request->is_active !== '', fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->search, fn ($q, $v) => $q->where(function ($sub) use ($v) {
                $sub->where('name', 'ilike', "%{$v}%")
                    ->orWhere('destination_url', 'ilike', "%{$v}%")
                    ->orWhere('campaign', 'ilike', "%{$v}%");
            }))
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        $providers = BlogAffiliateLink::select('provider')
            ->distinct()
            ->whereNotNull('provider')
            ->orderBy('provider')
            ->pluck('provider');

        return view('admin.blog.affiliate.index', compact('links', 'providers'));
    }

    /* ================================================================
     *  CREATE / STORE
     * ================================================================ */
    public function create()
    {
        return view('admin.blog.affiliate.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'slug'            => 'nullable|string|max:255|unique:blog_affiliate_links,slug',
            'destination_url' => 'required|url|max:2000',
            'provider'        => 'nullable|string|max:100',
            'campaign'        => 'nullable|string|max:255',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'description'     => 'nullable|string|max:2000',
            'image'           => 'nullable|image|max:2048',
            'nofollow'        => 'nullable|boolean',
            'is_active'       => 'nullable|boolean',
            'expires_at'      => 'nullable|date',
        ]);

        $validated['slug']      = !empty($validated['slug']) ? $validated['slug'] : Str::slug($validated['name']);
        $validated['nofollow']  = $request->boolean('nofollow', true);
        $validated['is_active'] = $request->boolean('is_active', true);

        // Ensure slug uniqueness
        $validated['slug'] = $this->ensureUniqueLinkSlug($validated['slug']);

        // Defer image upload to after create so the path can carry the id
        $imageFile = $request->hasFile('image') ? $request->file('image') : null;
        unset($validated['image']);

        $link = BlogAffiliateLink::create($validated);

        if ($imageFile) {
            try {
                $upload = app(R2MediaService::class)
                    ->uploadBlogAffiliateBanner((int) Auth::id(), (int) $link->id, $imageFile);
                $link->image = $upload->key;
                $link->save();
            } catch (InvalidMediaFileException $e) {
                return redirect()
                    ->route('admin.blog.affiliate.edit', $link)
                    ->with('error', $e->getMessage());
            }
        }

        return redirect()
            ->route('admin.blog.affiliate.index')
            ->with('success', 'สร้างลิงก์ Affiliate เรียบร้อยแล้ว');
    }

    /* ================================================================
     *  EDIT / UPDATE
     * ================================================================ */
    public function edit($id)
    {
        $link = BlogAffiliateLink::findOrFail($id);

        return view('admin.blog.affiliate.edit', compact('link'));
    }

    public function update(Request $request, $id)
    {
        $link = BlogAffiliateLink::findOrFail($id);

        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'slug'            => 'nullable|string|max:255|unique:blog_affiliate_links,slug,' . $link->id,
            'destination_url' => 'required|url|max:2000',
            'provider'        => 'nullable|string|max:100',
            'campaign'        => 'nullable|string|max:255',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'description'     => 'nullable|string|max:2000',
            'image'           => 'nullable|image|max:2048',
            'nofollow'        => 'nullable|boolean',
            'is_active'       => 'nullable|boolean',
            'expires_at'      => 'nullable|date',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $validated['nofollow']  = $request->boolean('nofollow', true);
        $validated['is_active'] = $request->boolean('is_active', true);

        // Image upload — wipe the old banner off R2 first (CDN cache
        // purged async by the delete pipeline), then upload the
        // replacement under the canonical schema.
        if ($request->hasFile('image')) {
            $media = app(R2MediaService::class);
            if ($link->image) {
                try { $media->delete($link->image); } catch (\Throwable) {}
            }
            try {
                $upload = $media->uploadBlogAffiliateBanner(
                    (int) Auth::id(),
                    (int) $link->id,
                    $request->file('image'),
                );
                $validated['image'] = $upload->key;
            } catch (InvalidMediaFileException $e) {
                return back()->withInput()->withErrors(['image' => $e->getMessage()]);
            }
        } else {
            unset($validated['image']);
        }

        $link->update($validated);

        return redirect()
            ->route('admin.blog.affiliate.index')
            ->with('success', 'อัพเดทลิงก์ Affiliate เรียบร้อยแล้ว');
    }

    /* ================================================================
     *  DESTROY
     * ================================================================ */
    public function destroy($id)
    {
        $link = BlogAffiliateLink::findOrFail($id);

        if ($link->image) {
            try { app(R2MediaService::class)->delete($link->image); } catch (\Throwable) {}
        }

        // Delete related clicks and CTA buttons
        $link->clicks()->delete();
        $link->ctaButtons()->delete();
        $link->delete();

        return redirect()
            ->route('admin.blog.affiliate.index')
            ->with('success', 'ลบลิงก์ Affiliate เรียบร้อยแล้ว');
    }

    /* ================================================================
     *  STATS -- detailed click analytics for a single link
     * ================================================================ */
    public function stats($id)
    {
        $link = BlogAffiliateLink::withCount('clicks')->findOrFail($id);

        // Clicks by day (last 30 days)
        $clicksByDay = BlogAffiliateClick::where('affiliate_link_id', $id)
            ->where('clicked_at', '>=', now()->subDays(30))
            ->select(DB::raw('DATE(clicked_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Clicks by device
        $clicksByDevice = BlogAffiliateClick::where('affiliate_link_id', $id)
            ->select('device_type', DB::raw('COUNT(*) as count'))
            ->groupBy('device_type')
            ->orderBy('count', 'desc')
            ->get();

        // Clicks by country
        $clicksByCountry = BlogAffiliateClick::where('affiliate_link_id', $id)
            ->select('country', DB::raw('COUNT(*) as count'))
            ->groupBy('country')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get();

        // Top referring posts
        $topPosts = BlogAffiliateClick::where('affiliate_link_id', $id)
            ->whereNotNull('post_id')
            ->select('post_id', DB::raw('COUNT(*) as count'))
            ->groupBy('post_id')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->with('post:id,title,slug')
            ->get();

        // Recent clicks
        $recentClicks = BlogAffiliateClick::where('affiliate_link_id', $id)
            ->orderBy('clicked_at', 'desc')
            ->limit(50)
            ->get();

        return view('admin.blog.affiliate.stats', compact(
            'link', 'clicksByDay', 'clicksByDevice', 'clicksByCountry', 'topPosts', 'recentClicks'
        ));
    }

    /* ================================================================
     *  DASHBOARD -- overview with charts
     * ================================================================ */
    public function dashboard()
    {
        // Overall stats
        $totalLinks       = BlogAffiliateLink::count();
        $activeLinks      = BlogAffiliateLink::where('is_active', true)->count();
        $totalClicks      = (int) BlogAffiliateLink::sum('total_clicks');
        $totalConversions = (int) BlogAffiliateLink::sum('total_conversions');
        $totalRevenue     = (float) BlogAffiliateLink::sum('revenue');

        // Clicks by day (last 30 days)
        $clicksByDay = BlogAffiliateClick::where('clicked_at', '>=', now()->subDays(30))
            ->select(DB::raw('DATE(clicked_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Top links by clicks
        $topLinks = BlogAffiliateLink::orderBy('total_clicks', 'desc')
            ->limit(10)
            ->get(['id', 'name', 'provider', 'total_clicks', 'total_conversions', 'revenue']);

        // Top posts driving clicks
        $topPosts = BlogAffiliateClick::whereNotNull('post_id')
            ->select('post_id', DB::raw('COUNT(*) as click_count'))
            ->groupBy('post_id')
            ->orderBy('click_count', 'desc')
            ->limit(10)
            ->with('post:id,title,slug')
            ->get();

        $stats = compact(
            'totalLinks', 'activeLinks', 'totalClicks',
            'totalConversions', 'totalRevenue'
        );

        return view('admin.blog.affiliate.dashboard', compact(
            'stats', 'clicksByDay', 'topLinks', 'topPosts'
        ));
    }

    /* ================================================================
     *  CTA BUTTONS -- CRUD
     * ================================================================ */
    public function ctaButtons(Request $request)
    {
        $buttons = BlogCtaButton::with('affiliateLink')
            ->when($request->search, fn ($q, $v) => $q->where('name', 'ilike', "%{$v}%"))
            ->when($request->has('is_active') && $request->is_active !== '', fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        $affiliateLinks = BlogAffiliateLink::active()->orderBy('name')->get();

        return view('admin.blog.affiliate.cta-buttons', compact('buttons', 'affiliateLinks'));
    }

    public function storeCta(Request $request)
    {
        $validated = $request->validate([
            'name'                => 'required|string|max:255',
            'label'              => 'required|string|max:255',
            'sub_label'          => 'nullable|string|max:255',
            'icon'               => 'nullable|string|max:100',
            'style'              => 'nullable|string|max:50',
            'url'                => 'nullable|url|max:2000',
            'affiliate_link_id'  => 'nullable|exists:blog_affiliate_links,id',
            'position'           => 'nullable|string|in:inline,sidebar,floating,after_content,popup',
            'show_after_paragraph' => 'nullable|integer|min:0',
            'variant'            => 'nullable|string|max:50',
            'is_active'          => 'nullable|boolean',
            'display_conditions' => 'nullable|array',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        BlogCtaButton::create($validated);

        return redirect()
            ->route('admin.blog.cta.index')
            ->with('success', 'สร้างปุ่ม CTA เรียบร้อยแล้ว');
    }

    public function updateCta(Request $request, $id)
    {
        $button = BlogCtaButton::findOrFail($id);

        $validated = $request->validate([
            'name'                => 'required|string|max:255',
            'label'              => 'required|string|max:255',
            'sub_label'          => 'nullable|string|max:255',
            'icon'               => 'nullable|string|max:100',
            'style'              => 'nullable|string|max:50',
            'url'                => 'nullable|url|max:2000',
            'affiliate_link_id'  => 'nullable|exists:blog_affiliate_links,id',
            'position'           => 'nullable|string|in:inline,sidebar,floating,after_content,popup',
            'show_after_paragraph' => 'nullable|integer|min:0',
            'variant'            => 'nullable|string|max:50',
            'is_active'          => 'nullable|boolean',
            'display_conditions' => 'nullable|array',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        $button->update($validated);

        return redirect()
            ->route('admin.blog.cta.index')
            ->with('success', 'อัพเดทปุ่ม CTA เรียบร้อยแล้ว');
    }

    public function destroyCta($id)
    {
        $button = BlogCtaButton::findOrFail($id);
        $button->delete();

        return redirect()
            ->route('admin.blog.cta.index')
            ->with('success', 'ลบปุ่ม CTA เรียบร้อยแล้ว');
    }

    /* ================================================================
     *  PRIVATE HELPERS
     * ================================================================ */
    private function ensureUniqueLinkSlug(string $slug, ?int $excludeId = null): string
    {
        $original = $slug;
        $count    = 1;

        while (BlogAffiliateLink::where('slug', $slug)->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))->exists()) {
            $slug = $original . '-' . $count++;
        }

        return $slug;
    }
}
