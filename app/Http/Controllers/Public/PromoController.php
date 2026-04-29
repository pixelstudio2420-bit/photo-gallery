<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\StoragePlan;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Promo / "Why us" landing page.
 *
 * Pitches the 3 killer USPs for the Thai market:
 *   1. LINE-first delivery (ส่งรูปเข้า LINE หลังจ่ายเงินเสร็จ)
 *   2. Face Search AI (อัปโหลด selfie → เจอตัวเองในงาน 1,000+ ใบ)
 *   3. Auto-payout to bank for photographers (โอนเข้าบัญชีอัตโนมัติ)
 *
 * Light controller — page is largely static + cached for 5 min on edge.
 * Pulls live counts so the social-proof numbers don't lie.
 */
class PromoController extends Controller
{
    public function index()
    {
        // Live counts (cached) so social-proof numbers reflect reality.
        $stats = Cache::remember('public.promo.stats', 300, function () {
            try {
                $eventsCount = Schema::hasTable('event_events')
                    ? DB::table('event_events')
                        ->whereIn('status', ['active', 'published'])
                        ->count()
                    : 0;

                $photographersCount = Schema::hasTable('photographer_profiles')
                    ? DB::table('photographer_profiles')->where('status', 'approved')->count()
                    : 0;

                $photosCount = Schema::hasTable('photo_photos')
                    ? DB::table('photo_photos')->count()
                    : 0;

                $ordersCount = Schema::hasTable('order_orders')
                    ? DB::table('order_orders')->where('status', 'completed')->count()
                    : 0;

                return [
                    'events'         => max($eventsCount, 0),
                    'photographers'  => max($photographersCount, 0),
                    'photos_indexed' => max($photosCount, 0),
                    'orders'         => max($ordersCount, 0),
                ];
            } catch (\Throwable $e) {
                Log::warning('promo.stats_failed', ['err' => $e->getMessage()]);
                return [
                    'events'         => 0,
                    'photographers'  => 0,
                    'photos_indexed' => 0,
                    'orders'         => 0,
                ];
            }
        });

        // Featured photographer plans (skipped if subscriptions disabled)
        $photographerPlans = Cache::remember('public.promo.photographer_plans', 300, function () {
            try {
                if (!Schema::hasTable('subscription_plans')) return collect();
                return SubscriptionPlan::where('is_active', 1)
                    ->where('is_public', 1)
                    ->orderBy('sort_order')
                    ->get();
            } catch (\Throwable $e) {
                Log::warning('promo.photographer_plans_failed', ['err' => $e->getMessage()]);
                return collect();
            }
        });

        // Storage plans for end-users (skipped if disabled)
        $storagePlans = Cache::remember('public.promo.storage_plans', 300, function () {
            try {
                if (!Schema::hasTable('storage_plans')) return collect();
                return StoragePlan::where('is_active', 1)
                    ->where('is_public', 1)
                    ->orderBy('sort_order')
                    ->get();
            } catch (\Throwable $e) {
                Log::warning('promo.storage_plans_failed', ['err' => $e->getMessage()]);
                return collect();
            }
        });

        // 4 latest featured events for the social-proof gallery strip
        $featuredEvents = Cache::remember('public.promo.featured_events', 300, function () {
            try {
                if (!Schema::hasTable('event_events')) return collect();
                return Event::with('category')
                    ->whereIn('status', ['active', 'published'])
                    ->where('visibility', 'public')
                    ->orderByDesc('shoot_date')
                    ->limit(4)
                    ->get();
            } catch (\Throwable $e) {
                Log::warning('promo.featured_events_failed', ['err' => $e->getMessage()]);
                return collect();
            }
        });

        // SEO + breadcrumbs
        try {
            $seo = app(\App\Services\SeoService::class);
            $seo->set([
                'title'       => 'ทำไมต้องเลือกเรา · ขายรูปอีเวนต์ผ่าน LINE + AI หาใบหน้า',
                'description' => 'แพลตฟอร์มภาพอีเวนต์ที่จ่ายผ่าน PromptPay ส่งรูปเข้า LINE ทันที พร้อม AI หาใบหน้าตัวเองในรูปนับพันใบ และโอนเงินเข้าบัญชีช่างภาพอัตโนมัติ',
            ]);
            $seo->setBreadcrumbs([
                ['name' => 'หน้าแรก', 'url' => route('home')],
                ['name' => 'ทำไมต้องเลือกเรา'],
            ]);
        } catch (\Throwable $e) {
            Log::warning('promo.seo_failed', ['err' => $e->getMessage()]);
        }

        return view('public.promo', compact(
            'stats',
            'photographerPlans',
            'storagePlans',
            'featuredEvents',
        ));
    }
}
