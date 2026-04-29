<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\AdClick;
use App\Models\AdCreative;
use App\Models\AdImpression;
use App\Models\PhotographerPromotion;
use App\Services\Media\Exceptions\InvalidMediaFileException;
use App\Services\Media\R2MediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Admin monetization dashboard.
 *
 * Routes (registered under /admin/monetization):
 *   GET  /                  → revenue dashboard + campaign list
 *   GET  /campaigns/create  → form
 *   POST /campaigns         → store
 *   GET  /campaigns/{id}    → campaign performance + creative list
 *   POST /campaigns/{id}/pause / resume
 *   GET  /promotions        → photographer boost list + recent buyers
 *
 * Reports read from `ad_daily_metrics` for fast aggregation; the raw
 * impression/click tables are append-only and would be too slow to
 * scan on every dashboard load.
 */
class MonetizationController extends Controller
{
    public function dashboard(): View
    {
        $now = now();

        $revenue = [
            'today_thb'      => $this->revenueBetween($now->copy()->startOfDay(),   $now),
            'this_month_thb' => $this->revenueBetween($now->copy()->startOfMonth(), $now),
            'all_time_thb'   => (float) AdCampaign::sum('spent_thb')
                              + (float) PhotographerPromotion::where('status', '!=', PhotographerPromotion::STATUS_PENDING)->sum('amount_thb'),
        ];

        $campaignStats = [
            'active'    => AdCampaign::where('status', AdCampaign::STATUS_ACTIVE)->count(),
            'paused'    => AdCampaign::where('status', AdCampaign::STATUS_PAUSED)->count(),
            'exhausted' => AdCampaign::where('status', AdCampaign::STATUS_EXHAUSTED)->count(),
        ];

        $promoStats = [
            'active' => PhotographerPromotion::where('status', PhotographerPromotion::STATUS_ACTIVE)->count(),
            'today'  => PhotographerPromotion::whereDate('created_at', today())->count(),
        ];

        // CTR last 7 days — single aggregate query, no loops.
        $since = $now->copy()->subDays(7);
        $ctr = [
            'impressions' => AdImpression::where('seen_at', '>=', $since)->where('is_bot', false)->count(),
            'clicks'      => AdClick::where('clicked_at', '>=', $since)->where('is_suspicious', false)->count(),
        ];
        $ctr['rate_pct'] = $ctr['impressions'] > 0
            ? round($ctr['clicks'] / $ctr['impressions'] * 100, 2)
            : 0.0;

        $recentCampaigns = AdCampaign::orderByDesc('created_at')->limit(10)->get();
        $recentPromotions = PhotographerPromotion::with('photographer')
            ->orderByDesc('created_at')->limit(10)->get();

        return view('admin.monetization.dashboard', compact(
            'revenue', 'campaignStats', 'promoStats', 'ctr',
            'recentCampaigns', 'recentPromotions',
        ));
    }

    public function campaignsIndex(): View
    {
        $campaigns = AdCampaign::orderByDesc('created_at')->paginate(25);
        return view('admin.monetization.campaigns', compact('campaigns'));
    }

    public function campaignsCreate(): View
    {
        return view('admin.monetization.campaign-form', [
            'campaign' => new AdCampaign([
                'pricing_model' => AdCampaign::PRICING_CPM,
                'status'        => AdCampaign::STATUS_PENDING,
                'starts_at'     => now(),
                'ends_at'       => now()->addDays(30),
            ]),
            'isNew' => true,
        ]);
    }

    public function campaignsStore(Request $request)
    {
        $data = $request->validate([
            'name'           => 'required|string|max:200',
            'advertiser'     => 'required|string|max:200',
            'contact_email'  => 'nullable|email|max:180',
            'pricing_model'  => 'required|in:cpm,cpc,flat_daily,flat_monthly',
            'rate_thb'       => 'required|numeric|min:0',
            'budget_cap_thb' => 'nullable|numeric|min:0',
            'starts_at'      => 'required|date',
            'ends_at'        => 'required|date|after:starts_at',
            'status'         => 'required|in:pending,active,paused,exhausted,ended',
        ]);
        $data['created_by'] = \Auth::guard('admin')->id();

        $campaign = AdCampaign::create($data);
        return redirect()->route('admin.monetization.campaigns.show', $campaign)
            ->with('success', 'สร้างแคมเปญ "' . $campaign->name . '" เรียบร้อย');
    }

    public function campaignsShow(AdCampaign $campaign): View
    {
        $since = now()->subDays(30);
        $stats = [
            'impressions' => AdImpression::where('campaign_id', $campaign->id)->where('seen_at', '>=', $since)->count(),
            'clicks'      => AdClick::where('campaign_id', $campaign->id)->where('clicked_at', '>=', $since)->count(),
            'valid_imps'  => AdImpression::where('campaign_id', $campaign->id)->where('seen_at', '>=', $since)->where('is_bot', false)->count(),
            'valid_clks'  => AdClick::where('campaign_id', $campaign->id)->where('clicked_at', '>=', $since)->where('is_suspicious', false)->count(),
        ];
        $stats['ctr_pct'] = $stats['valid_imps'] > 0
            ? round($stats['valid_clks'] / $stats['valid_imps'] * 100, 2)
            : 0.0;

        $creatives = $campaign->creatives()->orderByDesc('weight')->get();

        return view('admin.monetization.campaign-show', compact('campaign', 'stats', 'creatives'));
    }

    public function campaignToggle(AdCampaign $campaign, string $action)
    {
        $valid = ['active', 'paused', 'ended'];
        if (!in_array($action, $valid, true)) {
            return back()->withErrors(['_root' => 'สถานะไม่ถูกต้อง']);
        }
        $campaign->update(['status' => $action]);
        return back()->with('success', "แคมเปญถูกเปลี่ยนสถานะเป็น {$action}");
    }

    public function promotions(): View
    {
        $promos = PhotographerPromotion::with('photographer')
            ->orderByDesc('created_at')
            ->paginate(25);

        $revenue30d = (float) PhotographerPromotion::where('created_at', '>=', now()->subDays(30))
            ->where('status', '!=', PhotographerPromotion::STATUS_PENDING)
            ->sum('amount_thb');

        return view('admin.monetization.promotions', compact('promos', 'revenue30d'));
    }

    /* ─────────────────── Photographer Promotion CRUD ─────────────────── */

    /**
     * Edit form for a photographer promotion. Admin-only path used for
     * incident response — manually adjust dates, change kind, fix a
     * photographer's promo that the engine routed wrong.
     */
    public function promotionEdit(PhotographerPromotion $promotion): View
    {
        $promotion->load('photographer');
        return view('admin.monetization.promotion-form', compact('promotion'));
    }

    public function promotionUpdate(Request $request, PhotographerPromotion $promotion): RedirectResponse
    {
        $data = $request->validate([
            'kind'         => 'required|in:boost,featured,highlight',
            'boost_score'  => 'required|integer|min:1|max:25',
            'starts_at'    => 'nullable|date',
            'ends_at'      => 'nullable|date|after_or_equal:starts_at',
            'amount_thb'   => 'required|numeric|min:0|max:99999',
            'status'       => 'required|in:pending,active,expired,cancelled,refunded',
        ]);

        $promotion->update($data);

        \App\Support\ActivityLogger::admin(
            action:      'promotion.updated',
            target:      $promotion,
            description: "แก้ไข promotion #{$promotion->id} ของช่างภาพ #{$promotion->photographer_id}",
            newValues:   $data,
        );

        return redirect()->route('admin.monetization.promotions')
            ->with('success', "บันทึก promotion #{$promotion->id} แล้ว");
    }

    /**
     * Cancel an active promotion immediately. Sets status=cancelled but
     * does NOT auto-refund — admin chooses refund explicitly because the
     * refund path may need to coordinate with the gateway.
     */
    public function promotionCancel(Request $request, PhotographerPromotion $promotion): RedirectResponse
    {
        if (in_array($promotion->status, ['cancelled', 'refunded', 'expired'], true)) {
            return back()->with('error', "promotion #{$promotion->id} อยู่ในสถานะ {$promotion->status} แล้ว");
        }

        $promotion->update([
            'status' => PhotographerPromotion::STATUS_CANCELLED,
            'ends_at' => now(),
        ]);

        \App\Support\ActivityLogger::admin(
            action:      'promotion.cancelled',
            target:      $promotion,
            description: "ยกเลิก promotion #{$promotion->id} (kind={$promotion->kind}, photographer #{$promotion->photographer_id})",
            newValues:   ['status' => 'cancelled'],
        );

        return back()->with('success', "ยกเลิก promotion #{$promotion->id} แล้ว — ใช้ Refund เพื่อคืนเงิน");
    }

    /**
     * Mark a promotion as refunded — used after the actual refund has
     * cleared on the payment gateway side. Status flip is the audit
     * trail; the money side is handled by the existing refund flow.
     */
    public function promotionRefund(Request $request, PhotographerPromotion $promotion): RedirectResponse
    {
        if ($promotion->status === PhotographerPromotion::STATUS_REFUNDED) {
            return back()->with('error', 'promotion นี้ refund แล้ว');
        }

        $promotion->update([
            'status' => PhotographerPromotion::STATUS_REFUNDED,
        ]);

        \App\Support\ActivityLogger::admin(
            action:      'promotion.refunded',
            target:      $promotion,
            description: "Refund promotion #{$promotion->id} (฿" . number_format($promotion->amount_thb, 0) . ")",
            newValues:   ['status' => 'refunded'],
        );

        return back()->with('success', "บันทึก refund สำหรับ promotion #{$promotion->id} แล้ว");
    }

    /* ─────────────────── Creative CRUD ─────────────────── */

    /**
     * Show the form for creating a new creative for a campaign. The
     * campaign id is in the URL — the creative inherits the campaign's
     * billing model and budget cap automatically.
     */
    public function creativesCreate(AdCampaign $campaign): View
    {
        return view('admin.monetization.creative-form', [
            'campaign' => $campaign,
            'creative' => new AdCreative([
                'campaign_id' => $campaign->id,
                'placement'   => AdCreative::PLACEMENT_HOMEPAGE_BANNER,
                'weight'      => 100,
                'is_active'   => true,
                'cta_label'   => 'เรียนรู้เพิ่มเติม',
            ]),
            'isNew' => true,
        ]);
    }

    /**
     * Persist a new creative. Image is REQUIRED — uploaded to R2 under
     * `system/ad_creative/user_0/campaign_{id}/{uuid}_filename.ext` and
     * the public CDN URL stored in `image_url`.
     */
    public function creativesStore(Request $request, AdCampaign $campaign, R2MediaService $media): RedirectResponse
    {
        $data = $this->validateCreativePayload($request, requireImage: true);

        // Upload BEFORE creating the row — if upload fails, no orphan
        // creative gets persisted with a broken image_url.
        try {
            $upload = $media->uploadAdCreative($campaign->id, $request->file('image'));
        } catch (InvalidMediaFileException $e) {
            return back()->withErrors(['image' => $e->getMessage()])->withInput();
        } catch (\Throwable $e) {
            Log::error('ad.creative.upload_failed', ['campaign_id' => $campaign->id, 'err' => $e->getMessage()]);
            return back()->withErrors(['image' => 'อัพโหลดไม่สำเร็จ — ลองอีกครั้ง'])->withInput();
        }

        $creative = AdCreative::create([
            'campaign_id' => $campaign->id,
            'headline'    => $data['headline'],
            'body'        => $data['body'] ?? null,
            'image_url'   => $upload->url,        // CDN-served URL
            'click_url'   => $data['click_url'],
            'cta_label'   => $data['cta_label'] ?? 'เรียนรู้เพิ่มเติม',
            'placement'   => $data['placement'],
            'weight'      => $data['weight'] ?? 100,
            'is_active'   => $data['is_active'] ?? true,
        ]);

        // Bust the served-candidate cache so a new creative reaches the
        // page within seconds, not 60s.
        \Cache::forget("ad.candidates.{$creative->placement}");

        return redirect()
            ->route('admin.monetization.campaigns.show', $campaign)
            ->with('success', 'เพิ่ม creative "' . $creative->headline . '" เรียบร้อย');
    }

    public function creativesEdit(AdCampaign $campaign, AdCreative $creative): View
    {
        abort_unless($creative->campaign_id === $campaign->id, 404);
        return view('admin.monetization.creative-form', [
            'campaign' => $campaign,
            'creative' => $creative,
            'isNew'    => false,
        ]);
    }

    public function creativesUpdate(Request $request, AdCampaign $campaign, AdCreative $creative, R2MediaService $media): RedirectResponse
    {
        abort_unless($creative->campaign_id === $campaign->id, 404);
        $data = $this->validateCreativePayload($request, requireImage: false);

        // Image is optional on update — only replace if a new file was sent.
        if ($request->hasFile('image')) {
            try {
                $upload = $media->uploadAdCreative($campaign->id, $request->file('image'));
                $data['image_url'] = $upload->url;
                // We don't delete the OLD R2 object here — keeping it
                // allows rollback via revision if we ever add one. R2
                // storage cost for old creatives is negligible vs the
                // operational cost of an irreversible delete.
            } catch (InvalidMediaFileException $e) {
                return back()->withErrors(['image' => $e->getMessage()])->withInput();
            }
        }

        $creative->update($data);
        \Cache::forget("ad.candidates.{$creative->placement}");

        return redirect()
            ->route('admin.monetization.campaigns.show', $campaign)
            ->with('success', 'บันทึกการแก้ไข "' . $creative->headline . '" เรียบร้อย');
    }

    public function creativesDestroy(AdCampaign $campaign, AdCreative $creative): RedirectResponse
    {
        abort_unless($creative->campaign_id === $campaign->id, 404);
        $name = $creative->headline;
        $placement = $creative->placement;
        $creative->delete();
        \Cache::forget("ad.candidates.{$placement}");

        return back()->with('success', "ลบ creative \"{$name}\" เรียบร้อย");
    }

    /**
     * Shared validation for create + update. `requireImage=true` only on
     * create — on update, the existing image is reused if no new file
     * is uploaded.
     */
    private function validateCreativePayload(Request $request, bool $requireImage): array
    {
        $rules = [
            'headline'  => 'required|string|max:120',
            'body'      => 'nullable|string|max:300',
            'click_url' => 'required|url|max:500',
            'cta_label' => 'nullable|string|max:40',
            'placement' => 'required|in:homepage_banner,sidebar,search_inline,landing_native',
            'weight'    => 'required|integer|min:1|max:1000',
            'is_active' => 'sometimes|boolean',
            'image'     => ($requireImage ? 'required|' : 'nullable|')
                         . 'image|mimes:jpg,jpeg,png,webp|max:8192',  // matches media.system.ad_creative cap
        ];
        $data = $request->validate($rules);
        $data['is_active'] = (bool) $request->input('is_active', false);
        return $data;
    }

    /* ─────────────── helpers ─────────────── */

    /* ─────────────── helpers ─────────────── */

    private function revenueBetween(\Carbon\Carbon $from, \Carbon\Carbon $to): float
    {
        $ad = (float) AdCampaign::whereBetween('updated_at', [$from, $to])->sum('spent_thb');
        $pr = (float) PhotographerPromotion::whereBetween('created_at', [$from, $to])
            ->where('status', '!=', PhotographerPromotion::STATUS_PENDING)
            ->sum('amount_thb');
        return $ad + $pr;
    }
}
