<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\WishlistShare;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class WishlistShareController extends Controller
{
    /**
     * Show the authenticated user's list of shares.
     */
    public function index()
    {
        $userId = Auth::id();

        $shares = WishlistShare::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get();

        return view('public.wishlist.shares', compact('shares'));
    }

    /**
     * Create a new shareable wishlist.
     */
    public function create(Request $request)
    {
        $request->validate([
            'title'       => 'nullable|string|max:255',
            'description' => 'nullable|string|max:2000',
            'is_public'   => 'nullable|boolean',
            'expires_in'  => 'nullable|integer|min:1|max:365',
        ]);

        $userId = Auth::id();

        // Must have at least one wishlist item to share
        $hasItems = Wishlist::where('user_id', $userId)->exists();
        if (!$hasItems) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'คุณยังไม่มีรายการโปรด ไม่สามารถสร้างลิงก์แชร์ได้',
                ], 422);
            }
            return redirect()->back()->with('error', 'คุณยังไม่มีรายการโปรด ไม่สามารถสร้างลิงก์แชร์ได้');
        }

        $expiresAt = $request->filled('expires_in')
            ? now()->addDays((int) $request->input('expires_in'))
            : null;

        try {
            $share = WishlistShare::createForUser($userId, [
                'title'       => $request->input('title'),
                'description' => $request->input('description'),
                'is_public'   => $request->boolean('is_public', true),
                'expires_at'  => $expiresAt,
            ]);
        } catch (\Throwable $e) {
            Log::warning('WishlistShare create failed: ' . $e->getMessage());
            if ($request->expectsJson()) {
                return response()->json(['error' => 'ไม่สามารถสร้างลิงก์แชร์ได้'], 500);
            }
            return redirect()->back()->with('error', 'ไม่สามารถสร้างลิงก์แชร์ได้');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'share'   => [
                    'id'    => $share->id,
                    'token' => $share->token,
                    'url'   => $share->getUrl(),
                    'title' => $share->title,
                ],
            ]);
        }

        return redirect()->route('wishlist.shares.index')
            ->with('success', 'สร้างลิงก์แชร์เรียบร้อย: ' . $share->getUrl());
    }

    /**
     * Publicly view a shared wishlist by token.
     * No authentication required.
     */
    public function view(string $token)
    {
        $share = WishlistShare::where('token', $token)
            ->active()
            ->firstOrFail();

        try {
            $share->incrementViews();
        } catch (\Throwable $e) {
            Log::warning('WishlistShare view-count increment failed: ' . $e->getMessage());
        }

        $items = $share->getWishlistItems();
        $owner = $share->user;

        return view('public.wishlist.shared', compact('share', 'items', 'owner'));
    }

    /**
     * Revoke/delete a share owned by the current user.
     */
    public function destroy(WishlistShare $share)
    {
        if ($share->user_id !== Auth::id()) {
            abort(403);
        }

        try {
            $share->delete();
        } catch (\Throwable $e) {
            Log::warning('WishlistShare delete failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'ไม่สามารถลบลิงก์แชร์ได้');
        }

        if (request()->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('wishlist.shares.index')
            ->with('success', 'ลบลิงก์แชร์เรียบร้อย');
    }
}
