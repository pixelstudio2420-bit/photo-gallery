<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Order;
use App\Models\Review;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class UserController extends Controller
{
    // ═══════════════════════════════════════
    //  Index — Dashboard + List
    // ═══════════════════════════════════════
    public function index(Request $request)
    {
        // ── Stats (aggregate query + 60s cache) ──
        // Previously: User::get() loaded every user into memory to filter in PHP.
        // This OOM'd once the users table got large. Replaced with one SQL aggregate.
        $stats = Cache::remember('admin.users.stats', 60, function () {
            $defaults = [
                'total' => 0, 'active' => 0, 'suspended' => 0, 'verified' => 0,
                'new_today' => 0, 'new_this_week' => 0, 'new_this_month' => 0,
                'active_today' => 0,
            ];
            try {
                // Postgres: SUM(boolean) → COUNT(*) FILTER (WHERE ...)
                $r = DB::selectOne(
                    "SELECT
                        COUNT(*) AS total,
                        COUNT(*) FILTER (WHERE status='active') AS active,
                        COUNT(*) FILTER (WHERE status IN ('suspended','blocked')) AS suspended,
                        COUNT(*) FILTER (WHERE email_verified = true) AS verified,
                        COUNT(*) FILTER (WHERE created_at >= CURRENT_DATE) AS new_today,
                        COUNT(*) FILTER (WHERE created_at >= NOW() - INTERVAL '7 days') AS new_this_week,
                        COUNT(*) FILTER (WHERE created_at >= NOW() - INTERVAL '30 days') AS new_this_month,
                        COUNT(*) FILTER (WHERE last_login_at >= CURRENT_DATE) AS active_today
                     FROM auth_users"
                );
                if ($r) {
                    foreach ($defaults as $k => $_) {
                        $defaults[$k] = (int) ($r->{$k} ?? 0);
                    }
                }
            } catch (\Throwable $e) {}
            return $defaults;
        });
        $stats['total_revenue'] = Cache::remember('admin.users.total_revenue', 60, function () {
            try {
                return (float) Order::whereIn('status', ['completed', 'paid'])->sum('total');
            } catch (\Throwable $e) {
                return 0.0;
            }
        });

        // ── Query with rich filters ──
        $users = User::withCount(['orders', 'reviews'])
            ->when($request->q, function ($q, $s) {
                $q->where(function ($q2) use ($s) {
                    $q2->where('first_name', 'ilike', "%{$s}%")
                       ->orWhere('last_name', 'ilike', "%{$s}%")
                       ->orWhere('email', 'ilike', "%{$s}%")
                       ->orWhere('phone', 'ilike', "%{$s}%")
                       ->orWhere('username', 'ilike', "%{$s}%");
                });
            })
            ->when($request->status, function ($q, $status) {
                if ($status === 'suspended') {
                    $q->whereIn('status', ['suspended', 'blocked']);
                } else {
                    $q->where('status', $status);
                }
            })
            ->when($request->verified, function ($q, $v) {
                $q->where('email_verified', $v === 'yes');
            })
            ->when($request->provider, function ($q, $p) {
                if ($p === 'email') {
                    $q->whereNull('auth_provider');
                } else {
                    $q->where('auth_provider', $p);
                }
            })
            ->when($request->period, function ($q, $p) {
                return match ($p) {
                    'today'     => $q->where('created_at', '>=', now()->startOfDay()),
                    'week'      => $q->where('created_at', '>=', now()->subDays(7)),
                    'month'     => $q->where('created_at', '>=', now()->subDays(30)),
                    '3months'   => $q->where('created_at', '>=', now()->subMonths(3)),
                    default     => $q,
                };
            })
            ->when($request->has_orders, function ($q) {
                $q->has('orders');
            })
            ->when($request->is_photographer, function ($q) {
                $q->whereHas('photographerProfile');
            })
            ->when($request->sort, function ($q, $sort) {
                return match ($sort) {
                    'name'       => $q->orderBy('first_name')->orderBy('last_name'),
                    'email'      => $q->orderBy('email'),
                    'orders'     => $q->orderByDesc('orders_count'),
                    'login'      => $q->orderByDesc('last_login_at'),
                    'oldest'     => $q->orderBy('created_at'),
                    default      => $q->orderByDesc('created_at'),
                };
            }, fn($q) => $q->orderByDesc('created_at'))
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', compact('users', 'stats'));
    }

    // ═══════════════════════════════════════
    //  Create / Store
    // ═══════════════════════════════════════
    public function create()
    {
        return view('admin.users.form');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'email'      => 'required|email|unique:auth_users,email',
            'phone'      => 'nullable|string|max:20',
            'password'   => 'required|min:8|confirmed',
            'status'     => 'required|in:active,suspended',
        ]);

        $user = User::create([
            'first_name'      => $validated['first_name'],
            'last_name'       => $validated['last_name'],
            'email'           => $validated['email'],
            'phone'           => $validated['phone'] ?? null,
            'password_hash'   => Hash::make($validated['password']),
            'status'          => $validated['status'],
            'email_verified'  => $request->boolean('email_verified'),
        ]);

        ActivityLogger::admin(
            action: 'user.created',
            target: $user,
            description: "สร้างบัญชีผู้ใช้ {$user->email}",
            oldValues: null,
            newValues: [
                'email'          => $user->email,
                'full_name'      => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                'status'         => $user->status,
                'email_verified' => (bool) $user->email_verified,
            ],
        );

        return redirect()->route('admin.users.show', $user)
            ->with('success', "สร้างผู้ใช้ \"{$user->full_name}\" สำเร็จ");
    }

    // ═══════════════════════════════════════
    //  Show — Rich Detail View
    // ═══════════════════════════════════════
    public function show(User $user)
    {
        $user->load(['orders.event', 'reviews.event', 'socialLogins', 'photographerProfile']);

        $stats = [
            'orders_count'     => $user->orders->count(),
            'total_spent'      => $user->orders->whereIn('status', ['completed', 'paid'])->sum('total'),
            'reviews_count'    => $user->reviews->count(),
            'avg_rating'       => $user->reviews->avg('rating') ?? 0,
            'login_count'      => $user->login_count ?? 0,
            'days_since_signup' => $user->created_at ? $user->created_at->diffInDays(now()) : 0,
        ];

        $recentOrders = $user->orders()
            ->with('event')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $recentReviews = $user->reviews()
            ->with('event')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        // Activity from user_sessions — table is created lazily by the presence
        // migration; guard so an un-migrated install doesn't crash the profile page.
        $sessions = collect();
        if (\Illuminate\Support\Facades\Schema::hasTable('user_sessions')) {
            try {
                $sessions = DB::table('user_sessions')
                    ->where('user_id', $user->id)
                    ->orderByDesc('last_activity')
                    ->limit(10)
                    ->get();
            } catch (\Throwable $e) {
                $sessions = collect();
            }
        }

        return view('admin.users.show', compact('user', 'stats', 'recentOrders', 'recentReviews', 'sessions'));
    }

    // ═══════════════════════════════════════
    //  Edit / Update
    // ═══════════════════════════════════════
    public function edit(User $user)
    {
        return view('admin.users.form', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        // Toggle block action
        if ($request->has('toggle_block')) {
            $oldStatus = $user->status;
            $newStatus = $user->status === 'active' ? 'suspended' : 'active';
            $user->update(['status' => $newStatus]);

            ActivityLogger::admin(
                action: $newStatus === 'suspended' ? 'user.suspended' : 'user.reactivated',
                target: $user,
                description: ($newStatus === 'suspended' ? 'ระงับ' : 'ปลดบล็อค') . "บัญชีผู้ใช้ {$user->email}",
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => $newStatus],
            );

            $label = $newStatus === 'active' ? 'ปลดบล็อค' : 'ระงับ';
            return back()->with('success', "{$label}ผู้ใช้ \"{$user->full_name}\" สำเร็จ");
        }

        // Toggle email verified
        if ($request->has('toggle_verified')) {
            $oldVerified = (bool) $user->email_verified;
            $user->update([
                'email_verified' => !$user->email_verified,
                'email_verified_at' => !$user->email_verified ? now() : null,
            ]);

            ActivityLogger::admin(
                action: 'user.email_verified_toggled',
                target: $user,
                description: "สลับสถานะยืนยันอีเมลของ {$user->email}",
                oldValues: ['email_verified' => $oldVerified],
                newValues: ['email_verified' => !$oldVerified],
            );

            return back()->with('success', 'อัปเดตสถานะยืนยันอีเมลสำเร็จ');
        }

        // Full update
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'email'      => 'required|email|unique:auth_users,email,' . $user->id,
            'phone'      => 'nullable|string|max:20',
            'password'   => 'nullable|min:8|confirmed',
            'status'     => 'required|in:active,suspended',
        ]);

        // Snapshot old state for audit
        $oldSnapshot = [
            'first_name'     => $user->first_name,
            'last_name'      => $user->last_name,
            'email'          => $user->email,
            'phone'          => $user->phone,
            'status'         => $user->status,
            'email_verified' => (bool) $user->email_verified,
        ];

        $data = [
            'first_name'     => $validated['first_name'],
            'last_name'      => $validated['last_name'],
            'email'          => $validated['email'],
            'phone'          => $validated['phone'] ?? null,
            'status'         => $validated['status'],
            'email_verified' => $request->boolean('email_verified'),
        ];

        if (!empty($validated['password'])) {
            $data['password_hash'] = Hash::make($validated['password']);
        }

        if ($request->boolean('email_verified') && !$user->email_verified) {
            $data['email_verified_at'] = now();
        }

        $user->update($data);

        // Build new snapshot for diff (exclude password_hash — redaction handles it)
        $newSnapshot = [
            'first_name'     => $data['first_name'],
            'last_name'      => $data['last_name'],
            'email'          => $data['email'],
            'phone'          => $data['phone'],
            'status'         => $data['status'],
            'email_verified' => $data['email_verified'],
        ];
        $passwordChanged = !empty($validated['password']);

        ActivityLogger::admin(
            action: 'user.updated',
            target: $user,
            description: "แก้ไขข้อมูลผู้ใช้ {$user->email}" . ($passwordChanged ? ' (รวมรหัสผ่าน)' : ''),
            oldValues: $oldSnapshot,
            newValues: array_merge($newSnapshot, ['password_changed' => $passwordChanged]),
        );

        return redirect()->route('admin.users.show', $user)
            ->with('success', "อัปเดตข้อมูลผู้ใช้ \"{$user->full_name}\" สำเร็จ");
    }

    // ═══════════════════════════════════════
    //  Destroy
    // ═══════════════════════════════════════
    public function destroy(User $user)
    {
        $name     = $user->full_name;
        $userId   = $user->id;
        $email    = $user->email;
        $snapshot = ['id' => $userId, 'email' => $email, 'full_name' => $name];

        // Check if user has orders
        if ($user->orders()->exists()) {
            // Soft-block instead of delete
            $oldStatus = $user->status;
            $user->update(['status' => 'suspended']);

            ActivityLogger::admin(
                action: 'user.soft_suspended',
                target: $user,
                description: "ระงับบัญชี {$email} (มีประวัติสั่งซื้อ — ลบไม่ได้)",
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => 'suspended'],
            );

            return back()->with('warning', "ผู้ใช้ \"{$name}\" มีคำสั่งซื้ออยู่ จึงระงับบัญชีแทนการลบ");
        }

        $user->delete();

        ActivityLogger::admin(
            action: 'user.deleted',
            target: ['User', $userId],
            description: "ลบบัญชีผู้ใช้ {$email}",
            oldValues: $snapshot,
            newValues: null,
        );

        return redirect()->route('admin.users.index')
            ->with('success', "ลบผู้ใช้ \"{$name}\" สำเร็จ");
    }

    // ═══════════════════════════════════════
    //  Reset Password
    //  Allowed ONLY for local/email signups.
    //  OAuth accounts (google/line/facebook) cannot have their password reset —
    //  those users must login via their social provider.
    // ═══════════════════════════════════════
    public function resetPassword(Request $request, User $user)
    {
        // Guard: only local accounts (null/empty/'local' means email+password signup)
        $provider = strtolower((string) ($user->auth_provider ?? 'local'));
        $isLocal  = in_array($provider, ['', 'local', 'email'], true);

        if (!$isLocal) {
            return back()->with('error',
                "ไม่สามารถรีเซ็ตรหัสผ่านได้ — บัญชีนี้สมัครผ่าน \"{$provider}\" " .
                "ให้ผู้ใช้เข้าสู่ระบบผ่าน social login แทน"
            );
        }

        // Prevent accidental reset on admin/staff if the user is also linked to an admin
        if (!$user->password_hash) {
            return back()->with('warning', 'บัญชีนี้ยังไม่มีรหัสผ่าน — ใช้แก้ไขเพื่อตั้งรหัสใหม่แทน');
        }

        // Generate secure random password (10 chars, mixed case + digit)
        $newPass = $this->generateSecurePassword(10);

        $user->update([
            'password_hash' => Hash::make($newPass),
        ]);

        ActivityLogger::admin(
            action: 'user.password_reset',
            target: $user,
            description: "รีเซ็ตรหัสผ่านผู้ใช้ {$user->email}",
            oldValues: null,
            newValues: ['password_reset_at' => now()->toIso8601String()],
        );

        // Optional: log the action
        try {
            DB::table('admin_notifications')->insert([
                'type'       => 'security',
                'title'      => 'รีเซ็ตรหัสผ่านผู้ใช้',
                'message'    => "แอดมินรีเซ็ตรหัสผ่านของ {$user->email}",
                'ref_id'     => (string) $user->id,
                'is_read'    => true,
                'read_at'    => now(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {}

        return back()
            ->with('success', "รีเซ็ตรหัสผ่านของ {$user->email} สำเร็จ")
            ->with('new_password', $newPass);
    }

    /**
     * Generate a secure password: mix of upper + lower + digit, guaranteed length.
     */
    protected function generateSecurePassword(int $len = 10): string
    {
        $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lower = 'abcdefghjkmnpqrstuvwxyz';
        $digit = '23456789';
        $all   = $upper . $lower . $digit;

        $chars = [
            $upper[random_int(0, strlen($upper) - 1)],
            $lower[random_int(0, strlen($lower) - 1)],
            $digit[random_int(0, strlen($digit) - 1)],
        ];
        for ($i = count($chars); $i < $len; $i++) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }
        shuffle($chars);
        return implode('', $chars);
    }

    // ═══════════════════════════════════════
    //  Export CSV
    // ═══════════════════════════════════════
    public function export(Request $request)
    {
        $users = User::withCount('orders')
            ->orderByDesc('created_at')
            ->get();

        $csv = "ID,First Name,Last Name,Email,Phone,Status,Email Verified,Orders,Login Count,Last Login,Registered\n";
        foreach ($users as $u) {
            $csv .= implode(',', [
                $u->id,
                '"' . ($u->first_name ?? '') . '"',
                '"' . ($u->last_name ?? '') . '"',
                $u->email,
                '"' . ($u->phone ?? '') . '"',
                $u->status ?? 'active',
                $u->email_verified ? 'Yes' : 'No',
                $u->orders_count,
                $u->login_count ?? 0,
                $u->last_login_at?->format('Y-m-d H:i') ?? '',
                $u->created_at?->format('Y-m-d H:i') ?? '',
            ]) . "\n";
        }

        return response($csv)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="users-' . date('Y-m-d') . '.csv"');
    }
}
