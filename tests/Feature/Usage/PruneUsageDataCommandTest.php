<?php

namespace Tests\Feature\Usage;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PruneUsageDataCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->bootSchema();
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
        } else {
            DB::table('usage_events')->truncate();
        }
        if (!Schema::hasTable('signup_signals')) {
            Schema::create('signup_signals', function ($t) {
                $t->bigIncrements('id');
                $t->string('email_hash', 64)->nullable();
                $t->string('ip_hash', 64)->nullable();
                $t->string('device_fingerprint', 64)->nullable();
                $t->unsignedBigInteger('user_id')->nullable();
                $t->unsignedSmallInteger('risk_score')->default(0);
                $t->boolean('flagged')->default(false);
                $t->json('metadata')->nullable();
                $t->timestamp('created_at')->useCurrent();
            });
        } else {
            DB::table('signup_signals')->truncate();
        }
    }

    public function test_prunes_minute_counters_older_than_2_hours(): void
    {
        $now = Carbon::now();
        // Recent (kept) and old (pruned) minute rows
        DB::table('usage_counters')->insert([
            ['user_id' => 1, 'resource' => 'ai.face_search', 'period' => 'minute',
             'period_key' => $now->format('Y-m-d\TH:i'), 'units' => 1, 'cost_microcents' => 0, 'updated_at' => $now],
            ['user_id' => 1, 'resource' => 'ai.face_search', 'period' => 'minute',
             'period_key' => $now->copy()->subHours(3)->format('Y-m-d\TH:i'), 'units' => 1, 'cost_microcents' => 0, 'updated_at' => $now->copy()->subHours(3)],
        ]);

        $this->artisan('usage:prune')->assertExitCode(0);

        $remaining = DB::table('usage_counters')->where('period', 'minute')->count();
        $this->assertSame(1, $remaining, 'Only the recent (kept) minute row should survive');
    }

    public function test_prunes_usage_events_older_than_13_months(): void
    {
        DB::table('usage_events')->insert([
            ['user_id' => 1, 'plan_code' => 'free', 'resource' => 'photo.upload',
             'units' => 1, 'cost_microcents' => 0, 'occurred_at' => Carbon::now()->subMonths(2)],
            ['user_id' => 1, 'plan_code' => 'free', 'resource' => 'photo.upload',
             'units' => 1, 'cost_microcents' => 0, 'occurred_at' => Carbon::now()->subMonths(15)],
        ]);

        $this->artisan('usage:prune')->assertExitCode(0);

        $this->assertSame(1, DB::table('usage_events')->count());
    }

    public function test_prunes_signup_signals_older_than_90_days(): void
    {
        DB::table('signup_signals')->insert([
            ['email_hash' => str_repeat('a', 64), 'ip_hash' => null, 'device_fingerprint' => null,
             'risk_score' => 0, 'flagged' => false, 'created_at' => Carbon::now()->subDays(30)],
            ['email_hash' => str_repeat('b', 64), 'ip_hash' => null, 'device_fingerprint' => null,
             'risk_score' => 0, 'flagged' => false, 'created_at' => Carbon::now()->subDays(120)],
        ]);

        $this->artisan('usage:prune')->assertExitCode(0);

        $this->assertSame(1, DB::table('signup_signals')->count());
    }

    public function test_dry_run_does_not_delete(): void
    {
        DB::table('usage_events')->insert([
            'user_id' => 1, 'plan_code' => 'free', 'resource' => 'photo.upload',
            'units' => 1, 'cost_microcents' => 0, 'occurred_at' => Carbon::now()->subMonths(15),
        ]);

        $this->artisan('usage:prune', ['--dry-run' => true])->assertExitCode(0);

        $this->assertSame(1, DB::table('usage_events')->count());
    }

    public function test_no_op_returns_success_with_quiet_flag(): void
    {
        $this->artisan('usage:prune', ['--quiet-success' => true])
            ->assertExitCode(0);
    }
}
