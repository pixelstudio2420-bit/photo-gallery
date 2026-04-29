<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(): User
    {
        return User::create([
            'first_name'    => 'Order',
            'last_name'     => 'Tester',
            'email'         => 'order-' . uniqid() . '@example.com',
            'password_hash' => Hash::make('password'),
            'auth_provider' => 'local',
        ]);
    }

    // ─── Guest Cannot View Orders ───

    public function test_guest_cannot_view_orders(): void
    {
        $response = $this->get(route('orders.index'));

        $response->assertRedirect(route('login'));
    }

    // ─── Authenticated User Can View Orders ───

    public function test_authenticated_user_can_view_orders(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->get(route('orders.index'));

        $response->assertStatus(200);
    }

    // ─── Order Show Page ───

    public function test_order_show_page_loads(): void
    {
        $user  = $this->createUser();

        $event = Event::create([
            'name'            => 'Order Test Event',
            'slug'            => 'order-event-' . uniqid(),
            'status'          => 'active',
            'visibility'      => 'public',
            'price_per_photo' => 10.00,
            'is_free'         => false,
            'view_count'      => 0,
        ]);

        $order = Order::create([
            'user_id'      => $user->id,
            'event_id'     => $event->id,
            'order_number' => 'ORD-' . uniqid(),
            'total'        => 30.00,
            'status'       => 'pending_payment',
        ]);

        $response = $this->actingAs($user)->get(route('orders.show', $order->id));

        $response->assertStatus(200);
    }
}
