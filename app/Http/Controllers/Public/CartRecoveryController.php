<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\AbandonedCartService;
use App\Services\CartService;
use Illuminate\Http\Request;

class CartRecoveryController extends Controller
{
    public function __construct(
        private AbandonedCartService $abandonedCarts,
        private CartService $cart
    ) {
    }

    /**
     * Restore cart from recovery token.
     * Accessed via email link: /cart/recover/{token}
     */
    public function restore(Request $request, string $token)
    {
        $abandoned = $this->abandonedCarts->findByToken($token);

        if (!$abandoned) {
            return redirect()->route('cart.index')
                ->with('error', 'ลิงก์ไม่ถูกต้องหรือหมดอายุแล้ว');
        }

        // Restore items into current cart
        $items = $abandoned->items ?? [];
        $restored = 0;
        foreach ($items as $item) {
            try {
                $this->cart->add($item);
                $restored++;
            } catch (\Throwable $e) {
                \Log::warning('Cart restore item failed: ' . $e->getMessage());
            }
        }

        return redirect()->route('cart.index')
            ->with('success', "กู้คืนตะกร้าเรียบร้อย! เพิ่ม {$restored} รายการกลับมา");
    }
}
