<?php

namespace App\Services\Blog;

use App\Models\BlogAffiliateClick;
use App\Models\BlogAffiliateLink;
use App\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AffiliateTrackingService -- ติดตามคลิกลิงก์ Affiliate และวิเคราะห์ข้อมูล
 *
 * จัดการ click tracking, cooldown period, device detection,
 * analytics dashboard, และ CTA block insertion
 */
class AffiliateTrackingService
{
    /* ====================================================================
     *  Click Tracking
     * ==================================================================== */

    /**
     * บันทึกการคลิกลิงก์ affiliate
     *
     * ตรวจสอบ cooldown period เพื่อป้องกันการนับซ้ำ
     */
    public function trackClick(BlogAffiliateLink $link, Request $request, ?int $postId = null): ?BlogAffiliateClick
    {
        $ipAddress = $request->ip();
        $userAgent = $request->userAgent() ?? '';
        $cooldown  = config('blog.affiliate.click_cooldown_minutes', 30);

        // ตรวจสอบ cooldown -- ไม่นับซ้ำจาก IP เดียวกันในช่วงเวลาที่กำหนด
        $recentClick = BlogAffiliateClick::where('affiliate_link_id', $link->id)
            ->where('ip_address', $ipAddress)
            ->where('clicked_at', '>=', now()->subMinutes($cooldown))
            ->first();

        if ($recentClick !== null) {
            Log::debug('Affiliate click cooldown active', [
                'link_id'    => $link->id,
                'ip'         => $ipAddress,
                'cooldown'   => $cooldown,
            ]);
            return null;
        }

        // ตรวจจับ device type จาก user agent
        $deviceType = $this->detectDeviceType($userAgent);

        // ตรวจจับประเทศ (ถ้ามี GeoIP)
        $country = $this->detectCountry($ipAddress);

        $click = BlogAffiliateClick::create([
            'affiliate_link_id' => $link->id,
            'post_id'           => $postId,
            'user_id'           => auth()->id(),
            'ip_address'        => $ipAddress,
            'user_agent'        => mb_substr($userAgent, 0, 65535),
            'referrer'          => mb_substr($request->header('referer', ''), 0, 500),
            'country'           => $country,
            'device_type'       => $deviceType,
            'clicked_at'        => now(),
        ]);

        // เพิ่ม click counter
        $link->increment('total_clicks');

        Log::info('Affiliate click tracked', [
            'link_id'   => $link->id,
            'click_id'  => $click->id,
            'device'    => $deviceType,
            'post_id'   => $postId,
        ]);

        return $click;
    }

    /* ====================================================================
     *  Analytics
     * ==================================================================== */

    /**
     * สถิติการคลิกสำหรับลิงก์เฉพาะ
     *
     * @param  string  $period  7d, 30d, 90d, 1y, all
     */
    public function getClickStats(int $linkId, string $period = '30d'): array
    {
        $since = $this->parsePeriod($period);

        $query = BlogAffiliateClick::where('affiliate_link_id', $linkId);

        if ($since !== null) {
            $query->where('clicked_at', '>=', $since);
        }

        $totalClicks = (clone $query)->count();

        $uniqueClicks = (clone $query)
            ->distinct('ip_address')
            ->count('ip_address');

        // แยกตาม device
        $byDevice = (clone $query)
            ->select('device_type', DB::raw('COUNT(*) as count'))
            ->groupBy('device_type')
            ->pluck('count', 'device_type')
            ->toArray();

        // แยกตามประเทศ
        $byCountry = (clone $query)
            ->whereNotNull('country')
            ->select('country', DB::raw('COUNT(*) as count'))
            ->groupBy('country')
            ->orderByDesc('count')
            ->limit(10)
            ->pluck('count', 'country')
            ->toArray();

        // แยกตาม post
        $byPost = (clone $query)
            ->whereNotNull('post_id')
            ->select('post_id', DB::raw('COUNT(*) as count'))
            ->groupBy('post_id')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                $post = BlogPost::find($item->post_id);
                return [
                    'post_id' => $item->post_id,
                    'title'   => $post->title ?? 'ไม่ทราบ',
                    'clicks'  => $item->count,
                ];
            })
            ->toArray();

        // แยกตามวัน
        $byDay = (clone $query)
            ->select(DB::raw('DATE(clicked_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy(DB::raw('DATE(clicked_at)'))
            ->orderBy('date')
            ->limit(90)
            ->pluck('count', 'date')
            ->toArray();

        return [
            'total_clicks'  => $totalClicks,
            'unique_clicks' => $uniqueClicks,
            'by_device'     => $byDevice,
            'by_country'    => $byCountry,
            'by_post'       => $byPost,
            'by_day'        => $byDay,
            'period'        => $period,
        ];
    }

    /**
     * สถิติรวมสำหรับ dashboard
     */
    public function getDashboardStats(): array
    {
        $today = Carbon::today();
        $startOfMonth = Carbon::now()->startOfMonth();

        // คลิกวันนี้
        $clicksToday = BlogAffiliateClick::where('clicked_at', '>=', $today)->count();

        // คลิกเดือนนี้
        $clicksMonth = BlogAffiliateClick::where('clicked_at', '>=', $startOfMonth)->count();

        // ลิงก์ที่ active
        $activeLinks = BlogAffiliateLink::where('is_active', true)->count();

        // Top links (30 วัน)
        $topLinks = BlogAffiliateClick::select(
                'affiliate_link_id',
                DB::raw('COUNT(*) as click_count')
            )
            ->where('clicked_at', '>=', now()->subDays(30))
            ->groupBy('affiliate_link_id')
            ->orderByDesc('click_count')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                $link = BlogAffiliateLink::find($item->affiliate_link_id);
                return [
                    'link_id'    => $item->affiliate_link_id,
                    'name'       => $link->name ?? 'ไม่ทราบ',
                    'slug'       => $link->slug ?? '',
                    'clicks'     => $item->click_count,
                    'provider'   => $link->provider ?? '',
                ];
            })
            ->toArray();

        // Top posts ที่สร้าง clicks (30 วัน)
        $topPosts = BlogAffiliateClick::select(
                'post_id',
                DB::raw('COUNT(*) as click_count')
            )
            ->whereNotNull('post_id')
            ->where('clicked_at', '>=', now()->subDays(30))
            ->groupBy('post_id')
            ->orderByDesc('click_count')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                $post = BlogPost::find($item->post_id);
                return [
                    'post_id' => $item->post_id,
                    'title'   => $post->title ?? 'ไม่ทราบ',
                    'clicks'  => $item->click_count,
                ];
            })
            ->toArray();

        // คลิกรายวัน (7 วัน)
        $dailyClicks = BlogAffiliateClick::select(
                DB::raw('DATE(clicked_at) as date'),
                DB::raw('COUNT(*) as count')
            )
            ->where('clicked_at', '>=', now()->subDays(7))
            ->groupBy(DB::raw('DATE(clicked_at)'))
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();

        // รายได้รวม
        $totalRevenue = BlogAffiliateLink::sum('revenue');

        return [
            'clicks_today'  => $clicksToday,
            'clicks_month'  => $clicksMonth,
            'active_links'  => $activeLinks,
            'total_revenue'  => (float) $totalRevenue,
            'top_links'      => $topLinks,
            'top_posts'      => $topPosts,
            'daily_clicks'   => $dailyClicks,
        ];
    }

    /* ====================================================================
     *  Content Integration
     * ==================================================================== */

    /**
     * แทรก CTA blocks ในเนื้อหาบทความ
     *
     * ดึง CTA buttons ที่ตั้งค่าไว้แล้วใส่หลังย่อหน้าที่กำหนด
     */
    public function insertAffiliateBlocks(string $content, BlogPost $post): string
    {
        // ดึง CTA buttons ที่ active
        $ctaButtons = DB::table('blog_cta_buttons')
            ->where('is_active', true)
            ->where('position', 'after_paragraph')
            ->whereNotNull('show_after_paragraph')
            ->orderBy('show_after_paragraph')
            ->get();

        if ($ctaButtons->isEmpty()) {
            return $content;
        }

        // แยก content เป็นย่อหน้า
        $paragraphs = preg_split('/(<\/p>)/i', $content, -1, PREG_SPLIT_DELIM_CAPTURE);

        $output           = '';
        $paragraphCount   = 0;
        $insertedPositions = [];

        for ($i = 0; $i < count($paragraphs); $i++) {
            $output .= $paragraphs[$i];

            // นับเฉพาะ closing </p> tags
            if (strtolower(trim($paragraphs[$i])) === '</p>') {
                $paragraphCount++;

                // ตรวจสอบว่าต้องแทรก CTA หลังย่อหน้านี้ไหม
                foreach ($ctaButtons as $cta) {
                    if ($cta->show_after_paragraph === $paragraphCount
                        && !in_array($cta->id, $insertedPositions)) {
                        $output .= $this->renderCtaBlock($cta, $post->id);
                        $insertedPositions[] = $cta->id;
                    }
                }
            }
        }

        return $output;
    }

    /**
     * คำนวณผลการทดสอบ A/B สำหรับ CTA
     */
    public function getAbTestResults(int $ctaId): array
    {
        $cta = DB::table('blog_cta_buttons')->find($ctaId);

        if (!$cta) {
            return ['error' => 'ไม่พบ CTA'];
        }

        // ดึง CTA ทุก variant ที่ใช้ชื่อเดียวกัน
        $variants = DB::table('blog_cta_buttons')
            ->where('name', $cta->name)
            ->get();

        $results = [];
        $winner  = null;
        $bestCtr = 0.0;

        foreach ($variants as $variant) {
            $impressions = (int) $variant->impressions;
            $clicks      = (int) $variant->clicks;
            $ctr         = $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0.0;

            $variantResult = [
                'id'          => $variant->id,
                'variant'     => $variant->variant,
                'label'       => $variant->label,
                'style'       => $variant->style,
                'impressions' => $impressions,
                'clicks'      => $clicks,
                'ctr'         => $ctr,
            ];

            // คำนวณ confidence ด้วย z-test แบบง่าย
            if ($impressions >= 100) {
                $variantResult['sufficient_data'] = true;
            } else {
                $variantResult['sufficient_data'] = false;
            }

            $results[] = $variantResult;

            if ($ctr > $bestCtr) {
                $bestCtr = $ctr;
                $winner  = $variant->variant;
            }
        }

        // ตรวจสอบ statistical significance แบบง่าย
        $isSignificant = false;
        if (count($results) >= 2) {
            $totalImpressions = array_sum(array_column($results, 'impressions'));
            $isSignificant    = $totalImpressions >= 500;
        }

        return [
            'cta_name'       => $cta->name,
            'variants'       => $results,
            'winner'         => $winner,
            'best_ctr'       => $bestCtr,
            'is_significant' => $isSignificant,
            'recommendation' => $isSignificant
                ? "Variant {$winner} มี CTR สูงสุดที่ {$bestCtr}% แนะนำให้ใช้เป็นหลัก"
                : 'ข้อมูลยังไม่เพียงพอ ต้องการ impressions รวมอย่างน้อย 500 ครั้ง',
        ];
    }

    /* ====================================================================
     *  Internal helpers
     * ==================================================================== */

    /**
     * ตรวจจับ device type จาก User-Agent
     */
    private function detectDeviceType(string $userAgent): string
    {
        $ua = strtolower($userAgent);

        if (preg_match('/(tablet|ipad|playbook|silk)|(android(?!.*mobile))/i', $ua)) {
            return 'tablet';
        }

        if (preg_match('/(mobile|iphone|ipod|android.*mobile|windows phone|blackberry|opera mini|opera mobi)/i', $ua)) {
            return 'mobile';
        }

        if (preg_match('/(bot|crawl|spider|slurp|bingbot|googlebot)/i', $ua)) {
            return 'bot';
        }

        return 'desktop';
    }

    /**
     * ตรวจจับประเทศจาก IP (ใช้ GeoIP ถ้ามี หรือ header จาก CDN)
     */
    private function detectCountry(string $ipAddress): ?string
    {
        // ลองใช้ header จาก Cloudflare หรือ CDN อื่นก่อน
        try {
            $cfCountry = request()->header('CF-IPCountry');
            if (!empty($cfCountry) && $cfCountry !== 'XX') {
                return strtoupper($cfCountry);
            }
        } catch (\Exception) {
            // ไม่มี request context
        }

        // ลองใช้ PHP GeoIP extension
        if (function_exists('geoip_country_code_by_name')) {
            try {
                $code = @geoip_country_code_by_name($ipAddress);
                if ($code !== false) {
                    return strtoupper($code);
                }
            } catch (\Exception) {
                // Extension ไม่ทำงาน
            }
        }

        return null;
    }

    /**
     * Parse period string เป็น Carbon date
     */
    private function parsePeriod(string $period): ?Carbon
    {
        return match ($period) {
            '7d'  => Carbon::now()->subDays(7),
            '30d' => Carbon::now()->subDays(30),
            '90d' => Carbon::now()->subDays(90),
            '1y'  => Carbon::now()->subYear(),
            'all' => null,
            default => Carbon::now()->subDays(30),
        };
    }

    /**
     * Render HTML สำหรับ CTA block
     */
    private function renderCtaBlock(object $cta, int $postId): string
    {
        $linkPrefix = config('blog.affiliate.link_prefix', '/go');
        $nofollow   = config('blog.affiliate.default_nofollow', true) ? ' rel="nofollow noopener"' : '';

        // สร้าง URL -- ใช้ affiliate link slug ถ้ามี หรือ URL ตรง
        $href = $cta->url ?? '#';
        if ($cta->affiliate_link_id) {
            $affiliateLink = BlogAffiliateLink::find($cta->affiliate_link_id);
            if ($affiliateLink) {
                $href = url("{$linkPrefix}/{$affiliateLink->slug}") . "?ref=post-{$postId}";
            }
        }

        $icon     = $cta->icon ? "<i class=\"bi bi-{$cta->icon} me-2\"></i>" : '';
        $subLabel = $cta->sub_label ? "<span class=\"d-block small opacity-75\">{$cta->sub_label}</span>" : '';
        $variant  = $cta->variant ?? 'A';
        $ctaId    = $cta->id;

        return <<<HTML

<div class="blog-cta-block my-4 p-4 rounded-3 text-center" data-cta-id="{$ctaId}" data-variant="{$variant}">
    <a href="{$href}" target="_blank"{$nofollow}
       class="btn btn-lg btn-{$cta->style} px-5 py-3 fw-bold"
       data-affiliate-click="true"
       data-post-id="{$postId}">
        {$icon}{$cta->label}
        {$subLabel}
    </a>
</div>

HTML;
    }
}
