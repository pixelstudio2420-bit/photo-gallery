<?php

namespace Tests\Unit\Usage;

use App\Models\CircuitBreaker;
use App\Services\Usage\CircuitBreakerService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CircuitBreakerServiceTest extends TestCase
{
    private CircuitBreakerService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootSchema();
        Cache::flush();
        // Tight ceiling so tests can trip it without huge floats
        config(['usage.breakers' => [
            'ai.face_search'     => ['monthly_thb_ceiling' => 100,  'reset_period' => 'month'],
            'ai.preset_generate' => ['monthly_thb_ceiling' => 50,   'reset_period' => 'month'],
        ]]);
        $this->svc = new CircuitBreakerService();
    }

    private function bootSchema(): void
    {
        if (!Schema::hasTable('circuit_breakers')) {
            Schema::create('circuit_breakers', function ($t) {
                $t->string('feature', 64)->primary();
                $t->string('state', 16)->default('closed');
                $t->timestamp('opened_at')->nullable();
                $t->timestamp('reopened_at')->nullable();
                $t->decimal('threshold_thb', 12, 2);
                $t->decimal('spent_thb', 12, 2)->default(0);
                $t->timestamp('period_starts');
                $t->timestamp('period_ends');
                $t->text('notes')->nullable();
                $t->timestamps();
            });
        } else {
            DB::table('circuit_breakers')->truncate();
        }
    }

    public function test_isopen_returns_false_when_no_row_exists(): void
    {
        $this->assertFalse($this->svc->isOpen('ai.face_search'));
    }

    public function test_charge_creates_a_row_and_accumulates(): void
    {
        $this->svc->charge('ai.face_search', 10.0);
        $this->svc->charge('ai.face_search', 25.0);

        $row = CircuitBreaker::find('ai.face_search');
        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(35.0, (float) $row->spent_thb, 0.001);
        $this->assertSame(CircuitBreaker::STATE_CLOSED, $row->state);
    }

    public function test_charge_trips_open_when_threshold_reached(): void
    {
        $this->svc->charge('ai.face_search', 99.0);
        $this->assertFalse($this->svc->isOpen('ai.face_search'));

        $this->svc->charge('ai.face_search', 2.0);  // total = 101 ≥ 100

        Cache::flush();
        $this->assertTrue($this->svc->isOpen('ai.face_search'));

        $row = CircuitBreaker::find('ai.face_search');
        $this->assertSame(CircuitBreaker::STATE_OPEN, $row->state);
        $this->assertNotNull($row->opened_at);
    }

    public function test_charge_does_not_re_trip_an_already_open_breaker(): void
    {
        $this->svc->charge('ai.face_search', 200.0);
        $opened = CircuitBreaker::find('ai.face_search')->opened_at;

        sleep(1);
        $this->svc->charge('ai.face_search', 50.0);

        $this->assertEquals($opened, CircuitBreaker::find('ai.face_search')->opened_at,
            'opened_at should be set ONCE on the first trip — not bumped on every charge');
    }

    public function test_manual_reset_moves_open_to_half_open(): void
    {
        $this->svc->charge('ai.face_search', 200.0);
        $this->assertTrue($this->svc->isOpen('ai.face_search'));

        $this->svc->reset('ai.face_search', 'investigated, false alarm');

        Cache::flush();
        $this->assertFalse($this->svc->isOpen('ai.face_search'));
        $this->assertSame(
            CircuitBreaker::STATE_HALF_OPEN,
            CircuitBreaker::find('ai.face_search')->state,
        );
    }

    public function test_manual_trip_immediately_opens_a_breaker(): void
    {
        $this->svc->trip('ai.preset_generate', 'panic button');
        Cache::flush();

        $this->assertTrue($this->svc->isOpen('ai.preset_generate'));
        $this->assertSame(
            CircuitBreaker::STATE_OPEN,
            CircuitBreaker::find('ai.preset_generate')->state,
        );
    }

    public function test_charge_with_unknown_feature_is_a_safe_no_op(): void
    {
        // No declared breaker for 'random.feature' → method should return
        // without exception or DB row.
        $this->svc->charge('random.feature', 99999.0);
        $this->assertSame(0, DB::table('circuit_breakers')->count());
    }

    public function test_snapshot_returns_one_row_per_declared_breaker(): void
    {
        $this->svc->charge('ai.face_search', 50);

        $snapshot = $this->svc->snapshot();
        $this->assertCount(2, $snapshot); // both declared features
        $byFeature = collect($snapshot)->keyBy('feature');

        $this->assertEqualsWithDelta(50.0, (float) $byFeature['ai.face_search']['spent_thb'], 0.001);
        $this->assertSame(50.0, $byFeature['ai.face_search']['utilization_pct']);
        // ai.preset_generate has no row yet; default to closed/0
        $this->assertSame('closed', $byFeature['ai.preset_generate']['state']);
        $this->assertSame(0.0, (float) $byFeature['ai.preset_generate']['spent_thb']);
    }
}
