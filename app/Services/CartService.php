<?php

namespace App\Services;

use Illuminate\Support\Facades\Session;

class CartService
{
    private const SESSION_KEY = 'cart';

    /**
     * Get all cart items
     */
    public function getItems(): array
    {
        return Session::get(self::SESSION_KEY, []);
    }

    /**
     * Add item to cart
     */
    public function add(array $item): void
    {
        $cart = $this->getItems();
        $key = $item['photo_id'] ?? $item['product_id'] ?? uniqid();

        if (isset($cart[$key])) {
            $cart[$key]['quantity'] = ($cart[$key]['quantity'] ?? 1) + ($item['quantity'] ?? 1);
        } else {
            $cart[$key] = [
                'photo_id'   => $item['photo_id'] ?? null,
                'product_id' => $item['product_id'] ?? null,
                'event_id'   => $item['event_id'] ?? null,
                'file_id'    => $item['file_id'] ?? null,
                'name'       => $item['name'] ?? 'Photo',
                'thumbnail'  => $item['thumbnail'] ?? '',
                'price'      => (float)($item['price'] ?? 0),
                'price_type' => $item['price_type'] ?? 'single',
                'package_id'       => $item['package_id'] ?? null,
                'bundle_photo_ids' => $item['bundle_photo_ids'] ?? [],
                'quantity'   => (int)($item['quantity'] ?? 1),
            ];
        }

        Session::put(self::SESSION_KEY, $cart);

        // Track abandoned cart activity (non-blocking)
        try {
            app(\App\Services\AbandonedCartService::class)->trackActivity(
                auth()->id(),
                auth()->user()?->email,
                Session::getId(),
                $cart
            );
        } catch (\Throwable $e) {
            \Log::warning('Cart tracking failed: ' . $e->getMessage());
        }
    }

    /**
     * Remove item from cart
     */
    public function remove(string $key): void
    {
        $cart = $this->getItems();
        unset($cart[$key]);
        Session::put(self::SESSION_KEY, $cart);
    }

    /**
     * Update item quantity
     */
    public function updateQuantity(string $key, int $quantity): void
    {
        $cart = $this->getItems();

        if (isset($cart[$key])) {
            if ($quantity <= 0) {
                unset($cart[$key]);
            } else {
                $cart[$key]['quantity'] = $quantity;
            }
        }

        Session::put(self::SESSION_KEY, $cart);
    }

    /**
     * Get cart total
     */
    public function getTotal(): float
    {
        $total = 0;
        foreach ($this->getItems() as $item) {
            $total += ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
        }
        return $total;
    }

    /**
     * Get cart item count
     */
    public function count(): int
    {
        return count($this->getItems());
    }

    /**
     * Clear cart
     */
    public function clear(): void
    {
        Session::forget(self::SESSION_KEY);
    }
}
