<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Media\Exceptions\InvalidMediaFileException;
use App\Services\Media\R2MediaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use App\Models\Order;
use App\Models\DownloadToken;
use App\Models\Review;
use App\Models\Wishlist;

class ProfileController extends Controller
{
    /**
     * Main profile/dashboard page.
     */
    public function show()
    {
        $user = Auth::user();
        $user->load('socialLogins');

        $orderCount   = Order::where('user_id', $user->id)->count();
        $totalSpent   = Order::where('user_id', $user->id)->where('status', 'paid')->sum('total');
        $downloadCount = DownloadToken::where('user_id', $user->id)->count();
        $reviewCount  = Review::where('user_id', $user->id)->count();
        $wishlistCount = Wishlist::where('user_id', $user->id)->count();

        $recentOrders = Order::where('user_id', $user->id)
            ->with('event')
            ->withCount('items')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $recentDownloads = DownloadToken::where('user_id', $user->id)
            ->with('order.event')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return view('public.profile.dashboard', compact(
            'user',
            'orderCount',
            'totalSpent',
            'downloadCount',
            'reviewCount',
            'wishlistCount',
            'recentOrders',
            'recentDownloads'
        ));
    }

    /**
     * Show the profile edit form (existing index/edit).
     */
    public function index()
    {
        return $this->show();
    }

    public function edit()
    {
        $user = Auth::user();
        $user->load('socialLogins');
        return view('public.profile.edit', ['user' => $user]);
    }

    /**
     * Full order history with status filter.
     */
    public function orders(Request $request)
    {
        $user   = Auth::user();
        $status = $request->get('status');

        $query = Order::where('user_id', $user->id)
            ->with('event')
            ->withCount('items')
            ->orderByDesc('created_at');

        if ($status) {
            $query->where('status', $status);
        }

        $orders = $query->paginate(15)->withQueryString();

        return view('public.profile.orders', compact('orders', 'status'));
    }

    /**
     * "My Referrals" page — shows the user's personal referral code, share
     * URL, redemption stats, and a list of recent invites.
     *
     * If the marketing referral feature is disabled in admin settings, this
     * page renders an empty state instead of erroring out.
     */
    public function referrals(Request $request)
    {
        $user = Auth::user();
        $svc  = app(\App\Services\Marketing\ReferralService::class);

        $enabled = $svc->enabled();
        $code = $enabled ? $svc->getOrCreateForUser($user) : null;
        $stats = $enabled ? $svc->statsForUser($user) : [
            'code' => null, 'uses' => 0, 'rewarded' => 0, 'total_reward' => 0,
        ];

        // Short URL `/r/{code}` is the preferred share format (cleaner +
        // friendlier in tweets/SMS). The legacy `/?ref=` form still works
        // because CaptureReferral middleware listens for it on every GET.
        $shareUrl = $code
            ? url('/r/' . urlencode($code->code))
            : null;

        $redemptions = collect();
        if ($code) {
            $redemptions = \App\Models\Marketing\ReferralRedemption::where('referral_code_id', $code->id)
                ->with(['redeemer:id,first_name,last_name', 'order:id,order_number,total,status,created_at'])
                ->orderByDesc('created_at')
                ->limit(20)
                ->get();
        }

        return view('public.profile.referrals', compact(
            'user', 'enabled', 'code', 'stats', 'shareUrl', 'redemptions'
        ));
    }

    /**
     * Download history.
     */
    public function downloads(Request $request)
    {
        $user = Auth::user();

        $downloads = DownloadToken::where('user_id', $user->id)
            ->with('order.event')
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('public.profile.downloads', compact('downloads'));
    }

    /**
     * User's reviews.
     */
    public function reviews(Request $request)
    {
        $user = Auth::user();

        $reviews = Review::where('user_id', $user->id)
            ->with('event')
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('public.profile.reviews', compact('reviews'));
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name'  => 'nullable|string|max:100',
            'email'      => 'required|email|unique:auth_users,email,' . $user->id,
            'phone'      => 'nullable|string|max:20',
        ]);

        $user->update($validated);

        return back()->with('success', 'อัพเดทโปรไฟล์สำเร็จ');
    }

    public function updatePassword(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'current_password' => 'required',
            'password'         => ['required', 'confirmed', Password::min(8)],
        ]);

        if (!Hash::check($request->current_password, $user->password_hash)) {
            return back()->withErrors(['current_password' => 'รหัสผ่านปัจจุบันไม่ถูกต้อง']);
        }

        $user->update([
            'password_hash' => Hash::make($request->password),
        ]);

        return back()->with('success', 'เปลี่ยนรหัสผ่านสำเร็จ');
    }

    /**
     * Avatar upload / removal for the currently-authenticated customer.
     *
     * Mutually-exclusive paths driven by a single form:
     *   • a file in `avatar`        → replace the current avatar
     *   • `remove_avatar=1` (no file) → clear the avatar back to initials
     *
     * Unlike the photographer avatar flow (which re-encodes through
     * ImageProcessorService), customer avatars go straight to the current
     * upload driver (R2 / public) because we don't need the 400x400 webp
     * re-encode path for casual profile pictures — the frontend uses
     * `<x-avatar>` which already sizes them via CSS.
     *
     * Social-login avatars live as full https:// URLs (Google / LINE /
     * Facebook); those are left untouched on the delete path so we don't
     * attempt to `Storage::delete()` an external provider URL.
     */
    public function updateAvatar(Request $request, R2MediaService $media)
    {
        $user = Auth::user();

        // Note: image-rule limits + MIME enforcement are duplicated by the
        // server-side R2MediaService category config. The Laravel validator
        // gives the user a friendly client-side error before we hit R2.
        $request->validate([
            'avatar'        => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
        ]);

        // Only attempt to delete when the previous avatar lives on R2 — a
        // bare https:// URL means a social-login provider owns it.
        $safeDeleteOld = function () use ($user, $media) {
            $old = $user->getRawOriginal('avatar');
            if ($old && !str_starts_with($old, 'http')) {
                try { $media->delete($old); } catch (\Throwable) {}
            }
        };

        if ($request->hasFile('avatar')) {
            $safeDeleteOld();

            try {
                $result = $media->uploadAvatar((int) $user->id, $request->file('avatar'));
            } catch (InvalidMediaFileException $e) {
                return back()->withErrors(['avatar' => $e->getMessage()]);
            } catch (\Throwable $e) {
                Log::error('Avatar upload failed', [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]);
                return back()->withErrors(['avatar' => 'อัปโหลดรูปโปรไฟล์ไม่สำเร็จ กรุณาลองใหม่']);
            }

            $user->update(['avatar' => $result->key]);
            return back()->with('success', 'อัปเดตรูปโปรไฟล์สำเร็จ');
        }

        if ($request->boolean('remove_avatar')) {
            $safeDeleteOld();
            $user->update(['avatar' => null]);
            return back()->with('success', 'ลบรูปโปรไฟล์แล้ว');
        }

        return back()->with('warning', 'กรุณาเลือกไฟล์รูปภาพ หรือเลือก "ลบรูปโปรไฟล์"');
    }

    /**
     * Notification Preferences page.
     */
    public function notificationPreferences()
    {
        $preferences = \App\Models\NotificationPreference::forUser(Auth::id());

        $seo = app(\App\Services\SeoService::class);
        $seo->title('ตั้งค่าการแจ้งเตือน')
            ->robots('noindex, nofollow')
            ->setBreadcrumbs([
                ['name' => 'หน้าแรก', 'url' => url('/')],
                ['name' => 'โปรไฟล์', 'url' => route('profile')],
                ['name' => 'ตั้งค่าการแจ้งเตือน'],
            ]);

        return view('public.profile.notification-preferences', compact('preferences'));
    }

    /**
     * Save notification preferences (bulk update).
     */
    public function updateNotificationPreferences(Request $request)
    {
        $userId = Auth::id();
        $types = array_keys(\App\Models\NotificationPreference::TYPES);

        foreach ($types as $type) {
            $data = $request->input("prefs.{$type}", []);
            \App\Models\NotificationPreference::updateOrCreate(
                ['user_id' => $userId, 'type' => $type],
                [
                    'in_app_enabled' => !empty($data['in_app']),
                    'email_enabled'  => !empty($data['email']),
                    'sms_enabled'    => !empty($data['sms']),
                    'push_enabled'   => !empty($data['push']),
                ]
            );
        }

        return back()->with('success', 'บันทึกการตั้งค่าการแจ้งเตือนเรียบร้อย');
    }
}
