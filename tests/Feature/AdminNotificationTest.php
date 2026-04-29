<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function createAndLoginAdmin(): Admin
    {
        $admin = Admin::create([
            'email'         => 'admin-' . uniqid() . '@test.com',
            'password_hash' => Hash::make('password123'),
            'first_name'    => 'Test',
            'last_name'     => 'Admin',
            'role'          => 'superadmin',
            'is_active'     => true,
        ]);

        Auth::guard('admin')->login($admin);

        return $admin;
    }

    // ─── Admin Gets Notifications ───

    public function test_admin_gets_notifications(): void
    {
        $this->createAndLoginAdmin();

        AdminNotification::notify('order', 'New Order', 'Order #123 received', '/admin/orders/123', '123');
        AdminNotification::notify('user', 'New User', 'John registered', '/admin/users/1', '1');

        $response = $this->getJson(route('admin.api.admin.notifications'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'unread_count',
            'notifications',
        ]);
        $response->assertJsonPath('unread_count', 2);
    }

    // ─── Mark Notification Read ───

    public function test_mark_notification_read(): void
    {
        $this->createAndLoginAdmin();

        AdminNotification::notify('order', 'Test', 'Test message', null, null);
        $notif = AdminNotification::latest('id')->first();

        $response = $this->postJson(
            route('admin.api.admin.notifications.read', $notif->id)
        );

        $response->assertStatus(200);

        $notif->refresh();
        $this->assertTrue((bool) $notif->is_read);
        $this->assertNotNull($notif->read_at);
    }

    // ─── Mark All Read ───

    public function test_mark_all_read(): void
    {
        $this->createAndLoginAdmin();

        AdminNotification::notify('order', 'Order 1', 'msg', null, null);
        AdminNotification::notify('user', 'User 1', 'msg', null, null);
        AdminNotification::notify('slip', 'Slip 1', 'msg', null, null);

        $response = $this->postJson(
            route('admin.api.admin.notifications.read-all')
        );

        $response->assertStatus(200);
        $this->assertEquals(0, AdminNotification::where('is_read', false)->count());
    }

    // ─── Stats Endpoint ───

    public function test_notification_stats_endpoint(): void
    {
        $this->createAndLoginAdmin();

        $response = $this->getJson(
            route('admin.api.admin.notifications.stats')
        );

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'stats',
        ]);
    }

    // ─── Model Static Helpers Create Notifications ───

    public function test_new_order_notification_creates_record(): void
    {
        $order = (object) [
            'id'           => 1,
            'order_number' => 'ORD-001',
            'total'        => 500,
        ];

        AdminNotification::newOrder($order);

        $this->assertDatabaseHas('admin_notifications', [
            'type' => 'order',
        ]);
    }
}
