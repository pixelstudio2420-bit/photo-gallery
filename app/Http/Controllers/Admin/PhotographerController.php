<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ResolvesCommissionBounds;
use App\Http\Controllers\Controller;
use App\Models\PhotographerProfile;
use App\Models\User;
use App\Models\Event;
use App\Models\Order;
use App\Models\Review;
use App\Models\PhotographerPayout;
use App\Models\CommissionLog;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\AdminNotification;

class PhotographerController extends Controller
{
    use ResolvesCommissionBounds;

    public function index(Request $request)
    {
        // ── Stats ──
        // One aggregate query instead of pulling every photographer into
        // memory just to count them by status. The original approach broke
        // at ~2000 rows (DashboardService memory warning).
        $statsRow = DB::table('photographer_profiles')
            ->selectRaw(
                "COUNT(*) AS total,
                 SUM(CASE WHEN status = 'pending'   THEN 1 ELSE 0 END) AS pending,
                 SUM(CASE WHEN status = 'approved'  THEN 1 ELSE 0 END) AS approved,
                 SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) AS suspended"
            )
            ->first();

        $stats = [
            'total'     => (int) ($statsRow->total ?? 0),
            'pending'   => (int) ($statsRow->pending ?? 0),
            'approved'  => (int) ($statsRow->approved ?? 0),
            'suspended' => (int) ($statsRow->suspended ?? 0),
        ];

        // ── Total earnings across all photographers ──
        $stats['total_earnings'] = (float) PhotographerPayout::where('status', 'completed')
            ->sum('payout_amount');

        // ── Query with filters ──
        $photographers = PhotographerProfile::with(['user', 'events', 'reviews'])
            ->withCount(['events', 'reviews'])
            ->when($request->q, function ($q, $s) {
                $q->where(function ($q2) use ($s) {
                    $q2->where('display_name', 'ilike', "%{$s}%")
                       ->orWhere('photographer_code', 'ilike', "%{$s}%")
                       ->orWhereHas('user', fn($u) => $u->where('email', 'ilike', "%{$s}%")
                           ->orWhere('first_name', 'ilike', "%{$s}%")
                           ->orWhere('last_name', 'ilike', "%{$s}%"));
                });
            })
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->sort, function ($q, $sort) {
                return match ($sort) {
                    'events'     => $q->orderByDesc('events_count'),
                    'rating'     => $q->orderByDesc(
                        Review::selectRaw('COALESCE(AVG(rating),0)')
                            ->whereColumn('photographer_id', 'photographer_profiles.user_id')
                    ),
                    'newest'     => $q->orderByDesc('created_at'),
                    'oldest'     => $q->orderBy('created_at'),
                    'commission' => $q->orderByDesc('commission_rate'),
                    default      => $q->orderByDesc('created_at'),
                };
            }, fn($q) => $q->orderByRaw("CASE status WHEN 'pending' THEN 1 WHEN 'approved' THEN 2 WHEN 'suspended' THEN 3 ELSE 4 END")->orderByDesc('created_at'))
            ->paginate(20)
            ->withQueryString();

        return view('admin.photographers.index', compact('photographers', 'stats'));
    }

    public function create()
    {
        $users = User::whereDoesntHave('photographerProfile')
            ->where('status', 'active')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'email']);

        return view('admin.photographers.create', compact('users'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id'              => 'required|exists:auth_users,id|unique:photographer_profiles,user_id',
            'display_name'         => 'required|string|max:200',
            'bio'                  => 'nullable|string|max:1000',
            'commission_rate'      => $this->commissionRateRule(),
            'portfolio_url'        => 'nullable|url|max:500',
            'bank_name'            => 'nullable|string|max:100',
            'bank_account_number'  => 'nullable|string|max:30',
            'bank_account_name'    => 'nullable|string|max:200',
            'promptpay_number'     => 'nullable|string|max:20',
            'status'               => 'required|in:pending,approved',
        ], $this->commissionRateMessages());

        $validated['photographer_code'] = 'PH-' . strtoupper(substr(uniqid(), -6));
        $validated['approved_by'] = $validated['status'] === 'approved' ? Auth::guard('admin')->id() : null;
        $validated['approved_at'] = $validated['status'] === 'approved' ? now() : null;

        $photographer = PhotographerProfile::create($validated);

        if ($validated['status'] === 'approved') {
            $this->sendApprovalNotification($photographer);
        }

        ActivityLogger::admin(
            action: 'photographer.created',
            target: $photographer,
            description: "เพิ่มช่างภาพ \"{$photographer->display_name}\" (status: {$photographer->status}, commission: {$photographer->commission_rate}%)",
            oldValues: null,
            newValues: [
                'user_id'           => (int) $photographer->user_id,
                'display_name'      => $photographer->display_name,
                'photographer_code' => $photographer->photographer_code,
                'commission_rate'   => (float) $photographer->commission_rate,
                'status'            => $photographer->status,
            ],
        );

        return redirect()->route('admin.photographers.index')
            ->with('success', "เพิ่มช่างภาพ \"{$photographer->display_name}\" สำเร็จ");
    }

    public function show(PhotographerProfile $photographer)
    {
        $photographer->load(['user', 'events.category', 'events.photos', 'reviews.user', 'reviews.event', 'payouts']);

        // ── Stats for this photographer ──
        $stats = [
            'events_count'   => $photographer->events->count(),
            'photos_count'   => $photographer->events->sum(fn($e) => $e->photos->count()),
            'reviews_count'  => $photographer->reviews->count(),
            'avg_rating'     => $photographer->reviews->avg('rating') ?? 0,
            'total_earnings' => $photographer->payouts->where('status', 'completed')->sum('payout_amount'),
            'pending_payout' => $photographer->payouts->where('status', 'pending')->sum('payout_amount'),
            'total_orders'   => Order::whereIn('event_id', $photographer->events->pluck('id'))->count(),
            'total_revenue'  => Order::whereIn('event_id', $photographer->events->pluck('id'))
                                    ->whereIn('status', ['completed', 'paid'])->sum('total'),
        ];

        // ── Recent events (latest 10) ──
        $recentEvents = $photographer->events()->with('category')
            ->withCount('photos')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        // ── Recent payouts (latest 10) ──
        $recentPayouts = $photographer->payouts()
            ->with('order')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        // ── Reviews (latest 10) ──
        $recentReviews = $photographer->reviews()
            ->with(['user', 'event'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('admin.photographers.show', compact(
            'photographer', 'stats', 'recentEvents', 'recentPayouts', 'recentReviews'
        ));
    }

    public function edit(PhotographerProfile $photographer)
    {
        $photographer->load('user');
        return view('admin.photographers.edit', compact('photographer'));
    }

    public function update(Request $request, PhotographerProfile $photographer)
    {
        $validated = $request->validate([
            'display_name'         => 'required|string|max:200',
            'bio'                  => 'nullable|string|max:1000',
            'commission_rate'      => $this->commissionRateRule(),
            'status'               => 'required|in:pending,approved,suspended',
            'portfolio_url'        => 'nullable|url|max:500',
            'bank_name'            => 'nullable|string|max:100',
            'bank_account_number'  => 'nullable|string|max:30',
            'bank_account_name'    => 'nullable|string|max:200',
            'promptpay_number'     => 'nullable|string|max:20',
        ], $this->commissionRateMessages());

        $oldStatus = $photographer->status;
        $oldSnapshot = [
            'display_name'    => $photographer->display_name,
            'commission_rate' => (float) $photographer->commission_rate,
            'status'          => $photographer->status,
            'bank_name'       => $photographer->bank_name,
            // Bank account numbers are redacted by ActivityLogger automatically if keys
            // include 'account'. We log the LAST 4 digits only for traceability.
            'bank_account_last4' => $photographer->bank_account_number
                ? substr($photographer->bank_account_number, -4)
                : null,
        ];

        // Handle status transitions
        if ($validated['status'] === 'approved' && $oldStatus !== 'approved') {
            $validated['approved_at'] = now();
            $validated['approved_by'] = Auth::guard('admin')->id();
        }

        $photographer->update($validated);

        // Send notification on approval
        if ($validated['status'] === 'approved' && $oldStatus !== 'approved') {
            $this->sendApprovalNotification($photographer);
        }

        ActivityLogger::admin(
            action: 'photographer.updated',
            target: $photographer,
            description: "แก้ไขช่างภาพ \"{$photographer->display_name}\"" . ($oldStatus !== $photographer->status ? " (สถานะ: {$oldStatus} → {$photographer->status})" : ''),
            oldValues: $oldSnapshot,
            newValues: [
                'display_name'    => $photographer->display_name,
                'commission_rate' => (float) $photographer->commission_rate,
                'status'          => $photographer->status,
                'bank_name'       => $photographer->bank_name,
                'bank_account_last4' => $photographer->bank_account_number
                    ? substr($photographer->bank_account_number, -4)
                    : null,
            ],
        );

        return redirect()->route('admin.photographers.show', $photographer)
            ->with('success', 'อัพเดทข้อมูลช่างภาพสำเร็จ');
    }

    public function destroy(PhotographerProfile $photographer)
    {
        $oldStatus = $photographer->status;
        $photographer->update(['status' => 'suspended']);

        ActivityLogger::admin(
            action: 'photographer.suspended',
            target: $photographer,
            description: "ระงับช่างภาพ \"{$photographer->display_name}\"",
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => 'suspended'],
        );

        return redirect()->route('admin.photographers.index')
            ->with('success', "ระงับช่างภาพ \"{$photographer->display_name}\" สำเร็จ");
    }

    public function approve(PhotographerProfile $photographer)
    {
        $oldStatus = $photographer->status;
        $photographer->update([
            'status'      => 'approved',
            'approved_at' => now(),
            'approved_by' => Auth::guard('admin')->id(),
        ]);

        $this->sendApprovalNotification($photographer);

        ActivityLogger::admin(
            action: 'photographer.approved',
            target: $photographer,
            description: "อนุมัติช่างภาพ \"{$photographer->display_name}\" (commission: {$photographer->commission_rate}%)",
            oldValues: ['status' => $oldStatus],
            newValues: [
                'status'          => 'approved',
                'commission_rate' => (float) $photographer->commission_rate,
                'user_id'         => (int) $photographer->user_id,
            ],
        );

        return back()->with('success', "อนุมัติช่างภาพ \"{$photographer->display_name}\" สำเร็จ");
    }

    public function suspend(Request $request, PhotographerProfile $photographer)
    {
        $oldStatus = $photographer->status;
        $photographer->update(['status' => 'suspended']);

        ActivityLogger::admin(
            action: 'photographer.suspended',
            target: $photographer,
            description: "ระงับช่างภาพ \"{$photographer->display_name}\"",
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => 'suspended'],
        );

        // Audit-trail notification in the admin bell — gives other
        // admins visibility when one of them suspends a photographer.
        try {
            AdminNotification::photographerStatusChange(
                $photographer, 'suspend', Auth::guard('admin')->id()
            );
        } catch (\Throwable $e) {}

        // Notify the photographer themselves so they know why their
        // dashboard suddenly shows access errors.
        try {
            if ($photographer->user_id) {
                \App\Models\UserNotification::photographerSuspended(
                    $photographer->user_id,
                    $request->input('reason') ?: $photographer->rejection_reason
                );
            }
        } catch (\Throwable $e) {}

        return back()->with('success', "ระงับช่างภาพ \"{$photographer->display_name}\" สำเร็จ");
    }

    public function reactivate(PhotographerProfile $photographer)
    {
        $oldStatus = $photographer->status;
        $photographer->update([
            'status'      => 'approved',
            'approved_at' => now(),
            'approved_by' => Auth::guard('admin')->id(),
        ]);

        try {
            AdminNotification::photographerStatusChange(
                $photographer, 'reactivate', Auth::guard('admin')->id()
            );
        } catch (\Throwable $e) {}

        ActivityLogger::admin(
            action: 'photographer.reactivated',
            target: $photographer,
            description: "เปิดใช้งานช่างภาพ \"{$photographer->display_name}\" อีกครั้ง",
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => 'approved'],
        );

        return back()->with('success', "เปิดใช้งานช่างภาพ \"{$photographer->display_name}\" สำเร็จ");
    }

    public function toggleStatus(PhotographerProfile $photographer)
    {
        $oldStatus = $photographer->status;

        if ($photographer->status === 'approved') {
            $photographer->update(['status' => 'suspended']);
            $msg = "ระงับช่างภาพ \"{$photographer->display_name}\" สำเร็จ";
        } else {
            $photographer->update([
                'status'      => 'approved',
                'approved_at' => now(),
                'approved_by' => Auth::guard('admin')->id(),
            ]);
            $msg = "เปิดใช้งานช่างภาพ \"{$photographer->display_name}\" สำเร็จ";
        }

        ActivityLogger::admin(
            action: 'photographer.status_toggled',
            target: $photographer,
            description: "สลับสถานะช่างภาพ \"{$photographer->display_name}\" ({$oldStatus} → {$photographer->status})",
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => $photographer->status],
        );

        return back()->with('success', $msg);
    }

    public function adjustCommission(Request $request, PhotographerProfile $photographer)
    {
        $request->validate(
            ['commission_rate' => $this->commissionRateRule()],
            $this->commissionRateMessages()
        );

        $old = $photographer->commission_rate;
        $photographer->update(['commission_rate' => $request->commission_rate]);

        CommissionLog::record(
            $photographer->user_id, $old, $request->commission_rate,
            'manual', $request->reason, Auth::guard('admin')->id()
        );

        ActivityLogger::admin(
            action: 'photographer.commission_adjusted',
            target: $photographer,
            description: "ปรับค่าคอมมิชชั่น \"{$photographer->display_name}\" จาก {$old}% → {$request->commission_rate}%",
            oldValues: ['commission_rate' => (float) $old],
            newValues: [
                'commission_rate' => (float) $request->commission_rate,
                'reason'          => $request->reason,
            ],
        );

        return back()->with('success', "ปรับค่าคอมมิชชั่น {$old}% → {$request->commission_rate}% สำเร็จ");
    }

    /**
     * Reset password for the user account linked to this photographer.
     * Only permitted for local/email auth_provider accounts.
     */
    public function resetPassword(Request $request, PhotographerProfile $photographer)
    {
        $user = $photographer->user;
        if (!$user) {
            return back()->with('error', 'ไม่พบบัญชีผู้ใช้ที่เชื่อมกับช่างภาพนี้');
        }

        $provider = strtolower((string) ($user->auth_provider ?? 'local'));
        $isLocal  = in_array($provider, ['', 'local', 'email'], true);
        if (!$isLocal) {
            return back()->with('error',
                "ไม่สามารถรีเซ็ตรหัสผ่านได้ — บัญชีนี้สมัครผ่าน \"{$provider}\" ให้ผู้ใช้เข้าสู่ระบบผ่าน social login แทน");
        }

        if (!\Schema::hasColumn($user->getTable(), 'password_hash')) {
            return back()->with('error', 'โครงสร้างฐานข้อมูลไม่รองรับการรีเซ็ตรหัสผ่าน');
        }

        $newPass = $this->generateSecurePassword(10);
        $user->update(['password_hash' => Hash::make($newPass)]);

        try {
            AdminNotification::create([
                'type'    => 'security',
                'title'   => 'รีเซ็ตรหัสผ่านช่างภาพ',
                'message' => "รหัสผ่านของช่างภาพ #{$photographer->id} ({$user->email}) ถูกรีเซ็ตโดยแอดมิน",
                'ref_id'  => (string) $user->id,
                'read_at' => null,
            ]);
        } catch (\Throwable $e) {
            \Log::warning('AdminNotification create failed: ' . $e->getMessage());
        }

        // Note: we intentionally DO NOT log the new password here — ActivityLogger
        // auto-redacts it anyway, but better not to pass it in the first place.
        ActivityLogger::admin(
            action: 'photographer.password_reset',
            target: $photographer,
            description: "รีเซ็ตรหัสผ่านช่างภาพ \"{$photographer->display_name}\" ({$user->email})",
            oldValues: null,
            newValues: [
                'photographer_id' => (int) $photographer->id,
                'user_id'         => (int) $user->id,
                'user_email'      => $user->email,
            ],
        );

        return back()
            ->with('success', 'รีเซ็ตรหัสผ่านสำเร็จ — กรุณาคัดลอกรหัสใหม่ด้านล่างและส่งให้ผู้ใช้อย่างปลอดภัย')
            ->with('new_password', $newPass);
    }

    protected function generateSecurePassword(int $len = 10): string
    {
        $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lower = 'abcdefghijkmnpqrstuvwxyz';
        $digit = '23456789';
        $all   = $upper . $lower . $digit;
        $pw    = [
            $upper[random_int(0, strlen($upper) - 1)],
            $lower[random_int(0, strlen($lower) - 1)],
            $digit[random_int(0, strlen($digit) - 1)],
        ];
        for ($i = count($pw); $i < $len; $i++) {
            $pw[] = $all[random_int(0, strlen($all) - 1)];
        }
        shuffle($pw);
        return implode('', $pw);
    }

    protected function sendApprovalNotification(PhotographerProfile $photographer): void
    {
        try {
            $user = $photographer->user;
            if (!$user) return;

            // 1. Email
            $mail = app(\App\Services\MailService::class);
            $mail->photographerApproved($user->email, $user->first_name);

            // 2. LINE
            $line = app(\App\Services\LineNotifyService::class);
            $line->pushText($photographer->user_id, '✅ บัญชีช่างภาพของคุณได้รับการอนุมัติแล้ว!');

            // 3. In-app notification
            \App\Models\UserNotification::photographerApproved($user->id);
        } catch (\Throwable $e) {
            \Log::warning('Photographer notification failed: ' . $e->getMessage());
        }
    }
}
