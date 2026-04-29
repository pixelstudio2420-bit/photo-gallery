<?php

namespace Tests\Feature\Media;

use App\Jobs\PurgeR2CdnCacheJob;
use App\Models\AppSetting;
use App\Services\CloudflareCachePurgeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Verifies CDN purge orchestration.
 *
 * The project's full migration set is currently broken on sqlite (a
 * Postgres-only CHECK constraint), so this test creates only the one
 * table the job + Cloudflare service touch — `app_settings`. That keeps
 * us close to production semantics (real Eloquent reads) without paying
 * for the broken migration.
 */
class PurgeR2CdnCacheJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->bootMinimalSchema();
        // AppSetting maintains a static + Laravel cache; without flushing,
        // settings written by one test stay invisible to the next because
        // loadAll() short-circuits on the first empty fetch.
        AppSetting::flushCache();
    }

    protected function tearDown(): void
    {
        AppSetting::flushCache();
        parent::tearDown();
    }

    private function bootMinimalSchema(): void
    {
        if (!Schema::connection('sqlite')->hasTable('app_settings')) {
            Schema::connection('sqlite')->create('app_settings', function ($t) {
                $t->id();
                $t->string('key')->unique();
                $t->text('value')->nullable();
                $t->timestamps();
            });
        } else {
            DB::connection('sqlite')->table('app_settings')->truncate();
        }
    }

    private function configureCloudflare(bool $enabled, ?string $cdnDomain = 'cdn.example.com'): void
    {
        // Use ::set() so the static + Laravel caches are invalidated as
        // each row lands. Calling create() directly would leave the
        // cached "all settings" array stale and `isEnabled()` would still
        // see the previous (or empty) snapshot.
        AppSetting::set('cloudflare_enabled',   $enabled ? '1' : '0');
        AppSetting::set('cloudflare_zone_id',   'zone-test');
        AppSetting::set('cloudflare_api_token', 'token-test');
        if ($cdnDomain) {
            AppSetting::set('r2_custom_domain', $cdnDomain);
        }
    }

    public function test_no_ops_when_cloudflare_disabled(): void
    {
        $this->configureCloudflare(enabled: false);
        Http::fake();

        $job = new PurgeR2CdnCacheJob(['events/photos/user_1/event_1/abc.jpg']);
        $job->handle(new CloudflareCachePurgeService());

        Http::assertNothingSent();
    }

    public function test_no_ops_when_keys_array_is_empty(): void
    {
        $this->configureCloudflare(enabled: true);
        Http::fake();

        $job = new PurgeR2CdnCacheJob([]);
        $job->handle(new CloudflareCachePurgeService());

        Http::assertNothingSent();
    }

    public function test_builds_absolute_urls_from_keys(): void
    {
        $this->configureCloudflare(enabled: true);
        Http::fake([
            'api.cloudflare.com/*' => Http::response(['success' => true, 'result' => []], 200),
        ]);

        $job = new PurgeR2CdnCacheJob([
            'events/photos/user_1/event_1/abc.jpg',
            'events/photos/user_1/event_1/def.jpg',
        ]);
        $job->handle(new CloudflareCachePurgeService());

        Http::assertSent(function ($request) {
            $body = $request->data();
            return isset($body['files'])
                && in_array('https://cdn.example.com/events/photos/user_1/event_1/abc.jpg', $body['files'], true)
                && in_array('https://cdn.example.com/events/photos/user_1/event_1/def.jpg', $body['files'], true);
        });
    }

    public function test_throws_on_cloudflare_failure_so_queue_retries(): void
    {
        $this->configureCloudflare(enabled: true);
        Http::fake([
            'api.cloudflare.com/*' => Http::response([
                'success' => false,
                'errors'  => [['message' => 'Invalid API token']],
            ], 403),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/cloudflare purge failed/i');

        $job = new PurgeR2CdnCacheJob(['events/photos/user_1/event_1/abc.jpg']);
        $job->handle(new CloudflareCachePurgeService());
    }

    public function test_unique_id_collapses_dispatches_for_same_resource(): void
    {
        $a = new PurgeR2CdnCacheJob(['k1', 'k2'], 'resource:event_99');
        $b = new PurgeR2CdnCacheJob(['k3', 'k4'], 'resource:event_99');
        $c = new PurgeR2CdnCacheJob(['k5'],       'resource:event_100');

        $this->assertSame($a->uniqueId(), $b->uniqueId(), 'Same tag → same uniqueId');
        $this->assertNotSame($a->uniqueId(), $c->uniqueId(), 'Different tag → different uniqueId');
    }

    public function test_no_cdn_domain_configured_logs_and_skips(): void
    {
        $this->configureCloudflare(enabled: true, cdnDomain: '');
        config(['filesystems.disks.r2.url' => null]);
        Http::fake();

        $job = new PurgeR2CdnCacheJob(['events/photos/user_1/event_1/abc.jpg']);
        $job->handle(new CloudflareCachePurgeService());

        Http::assertNothingSent();
    }
}
