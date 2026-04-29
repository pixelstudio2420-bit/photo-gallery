<?php

namespace Tests\Unit\Usage;

use App\Services\Usage\CircuitBreakerService;
use App\Services\Usage\QuotaResult;
use App\Services\Usage\QuotaService;
use App\Services\Usage\UsageMeter;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class QuotaServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->bootSchema();
        // Tests in this class don't need to talk to a real
        // CircuitBreaker store — the schema doesn't include it. We mock
        // the service down to whatever the test wants for isOpen().
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function bootSchema(): void
    {
        if (!Schema::hasTable('usage_counters')) {
            Schema::create('usage_counters', function ($t) {
                $t->unsignedBigInteger('user_id');
                $t->string('resource', 48);
                $t->string('period', 8);
                $t->string('period_key', 32);
                $t->bigInteger('units')->default(0);
                $t->bigInteger('cost_microcents')->default(0);
                $t->timestamp('updated_at')->useCurrent();
                $t->primary(['user_id', 'resource', 'period', 'period_key']);
            });
        } else {
            DB::table('usage_counters')->truncate();
        }
        if (!Schema::hasTable('usage_events')) {
            Schema::create('usage_events', function ($t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('user_id');
                $t->string('plan_code', 32);
                $t->string('resource', 48);
                $t->bigInteger('units');
                $t->bigInteger('cost_microcents')->default(0);
                $t->json('metadata')->nullable();
                $t->timestamp('occurred_at');
            });
        }
    }

    private function svc(bool $breakerOpen = false): QuotaService
    {
        $breakers = Mockery::mock(CircuitBreakerService::class);
        $breakers->shouldReceive('isOpen')->andReturn($breakerOpen);
        return new QuotaService($breakers);
    }

    private function fakeUser(int $id, string $planCode = 'free'): Authenticatable
    {
        return new class ($id, $planCode) implements Authenticatable {
            public function __construct(public readonly int $id, public readonly string $planCode) {}
            public ?object $photographerProfile;
            public function getAuthIdentifierName() { return 'id'; }
            public function getAuthIdentifier()     { return $this->id; }
            public function getAuthPasswordName()   { return 'password'; }
            public function getAuthPassword()       { return ''; }
            public function getRememberToken()      { return ''; }
            public function setRememberToken($v): void {}
            public function getRememberTokenName()  { return ''; }
        };
    }

    /**
     * Simple stub user that mimics App\Models\User just enough for
     * QuotaService::planCodeFor() to read .photographerProfile.
     */
    private function fakePhotographerUser(int $id, string $planCode): \App\Models\User
    {
        $u = new \App\Models\User();
        $u->forceFill(['id' => $id, 'first_name' => 'T']);
        $u->setRelation('photographerProfile', (object) ['subscription_plan_code' => $planCode]);
        return $u;
    }

    public function test_check_returns_ok_when_no_cap_is_declared(): void
    {
        $u = $this->fakePhotographerUser(1, 'studio');  // studio has null caps
        $r = $this->svc()->check($u, 'photo.upload', 1);

        // studio.photo.upload has hard=null → OK
        $this->assertTrue($r->allowed());
        $this->assertSame(QuotaResult::STATE_OK, $r->state);
    }

    public function test_check_blocks_when_hard_cap_is_zero_for_plan(): void
    {
        $u = $this->fakePhotographerUser(1, 'free');    // free.event.create.hard=0
        $r = $this->svc()->check($u, 'event.create');

        $this->assertTrue($r->blocked());
        $this->assertSame(QuotaResult::STATE_DISABLED, $r->state);
        $this->assertSame(403, $r->statusCode());
    }

    public function test_check_blocks_when_used_plus_units_exceeds_hard_cap(): void
    {
        $u = $this->fakePhotographerUser(7, 'free');
        // free.ai.face_search hard=50 → seed 50 used, ask for 1 more → blocked
        UsageMeter::record(7, 'free', 'ai.face_search', units: 50);

        $r = $this->svc()->check($u, 'ai.face_search', 1);

        $this->assertTrue($r->blocked());
        $this->assertSame(QuotaResult::STATE_HARD_BLOCK, $r->state);
        $this->assertSame(402, $r->statusCode());
    }

    public function test_check_returns_soft_warn_inside_band(): void
    {
        $u = $this->fakePhotographerUser(8, 'free');
        // free.ai.face_search soft=40, hard=50 → 41 used, ask for 1 → soft warn
        UsageMeter::record(8, 'free', 'ai.face_search', units: 41);

        $r = $this->svc()->check($u, 'ai.face_search', 1);

        $this->assertTrue($r->allowed());
        $this->assertSame(QuotaResult::STATE_SOFT_WARN, $r->state);
        // remaining() reports hard - used WITHOUT this request applied
        // (used = 41, hard = 50) → 9. The view-side "you have N left"
        // message reads this BEFORE the user fires the action.
        $this->assertSame(9, $r->remaining());
    }

    public function test_check_returns_breaker_when_circuit_is_open(): void
    {
        $u = $this->fakePhotographerUser(1, 'pro');
        $r = $this->svc(breakerOpen: true)->check($u, 'ai.face_search');

        $this->assertTrue($r->blocked());
        $this->assertSame(QuotaResult::STATE_BREAKER, $r->state);
        $this->assertSame(503, $r->statusCode());
    }

    public function test_master_switch_disables_all_enforcement(): void
    {
        config(['usage.enforcement_enabled' => false]);
        $u = $this->fakePhotographerUser(1, 'free');
        UsageMeter::record(1, 'free', 'ai.face_search', units: 9999);

        $r = $this->svc()->check($u, 'ai.face_search');

        $this->assertTrue($r->allowed());
        $this->assertSame(QuotaResult::STATE_OK, $r->state);
    }

    public function test_storage_uses_lifetime_period(): void
    {
        $u = $this->fakePhotographerUser(1, 'free');
        // free.storage.bytes hard=2GB, period=lifetime
        UsageMeter::record(1, 'free', 'storage.bytes', units: 2 * 1024 * 1024 * 1024);

        $r = $this->svc()->check($u, 'storage.bytes', 1);
        $this->assertTrue($r->blocked());
        $this->assertSame(QuotaResult::STATE_HARD_BLOCK, $r->state);
    }
}
