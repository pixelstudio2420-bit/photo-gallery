<?php

namespace Tests\Unit;

use App\Services\CartService;
use Tests\TestCase;

class CartServiceTest extends TestCase
{
    private CartService $cart;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cart = new CartService();
        $this->cart->clear();
    }

    // ─── Empty Cart ───

    public function test_get_items_returns_empty_initially(): void
    {
        $this->assertEmpty($this->cart->getItems());
        $this->assertEquals(0, $this->cart->count());
        $this->assertEquals(0.0, $this->cart->getTotal());
    }

    // ─── Add Item ───

    public function test_add_item_stores_in_session(): void
    {
        $this->cart->add([
            'photo_id'  => 'photo-001',
            'event_id'  => 1,
            'name'      => 'Beach Photo',
            'thumbnail' => 'https://example.com/thumb.jpg',
            'price'     => 50.00,
        ]);

        $this->assertEquals(1, $this->cart->count());
        $items = $this->cart->getItems();
        $this->assertArrayHasKey('photo-001', $items);
        $this->assertEquals('Beach Photo', $items['photo-001']['name']);
        $this->assertEquals(50.00, $items['photo-001']['price']);
    }

    // ─── Add Duplicate Increments Quantity ───

    public function test_add_duplicate_increments_quantity(): void
    {
        $item = [
            'photo_id' => 'photo-002',
            'price'    => 30.00,
        ];

        $this->cart->add($item);
        $this->cart->add($item);

        $items = $this->cart->getItems();
        $this->assertEquals(2, $items['photo-002']['quantity']);
    }

    // ─── Remove Item ───

    public function test_remove_item(): void
    {
        $this->cart->add(['photo_id' => 'photo-del', 'price' => 25.00]);
        $this->assertEquals(1, $this->cart->count());

        $this->cart->remove('photo-del');
        $this->assertEquals(0, $this->cart->count());
    }

    // ─── Update Quantity to Zero Removes ───

    public function test_update_quantity_to_zero_removes(): void
    {
        $this->cart->add(['photo_id' => 'photo-zero', 'price' => 10.00]);
        $this->cart->updateQuantity('photo-zero', 0);

        $this->assertEquals(0, $this->cart->count());
    }

    // ─── Get Total Calculates Correctly ───

    public function test_get_total_calculates_correctly(): void
    {
        $this->cart->add(['photo_id' => 'a', 'price' => 100.00, 'quantity' => 2]);
        $this->cart->add(['photo_id' => 'b', 'price' => 50.00, 'quantity' => 1]);

        // a: 100 * 2 = 200, b: 50 * 1 = 50
        $this->assertEquals(250.00, $this->cart->getTotal());
    }

    // ─── Clear Cart ───

    public function test_clear_empties_cart(): void
    {
        $this->cart->add(['photo_id' => 'x', 'price' => 10.00]);
        $this->cart->add(['photo_id' => 'y', 'price' => 20.00]);
        $this->assertEquals(2, $this->cart->count());

        $this->cart->clear();
        $this->assertEquals(0, $this->cart->count());
    }
}
