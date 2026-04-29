<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\StoragePlan;
use App\Models\SubscriptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
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

    /**
     * Smart "buy this plan" redirect.
     *
     * Routed from each plan card on /promo. Funnels every user into the
     * shortest path that ends at a payable subscription page, regardless
     * of whether they already have a photographer profile.
     *
     * Decision tree:
     *   - Plan code unknown / inactive → bounce back to /promo with error.
     *   - Authenticated photographer       → /photographer/subscription/plans?plan=…
     *     (the plans page reads ?plan=… and scrolls to / pre-selects that
     *     card so the user is one click from "Subscribe").
     *   - Authenticated user, no profile   → /photographer/register
     *     (claim flow), with the plan code stashed in the session so the
     *     register handler can finish the journey.
     *   - Anonymous                        → /photographer/register, same
     *     session-stash treatment.
     *
     * The session key used (`intended_plan`) is consumed by
     * Photographer\AuthController::register() and ::claim() — see those
     * methods for the post-signup redirect.
     */
    public function checkout(string $code): RedirectResponse
    {
        $plan = null;
        try {
            $plan = SubscriptionPlan::where('code', $code)
                ->where('is_active', 1)
                ->first();
        } catch (\Throwable $e) {
            Log::warning('promo.checkout.plan_lookup_failed', ['code' => $code, 'err' => $e->getMessage()]);
        }

        if (!$plan) {
            return redirect()->route('promo')
                ->with('error', 'ไม่พบแผนที่เลือก หรือแผนนี้ปิดให้สมัครชั่วคราว');
        }

        if (Auth::check()) {
            $user = Auth::user();
            // Already a photographer → land on the plans page with the
            // selection pre-filled. Subscription plans page reads
            // ?plan=… and uses it to scroll/highlight the matching card.
            if ($user->photographerProfile) {
                return redirect()->to(
                    route('photographer.subscription.plans') . '?plan=' . urlencode($code) . '#plan-' . $code
                );
            }

            // Logged in but not a photographer (e.g. customer who came
            // from /promo). Funnel through the photographer claim flow;
            // the session key lets register/claim finish the redirect.
            session(['intended_plan' => $code]);
            return redirect()->route('photographer.register')
                ->with('info', 'เปิดบัญชีช่างภาพก่อน — แล้วระบบจะพาคุณไปหน้าสมัครแผน “' . $plan->name . '” อัตโนมัติ');
        }

        // Anonymous → store plan in session + send to register. The
        // register page also accepts a "Login" link, and once they
        // authenticate the same intended_plan key gets consumed.
        session(['intended_plan' => $code]);
        return redirect()->route('photographer.register')
            ->with('info', 'สร้างบัญชีก่อน — แล้วระบบจะพาคุณไปหน้าสมัครแผน “' . $plan->name . '” อัตโนมัติ');
    }
}
