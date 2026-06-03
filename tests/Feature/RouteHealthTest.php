<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Services\RouteHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Route & Page Health monitor — engine classification + admin dashboard.
 */
class RouteHealthTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        return Admin::create([
            'email'         => 'rh-admin-' . uniqid() . '@example.com',
            'password_hash' => Hash::make('admin-secret'),
            'first_name'    => 'Health',
            'last_name'     => 'Admin',
            'role'          => 'superadmin',
            'permissions'   => null,
            'is_active'     => true,
        ]);
    }

    public function test_engine_flags_5xx_as_fail(): void
    {
        Route::get('/__rh_test_500', fn () => abort(500, 'boom'));

        $svc = app(RouteHealthService::class);
        $r = $svc->runOne(['key' => 't500', 'label' => '500', 'kind' => 'route', 'path' => '/__rh_test_500']);

        $this->assertSame('fail', $r['result']);
        $this->assertSame(500, $r['status']);
    }

    public function test_engine_flags_2xx_with_error_body_as_fail(): void
    {
        Route::get('/__rh_test_badpage', fn () => response('<html>SQLSTATE[42703]: oops</html>', 200));

        $svc = app(RouteHealthService::class);
        $r = $svc->runOne(['key' => 'bad', 'label' => 'bad', 'kind' => 'page', 'path' => '/__rh_test_badpage']);

        $this->assertSame('fail', $r['result']);
        $this->assertStringContainsString('error marker', (string) $r['error']);
    }

    public function test_engine_passes_clean_200(): void
    {
        Route::get('/__rh_test_ok', fn () => response('<html><title>ok</title><body>fine</body></html>', 200));

        $svc = app(RouteHealthService::class);
        $r = $svc->runOne(['key' => 'ok', 'label' => 'ok', 'kind' => 'page', 'path' => '/__rh_test_ok']);

        $this->assertSame('ok', $r['result']);
    }

    public function test_intended_404_is_ok(): void
    {
        $svc = app(RouteHealthService::class);
        $r = $svc->runOne([
            'key' => 'i404', 'label' => '404', 'kind' => 'route',
            'path' => '/__definitely_not_a_route_zzz', 'intended_status' => 404,
        ]);

        $this->assertSame('ok', $r['result']);
        $this->assertSame(404, $r['status']);
    }

    public function test_runall_persists_and_computes_uptime(): void
    {
        $svc = app(RouteHealthService::class);
        $snapshot = $svc->runAll();

        $this->assertArrayHasKey('summary', $snapshot);
        $this->assertGreaterThan(0, $snapshot['summary']['total']);
        $this->assertDatabaseCount('route_health_checks', $snapshot['summary']['total']);

        $uptime = $svc->uptime(30);
        $this->assertSame($snapshot['summary']['total'], $uptime['total']);
        $this->assertNotNull($uptime['uptime_pct']);
    }

    public function test_admin_dashboard_loads(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.health.index'))
            ->assertStatus(200)
            ->assertSee('Route &amp; Page Health', false);
    }

    public function test_admin_run_now_triggers_check_and_redirects(): void
    {
        $resp = $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.health.run'));

        $resp->assertRedirect(route('admin.health.index'));
        $this->assertGreaterThan(0, \DB::table('route_health_checks')->count());
    }

    public function test_command_runs_and_exits_zero_when_clean(): void
    {
        // The curated targets all resolve against the test app; none should
        // 5xx on a fresh migration, so the command exits 0.
        $code = \Artisan::call('routes:health', ['--quiet-if-clean' => true, '--no-alert' => true]);
        $this->assertSame(0, $code);
    }
}
