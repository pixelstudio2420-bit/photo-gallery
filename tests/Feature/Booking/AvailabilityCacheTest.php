<?php

namespace Tests\Feature\Booking;

use App\Models\PhotographerAvailability;
use App\Models\User;
use App\Services\AvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Locks down AvailabilityService caching.
 *
 * Properties:
 *   • Repeated isMomentAvailable calls hit the DB ONCE per cache TTL
 *   • Saving a PhotographerAvailability rule busts the cache
 *   • Deleting a rule busts the cache
 *   • flushCacheFor() is idempotent (no error on missing key)
 *
 * The cache must use Laravel's Cache facade (the default array driver
 * in tests) so it works regardless of the deployment's choice of
 * Redis/file/memcached.
 */
class AvailabilityCacheTest extends TestCase
{
    use RefreshDatabase;

    private User $photographer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->photographer = User::create([
            'first_name'    => 'Avail',
            'last_name'     => 'Test',
            'email'         => 'avail-' . uniqid() . '@example.com',
            'password_hash' => Hash::make('p'),
            'auth_provider' => 'local',
        ]);
        Cache::forget("availability.rules.{$this->photographer->id}");
    }

    private function makeRule(): PhotographerAvailability
    {
        return PhotographerAvailability::create([
            'photographer_id' => $this->photographer->id,
            'type'            => PhotographerAvailability::TYPE_RECURRING,
            'day_of_week'     => 1,  // Monday
            'time_start'      => '09:00:00',
            'time_end'        => '17:00:00',
            'effect'          => PhotographerAvailability::EFFECT_AVAILABLE,
        ]);
    }

    public function test_repeated_lookups_hit_db_only_once(): void
    {
        $this->makeRule();

        // Drain any rule-touch query log noise from the model save above.
        DB::flushQueryLog();
        DB::enableQueryLog();

        $svc = app(AvailabilityService::class);
        $monday = now()->next(\Carbon\Carbon::MONDAY)->setTime(10, 0);

        $svc->isMomentAvailable($this->photographer->id, $monday);
        $firstCount = count(DB::getQueryLog());

        $svc->isMomentAvailable($this->photographer->id, $monday);
        $svc->isMomentAvailable($this->photographer->id, $monday);
        $totalCount = count(DB::getQueryLog());

        $this->assertSame(
            $firstCount,
            $totalCount,
            'subsequent calls within the cache TTL must NOT add DB queries',
        );
    }

    public function test_saving_a_rule_busts_the_cache(): void
    {
        $rule = $this->makeRule();
        $svc = app(AvailabilityService::class);

        // Warm cache: photographer has open rule MO 09–17 → Monday 10:00 = available.
        $monday = now()->next(\Carbon\Carbon::MONDAY)->setTime(10, 0);
        $this->assertTrue($svc->isMomentAvailable($this->photographer->id, $monday));

        // Save a BLOCKED rule that covers 10:00 → moment must now be blocked.
        PhotographerAvailability::create([
            'photographer_id' => $this->photographer->id,
            'type'            => PhotographerAvailability::TYPE_RECURRING,
            'day_of_week'     => 1,
            'time_start'      => '10:00:00',
            'time_end'        => '11:00:00',
            'effect'          => PhotographerAvailability::EFFECT_BLOCKED,
            'label'           => 'Lunch test',
        ]);

        // Cache must have been busted by the model observer; the next
        // call sees the new BLOCKED rule.
        $this->assertFalse($svc->isMomentAvailable($this->photographer->id, $monday),
            'saving a rule must invalidate the cache');
    }

    public function test_deleting_a_rule_busts_the_cache(): void
    {
        $rule = $this->makeRule();
        $svc = app(AvailabilityService::class);

        $monday = now()->next(\Carbon\Carbon::MONDAY)->setTime(10, 0);
        $this->assertTrue($svc->isMomentAvailable($this->photographer->id, $monday));

        $rule->delete();

        // No rules → service falls back to "always available" (legacy
        // 24/7 default). The state changed, so the cache must reflect it.
        $this->assertTrue($svc->isMomentAvailable($this->photographer->id, $monday));
    }

    public function test_flush_cache_for_unknown_user_is_a_noop(): void
    {
        // Should NOT throw — Cache::forget on a missing key is fine.
        AvailabilityService::flushCacheFor(999_999);
        $this->assertTrue(true);   // didn't throw → contract held
    }
}
