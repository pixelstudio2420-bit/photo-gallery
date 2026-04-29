<?php
namespace App\Http\Controllers\Public;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Wishlist;

class WishlistController extends Controller
{
    public function index() {
        $wishlists = Wishlist::where('user_id', Auth::id())->with(['event','product'])->get();
        return view('public.wishlist.index', compact('wishlists'));
    }

    public function toggle(Request $request)
    {
        $request->validate([
            'event_id' => 'required|integer|exists:events,id',
            'photo_id' => 'nullable|integer',
        ]);

        $userId  = Auth::id();
        $eventId = $request->input('event_id');
        $photoId = $request->input('photo_id');

        $query = Wishlist::where('user_id', $userId)
                         ->where('event_id', $eventId);

        if ($photoId !== null) {
            $query->where('photo_id', $photoId);
        } else {
            $query->whereNull('photo_id');
        }

        $existing = $query->first();

        if ($existing) {
            $existing->delete();
            $inWishlist = false;
        } else {
            Wishlist::create([
                'user_id'  => $userId,
                'event_id' => $eventId,
                'photo_id' => $photoId,
            ]);
            $inWishlist = true;
        }

        $count = Wishlist::where('user_id', $userId)->count();

        if ($request->expectsJson()) {
            return response()->json([
                'in_wishlist' => $inWishlist,
                'count'       => $count,
            ]);
        }

        $message = $inWishlist ? 'เพิ่มในรายการโปรดแล้ว' : 'ลบออกจากรายการโปรดแล้ว';
        return redirect()->back()->with('success', $message);
    }
}
