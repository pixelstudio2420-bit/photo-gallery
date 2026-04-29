<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(): User
    {
        return User::create([
            'first_name'    => 'Cart',
            'last_name'     => 'Tester',
            'email'         => 'cart-' . uniqid() . '@example.com',
            'password_hash' => Hash::make('password'),
            'auth_provider' => 'local',
        ]);
    }

    private function createEvent(): Event
    {
        return Event::create([
            'name'            => 'Cart Test Event',
            'slug'            => 'cart-event-' . uniqid(),
            'status'          => 'active',
            'visibility'      => 'public',
            'price_per_photo' => 15.00,
            'is_free'         => false,
            'view_count'      => 0,
        ]);
    }

    // ─── Guest Cannot Add to Cart ───

    public function test_guest_cannot_add_to_cart(): void
    {
        $response = $this->post(route('cart.add'), [
            'photo_id'      => 'fake-photo-id',
            'event_id'      => 1,
            'thumbnail_url' => 'https://example.com/thumb.jpg',
        ]);

        // Should redirect to login for unauthenticated users
        $response->assertRedirect(route('login'));
    }

    // ─── Authenticated User Can Add to Cart ───

    public function test_authenticated_user_can_add_to_cart(): void
    {
        $user  = $this->createUser();
        $event = $this->createEvent();

        $response = $this->actingAs($user)->post(route('cart.add'), [
            'photo_id'      => 'test-photo-123',
            'event_id'      => $event->id,
            'thumbnail_url' => 'https://drive.google.com/thumbnail?id=abc&sz=w400',
        ]);

        // Should redirect back or return success (not a login redirect)
        $this->assertNotEquals(route('login'), $response->headers->get('Location'));
    }

    // ─── Cart Page Loads ───

    public function test_cart_page_loads_for_authenticated_user(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->get(route('cart.index'));

        $response->assertStatus(200);
    }
}
