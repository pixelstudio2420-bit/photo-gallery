<?php

namespace Tests\Feature\Notifications;

use App\Models\PhotographerProfile;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Locks down the addons:notify-expiring command's two contracts:
 *
 *   A. T-3 day warning — fires for addons expiring in ~3 days,
 *      doesn't fire for addons expiring outside that window.
 *
 *   B. Auto-expire — flips status='activated' → 'expired' for rows
 *      whose expires_at has already passed, and notifies once.
 *
 * Both branches dedup on refId so re-running the cron is idempotent.
 */
class AddonsNotifyExpiringCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makePhotographer(): User
    {
        $user = User::create([
            'first_name'    => 'Add',
            'last_name'     => 'Tester',
            'email'         => 'addon-' . uniqid() . '@test.local',
            'password_hash' => Hash::make('p'),
            'auth_provider' => 'local',
        ]);
        PhotographerProfile::create([
            'user_id'           => $user->id,
            'photographer_code' => 'PH-T' . substr(uniqid(), -6),
            'display_name'      => 'Add Tester',
            'commission_rate'   => 80,
            'status'            => 'approved',
            'tier'              => 'pro',
        ]);
        return $user;
    }

    private function seedAddon(int $photographerId, string $status, ?\Carbon\CarbonInterface $expiresAt, array $snapshot = []): int
    {
        return DB::table('photographer_addon_purchases')->insertGetId([
            'photographer_id' => $photographerId,
            'sku'             => 'storage.50gb',
            'category'        => 'storage',
            'price_thb'       => 290,
            'snapshot'        => json_encode(array_merge([
                'sku'       => 'storage.50gb',
                'label'     => '+50 GB',
                'price_thb' => 290,
            ], $snapshot)),
            'status'          => $status,
            'activated_at'    => now()->subDay(),
            'expires_at'      => $expiresAt,
            'created_at'      => now()->subDay(),
            'updated_at'      => now(),
        ]);
    }

    /* ───────────── T-3 day warning branch ───────────── */

    public function test_addon_expiring_in_3_days_fires_warning(): void
    {
        $user = $this->makePhotographer();
        $purchaseId = $this->seedAddon(
            $user->id, 'activated',
            now()->addDays(3)->addHours(2),   // ~3 days from now
        );

        $this->artisan('addons:notify-expiring')->assertSuccessful();

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $user->id,
            'type'    => 'addon.expiring',
            'ref_id'  => "addon.{$purchaseId}.expiring.3d",
        ]);
    }

    public function test_addon_expiring_in_10_days_does_not_fire(): void
    {
        $user = $this->makePhotographer();
        $this->seedAddon($user->id, 'activated', now()->addDays(10));

        $this->artisan('addons:notify-expiring')->assertSuccessful();

        // No notification — outside the T-3 day window
        $this->assertSame(0, UserNotification::where('user_id', $user->id)
            ->where('type', 'addon.expiring')
            ->count());
    }

    public function test_running_twice_does_not_duplicate_t3_warning(): void
    {
        $user = $this->makePhotographer();
        $this->seedAddon($user->id, 'activated', now()->addDays(3)->addHours(2));

        $this->artisan('addons:notify-expiring')->assertSuccessful();
        $this->artisan('addons:notify-expiring')->assertSuccessful();

        $this->assertSame(1, UserNotification::where('user_id', $user->id)
            ->where('type', 'addon.expiring')
            ->count(),
            'T-3 warning must dedup across cron re-runs.');
    }

    /* ───────────── Auto-expire branch ───────────── */

    public function test_past_due_addon_flipped_to_expired_and_notified(): void
    {
        $user = $this->makePhotographer();
        $purchaseId = $this->seedAddon(
            $user->id, 'activated',
            now()->subDay(),   // already expired
        );

        $this->artisan('addons:notify-expiring')->assertSuccessful();

        // Status flipped
        $this->assertSame('expired',
            DB::table('photographer_addon_purchases')->where('id', $purchaseId)->value('status'));

        // Notification fired
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $user->id,
            'type'    => 'addon.expired',
            'ref_id'  => "addon.{$purchaseId}.expired",
        ]);
    }

    public function test_already_expired_row_not_re_notified(): void
    {
        $user = $this->makePhotographer();
        $purchaseId = $this->seedAddon($user->id, 'expired', now()->subDay());

        $this->artisan('addons:notify-expiring')->assertSuccessful();

        // No notification — row was already expired (status filter excludes)
        $this->assertSame(0, UserNotification::where('user_id', $user->id)
            ->where('type', 'addon.expired')
            ->count());
    }

    public function test_lifetime_addon_with_null_expiry_not_touched(): void
    {
        $user = $this->makePhotographer();
        $purchaseId = $this->seedAddon($user->id, 'activated', null);   // lifetime

        $this->artisan('addons:notify-expiring')->assertSuccessful();

        // Status unchanged — null expires_at means lifetime add-on
        $this->assertSame('activated',
            DB::table('photographer_addon_purchases')->where('id', $purchaseId)->value('status'));
    }
}
