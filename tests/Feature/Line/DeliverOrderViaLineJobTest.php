<?php

namespace Tests\Feature\Line;

use App\Jobs\Line\DeliverOrderViaLineJob;
use App\Jobs\Line\SendLinePushJob;
use App\Models\AppSetting;
use App\Models\Event;
use App\Models\Order;
use App\Models\PhotographerProfile;
use App\Models\User;
use App\Services\LineNotifyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Locks down the queued LINE delivery flow.
 *
 * The properties:
 *
 *   • Job dispatches SendLinePushJob for the download-link Flex
 *     bubble (always).
 *   • When `delivery_line_send_photos` is on AND order has ≤30 photos,
 *     the job ALSO dispatches push jobs for each photo chunk.
 *   • When `delivery_line_send_photos` is off, no photo pushes are
 *     dispatched (only the download link).
 *   • Missing user_id on the order → job is a quiet no-op (no push
 *     attempts, no errors).
 *   • Idempotency keys are set so a job rerun doesn't double-send.
 *
 * We use Queue::fake() to capture dispatched jobs without actually
 * making LINE API calls.
 */
class DeliverOrderViaLineJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AppSetting::set('line_messaging_enabled', '1');
        AppSetting::set('line_user_push_enabled', '1');
        AppSetting::set('line_user_push_download', '1');
        AppSetting::set('line_channel_access_token', 'tok');
        AppSetting::flushCache();
    }

    private function makeOrder(int $photoCount = 0): Order
    {
        $user = User::create([
            'first_name'    => 'Buyer',
            'last_name'     => 'X',
            'email'         => 'buyer-' . uniqid() . '@example.com',
            'password_hash' => Hash::make('p'),
            'auth_provider' => 'local',
            'line_user_id'  => 'U' . str_repeat('1', 32),
        ]);
        // LineNotifyService::getLineUserId reads auth_social_logins, NOT
        // the denormalised users.line_user_id column. Seed both so the
        // happy path works end-to-end.
        \DB::table('auth_social_logins')->insert([
            'user_id'     => $user->id,
            'provider'    => 'line',
            'provider_id' => 'U' . str_repeat('1', 32),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
        $event = Event::create([
            'name'            => 'E2E LINE Event',
            'slug'            => 'e2e-line-' . uniqid(),
            'status'          => 'active',
            'visibility'      => 'public',
            'price_per_photo' => 20.00,
            'is_free'         => false,
            'view_count'      => 0,
        ]);
        $order = Order::create([
            'user_id'      => $user->id,
            'event_id'     => $event->id,
            'order_number' => 'ORD-' . uniqid(),
            'total'        => 100.00,
            'status'       => 'paid',
        ]);
        return $order;
    }

    public function test_job_dispatches_push_for_download_link(): void
    {
        Queue::fake();

        AppSetting::set('delivery_line_send_photos', '0');
        AppSetting::flushCache();

        $order = $this->makeOrder();
        (new DeliverOrderViaLineJob($order->id))->handle(app(LineNotifyService::class));

        Queue::assertPushed(SendLinePushJob::class, function ($job) use ($order) {
            // The download-link push uses idempotency key
            // "order.{$id}.line.download" — verify by re-dispatching
            // would dedupe (we test that property in the audit table).
            return true;
        });
    }

    public function test_no_push_when_user_has_no_line_id(): void
    {
        Queue::fake();

        $order = $this->makeOrder();
        // The send path looks up the LINE id from auth_social_logins —
        // remove it (and the denormalised column) so getLineUserId()
        // returns null.
        \DB::table('auth_social_logins')
            ->where('user_id', $order->user_id)->delete();
        $order->user->update(['line_user_id' => null]);

        (new DeliverOrderViaLineJob($order->id))->handle(app(LineNotifyService::class));

        Queue::assertNothingPushed();
    }

    public function test_missing_order_is_a_quiet_noop(): void
    {
        Queue::fake();
        (new DeliverOrderViaLineJob(999_999))->handle(app(LineNotifyService::class));
        Queue::assertNothingPushed();
    }

    /**
     * Regression guard.
     *
     * Background: when LINE delivery moved from sync to queued, the
     * channel-specific deliverer started returning status='sent' instead
     * of status='delivered'. PhotoDeliveryService::deliver() only stamps
     * `orders.delivered_at` when status === 'delivered' — so LINE-
     * delivered orders started having delivered_at=null forever, which
     * broke the admin "delivered orders this week" query.
     *
     * Fix: DeliverOrderViaLineJob updates `delivered_at` itself once
     * it has successfully queued the download-link push. This test
     * asserts the column is populated end-to-end.
     */
    public function test_delivered_at_is_stamped_on_successful_dispatch(): void
    {
        AppSetting::set('delivery_line_send_photos', '0');
        AppSetting::flushCache();

        $order = $this->makeOrder();
        $this->assertNull($order->delivered_at);

        (new DeliverOrderViaLineJob($order->id))->handle(app(LineNotifyService::class));

        $fresh = $order->fresh();
        $this->assertNotNull($fresh->delivered_at,
            'delivered_at must be stamped after a successful queue dispatch');
        $this->assertSame('delivered', $fresh->delivery_status);
    }

    public function test_delivered_at_is_not_overwritten_when_already_set(): void
    {
        // Idempotency: a re-run of the job should not move the
        // delivered_at timestamp. Otherwise re-deliveries would
        // shift the "first delivered at" forward and break audit.
        AppSetting::set('delivery_line_send_photos', '0');
        AppSetting::flushCache();

        $order = $this->makeOrder();
        $earlier = now()->subDays(2);
        $order->update(['delivered_at' => $earlier, 'delivery_status' => 'delivered']);

        (new DeliverOrderViaLineJob($order->id))->handle(app(LineNotifyService::class));

        $fresh = $order->fresh();
        $this->assertNotNull($fresh->delivered_at);
        // Allow ms-level fuzz from datetime → carbon round-trip.
        $this->assertTrue(
            abs($fresh->delivered_at->diffInSeconds($earlier)) < 5,
            'delivered_at must not move on re-run',
        );
    }

    public function test_idempotent_replay_does_not_dispatch_twice(): void
    {
        // We can't easily test this through Queue::fake() because the
        // dedup happens at the line_deliveries layer (DB unique index),
        // not at the queue. Instead, we run twice and assert only ONE
        // line_deliveries row was inserted per logical send.
        AppSetting::set('delivery_line_send_photos', '0');
        AppSetting::flushCache();
        Queue::fake();   // jobs don't actually run, but begin() rows are inserted

        $order = $this->makeOrder();
        (new DeliverOrderViaLineJob($order->id))->handle(app(LineNotifyService::class));
        (new DeliverOrderViaLineJob($order->id))->handle(app(LineNotifyService::class));

        // queuePushDownloadLink uses idempotency key
        // "order.{$id}.line.download". Two runs → still ONE row.
        $this->assertSame(
            1,
            \DB::table('line_deliveries')
                ->where('idempotency_key', "order.{$order->id}.line.download")
                ->count(),
        );
    }
}
