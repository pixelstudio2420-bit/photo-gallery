<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Wishlist;

class WishlistApiController extends Controller
{
    public function index(Request $request)
    {
        $userId = Auth::id();
        $action = $request->query('action', 'list');

        if ($action === 'count') {
            $count = Wishlist::where('user_id', $userId)->count();
            return response()->json(['success' => true, 'count' => $count]);
        }

        if ($action === 'check') {
            $eventId = $request->query('event_id');
            $exists = Wishlist::where('user_id', $userId)->where('event_id', $eventId)->exists();
            return response()->json(['success' => true, 'in_wishlist' => $exists]);
        }

        // Default: list all
        $items = Wishlist::where('user_id', $userId)->get();
        return response()->json(['success' => true, 'data' => $items]);
    }

    public function toggle(Request $request)
    {
        $userId = Auth::id();
        $eventId = $request->input('event_id');

        if (!$eventId) {
            return response()->json(['success' => false, 'message' => 'event_id required']);
        }

        $existing = Wishlist::where('user_id', $userId)->where('event_id', $eventId)->first();

        if ($existing) {
            $existing->delete();
            $inWishlist = false;
        } else {
            Wishlist::create([
                'user_id' => $userId,
                'event_id' => $eventId,
            ]);
            $inWishlist = true;
        }

        $count = Wishlist::where('user_id', $userId)->count();

        return response()->json([
            'success' => true,
            'in_wishlist' => $inWishlist,
            'count' => $count,
        ]);
    }
}
