<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\StoragePlan;
use App\Models\User;
use App\Models\UserFile;
use App\Models\UserStorageInvoice;
use App\Models\UserStorageSubscription;
use App\Services\UserStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Admin face of the consumer cloud-storage system.
 *
 * Three surfaces sharing this controller because they're all read-faces
 * of the same feature and usage is low-volume enough that splitting would
 * just mean more boilerplate:
 *
 *   • /admin/user-storage            — platform KPIs + toggle controls
 *   • /admin/user-storage/subscribers — paid/free-tier user list + detail
 *   • /admin/user-storage/actions     — cancel/resume/extend-grace workflows
 *
 * Plan CRUD lives in StoragePlanController; file monitoring lives in
 * UserFilesController.
 */
class UserStorageController extends Controller
{
    public function __construct(private UserStorageService $storage) {}

    // ═══════════════════════════════════════════════════════════════════
    //  Platform overview
    // ═══════════════════════════════════════════════════════════════════

    public function index(): View
    {
        $kpis  = $this->storage->platformKpis();
        $plans = StoragePlan::ordered()->get();

        // Map the plan_id-keyed distribution to plan objects so the view can
        // render pretty names + colours instead of raw IDs.
        $byPlanLabeled = [];
        foreach ($plans as $p) {
            $byPlanLabeled[] = [
                'plan'  => $p,
                'count' => (int) ($kpis['by_plan'][$p->id] ?? 0),
            ];
        }

        // Last 20 invoices for the footer table — cheap, and operators usually
        // want to eyeball recent billing activity.
        $recentInvoices = UserStorageInvoice::with(['user:id,first_name,last_name,email', 'subscription.plan:id,name,code'])
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        // Grace-expiring-soon leaderboard — the most actionable list for ops.
        $graceSoon = UserStorageSubscription::with(['user:id,first_name,last_name,email', 'plan:id,name,code'])
            ->where('status', UserStorageSubscription::STATUS_GRACE)
            ->whereNotNull('grace_ends_at')
            ->orderBy('grace_ends_at')
            ->limit(10)
            ->get();

        $settings = [
            'sales_mode_storage_enabled' => ((string) AppSetting::get('sales_mode_storage_enabled', '0')) === '1',
            'user_storage_enabled'       => ((string) AppSetting::get('user_storage_enabled', '0')) === '1',
            'default_user_storage_plan'  => (string) AppSetting::get('default_user_storage_plan', StoragePlan::CODE_FREE),
            'grace_period_days'          => (int) AppSetting::get('user_storage_grace_period_days', '7'),
        ];

        return view('admin.user-storage.index', [
            'kpis'           => $kpis,
            'plansKpi'       => $byPlanLabeled,
            'plans'          => $plans,
            'recentInvoices' => $recentInvoices,
            'graceSoon'      => $graceSoon,
            'settings'       => $settings,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    //  Settings toggles
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Flip one of the module-level toggles. Separate from generic app-settings
     * page because these have real user-facing impact (new sign-ups, uploads)
     * and we want the audit trail on this controller.
     */
    public function toggleSetting(Request $request)
    {
        $key = $request->validate([
            'key' => 'required|string|in:sales_mode_storage_enabled,user_storage_enabled',
        ])['key'];

        $current = ((string) AppSetting::get($key, '0')) === '1';
        $next    = !$current ? '1' : '0';
        AppSetting::set($key, $next);

        Log::info('user_storage.admin.toggle', [
            'key'     => $key,
            'from'    => $current ? '1' : '0',
            'to'      => $next,
            'admin_id'=> optional(auth('admin')->user())->id,
        ]);

        $label = $key === 'sales_mode_storage_enabled'
            ? 'โหมดขายพื้นที่เก็บไฟล์'
            : 'ระบบ Cloud Storage';

        return back()->with('success', "{$label}: " . ($next === '1' ? 'เปิดใช้งาน' : 'ปิดใช้งาน'));
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'default_user_storage_plan'      => 'required|string|max:40',
            'user_storage_grace_period_days' => 'required|integer|min:0|max:60',
        ]);

        AppSetting::set('default_user_storage_plan', $data['default_user_storage_plan']);
        AppSetting::set('user_storage_grace_period_days', (string) $data['user_storage_grace_period_days']);

        return back()->with('success', 'บันทึกการตั้งค่าเรียบร้อย');
    }

    // ═══════════════════════════════════════════════════════════════════
    //  Subscribers list + detail
    // ═══════════════════════════════════════════════════════════════════

    public function subscribers(Request $request): View
    {
        $search = trim((string) $request->string('q'));
        $status = trim((string) $request->string('status'));
        $plan   = trim((string) $request->string('plan'));

        // Base query is on auth_users so we can show free-tier users (no subscription row)
        // alongside paid ones. Left-join the current subscription for status + plan code.
        $q = DB::table('auth_users')
            ->leftJoin('user_storage_subscriptions', 'user_storage_subscriptions.id', '=', 'auth_users.current_storage_sub_id')
            ->leftJoin('storage_plans', 'storage_plans.id', '=', 'user_storage_subscriptions.plan_id')
            ->select([
                'auth_users.id',
                'auth_users.email',
                'auth_users.first_name',
                'auth_users.last_name',
                'auth_users.storage_used_bytes',
                'auth_users.storage_quota_bytes',
                'auth_users.storage_plan_code',
                'auth_users.storage_plan_status',
                'auth_users.storage_renews_at',
                'user_storage_subscriptions.id as subscription_id',
                'user_storage_subscriptions.status as sub_status',
                'user_storage_subscriptions.current_period_end as sub_period_end',
                'user_storage_subscriptions.grace_ends_at as sub_grace_ends',
                'storage_plans.name as plan_name',
                'storage_plans.code as plan_code',
                'storage_plans.color_hex as plan_color',
                'storage_plans.price_thb as plan_price',
            ])
            ->orderByDesc('auth_users.storage_used_bytes');

        if ($search !== '') {
            $like = "%{$search}%";
            $q->where(function ($qq) use ($like) {
                $qq->where('auth_users.first_name', 'ilike', $like)
                   ->orWhere('auth_users.last_name', 'ilike', $like)
                   ->orWhere('auth_users.email', 'ilike', $like);
            });
        }

        if ($status !== '') {
            if ($status === 'free') {
                $q->where('auth_users.storage_plan_code', StoragePlan::CODE_FREE);
            } elseif ($status === 'paid') {
                $q->where('auth_users.storage_plan_code', '!=', StoragePlan::CODE_FREE);
            } else {
                $q->where('user_storage_subscriptions.status', $status);
            }
        }

        if ($plan !== '') {
            $q->where('auth_users.storage_plan_code', $plan);
        }

        $subscribers = $q->paginate(30)->withQueryString();
        $planOptions = StoragePlan::ordered()->get(['id', 'code', 'name']);

        return view('admin.user-storage.subscribers.index', [
            'subscribers' => $subscribers,
            'planOptions' => $planOptions,
            'search'      => $search,
            'status'      => $status,
            'plan'        => $plan,
        ]);
    }

    public function subscriberShow(User $user): View
    {
        $sub      = $this->storage->currentSubscription($user);
        $plan     = $this->storage->currentPlan($user);
        $summary  = $this->storage->dashboardSummary($user);

        $subscriptions = UserStorageSubscription::where('user_id', $user->id)
            ->with('plan:id,name,code,color_hex')
            ->orderByDesc('id')
            ->get();

        $invoices = UserStorageInvoice::where('user_id', $user->id)
            ->with('subscription.plan:id,name,code')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $fileStats = [
            'total_files'   => UserFile::where('user_id', $user->id)->count(),
            'trashed_files' => UserFile::onlyTrashed()->where('user_id', $user->id)->count(),
            'shared_files'  => UserFile::where('user_id', $user->id)->whereNotNull('share_token')->count(),
        ];

        return view('admin.user-storage.subscribers.show', [
            'user'          => $user,
            'sub'           => $sub,
            'plan'          => $plan,
            'summary'       => $summary,
            'subscriptions' => $subscriptions,
            'invoices'      => $invoices,
            'fileStats'     => $fileStats,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    //  Subscriber actions (admin-initiated)
    // ═══════════════════════════════════════════════════════════════════

    public function adminCancel(Request $request, UserStorageSubscription $subscription)
    {
        $immediate = (bool) $request->boolean('immediate');
        $this->storage->cancel($subscription, $immediate);

        Log::info('user_storage.admin.cancel', [
            'subscription_id' => $subscription->id,
            'user_id'         => $subscription->user_id,
            'immediate'       => $immediate,
            'admin_id'        => optional(auth('admin')->user())->id,
        ]);

        return back()->with('success', $immediate ? 'ยกเลิกแผนทันทีเรียบร้อย' : 'ยกเลิกแผน (มีผลเมื่อสิ้นรอบบิล) เรียบร้อย');
    }

    public function adminResume(UserStorageSubscription $subscription)
    {
        $this->storage->resume($subscription);

        Log::info('user_storage.admin.resume', [
            'subscription_id' => $subscription->id,
            'user_id'         => $subscription->user_id,
            'admin_id'        => optional(auth('admin')->user())->id,
        ]);

        return back()->with('success', 'กู้คืนแผนเรียบร้อย');
    }

    public function extendGrace(Request $request, UserStorageSubscription $subscription)
    {
        $days = (int) $request->validate([
            'days' => 'required|integer|min:1|max:60',
        ])['days'];

        $subscription->update([
            'grace_ends_at' => ($subscription->grace_ends_at ?? now())->copy()->addDays($days),
            'status'        => UserStorageSubscription::STATUS_GRACE,
        ]);

        Log::info('user_storage.admin.extend_grace', [
            'subscription_id' => $subscription->id,
            'days'            => $days,
            'admin_id'        => optional(auth('admin')->user())->id,
        ]);

        return back()->with('success', "ขยาย grace period อีก {$days} วันเรียบร้อย");
    }

    public function recalcUsage(User $user)
    {
        $bytes = $this->storage->recalcUsedBytes($user);

        return back()->with('success', 'คำนวณพื้นที่ใหม่เรียบร้อย — ใช้ไป ' . round($bytes / (1024 ** 3), 2) . ' GB');
    }
}
