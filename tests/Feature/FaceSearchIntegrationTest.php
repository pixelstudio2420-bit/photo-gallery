<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\EventPhoto;
use App\Services\FaceSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

/**
 * Feature-level tests covering the four face-search integration points:
 *   1) rekognition:reindex-event artisan command
 *   2) Coverage diagnostic endpoint
 *   3) Face-search API PDPA consent gate
 *   4) Command + endpoint error paths
 *
 * The tests mock FaceSearchService so no real AWS calls go out — this keeps
 * CI hermetic and costs $0.
 */
class FaceSearchIntegrationTest extends TestCase
{
    use RefreshDatabase;

    // ═════════════════════════════════════════════════════════════
    //  rekognition:reindex-event  (artisan command)
    // ═════════════════════════════════════════════════════════════

    public function test_reindex_command_errors_when_no_args(): void
    {
        $this->artisan('rekognition:reindex-event')
             ->expectsOutputToContain('Provide an event_id')
             ->assertFailed();
    }

    public function test_reindex_command_errors_when_aws_not_configured(): void
    {
        $this->makeEvent(1);

        config(['services.aws.key' => '', 'services.aws.secret' => '']);

        $this->artisan('rekognition:reindex-event', ['event_id' => 1])
             ->expectsOutputToContain('AWS Rekognition is not configured')
             ->assertFailed();
    }

    public function test_reindex_command_dry_run_does_not_call_aws(): void
    {
        $this->makeEvent(1);
        $this->makePhoto(1, ['id' => 10]);
        $this->makePhoto(1, ['id' => 11]);
        $this->makePhoto(1, ['id' => 12, 'rekognition_face_id' => 'already-indexed']);

        // Mock must never be called during dry-run
        $mock = Mockery::mock(FaceSearchService::class);
        $mock->shouldReceive('indexPhoto')->never();
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $this->app->instance(FaceSearchService::class, $mock);

        $this->artisan('rekognition:reindex-event', ['event_id' => 1, '--dry-run' => true])
             ->expectsOutputToContain('DRY RUN')
             ->assertSuccessful();
    }

    public function test_reindex_command_skips_already_indexed_photos_without_force(): void
    {
        $this->makeEvent(1);
        $this->makePhoto(1, ['id' => 20]);
        $this->makePhoto(1, ['id' => 21, 'rekognition_face_id' => 'keep-this']);

        $this->setAwsCredentials();

        $mock = Mockery::mock(FaceSearchService::class);
        $mock->shouldReceive('isConfigured')->andReturn(true);
        // indexPhoto should be called ONCE (only for photo #20 — #21 is already indexed)
        $mock->shouldReceive('indexPhoto')
             ->once()
             ->with(Mockery::on(fn($p) => $p->id === 20), Mockery::any())
             ->andReturn('new-face-id');
        $this->app->instance(FaceSearchService::class, $mock);

        $this->artisan('rekognition:reindex-event', ['event_id' => 1])
             ->assertSuccessful();
    }

    public function test_reindex_command_errors_on_missing_event(): void
    {
        $this->setAwsCredentials();
        $this->mockFaceServiceConfigured();

        $this->artisan('rekognition:reindex-event', ['event_id' => 9999])
             ->expectsOutputToContain('not found')
             ->assertFailed();
    }

    public function test_reindex_command_force_flag_reindexes_existing(): void
    {
        $this->makeEvent(1);
        $this->makePhoto(1, ['id' => 30, 'rekognition_face_id' => 'old-face-id']);

        $this->setAwsCredentials();

        $mock = Mockery::mock(FaceSearchService::class);
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('indexPhoto')->once()->andReturn('new-face-id');
        $this->app->instance(FaceSearchService::class, $mock);

        $this->artisan('rekognition:reindex-event', ['event_id' => 1, '--force' => true])
             ->assertSuccessful();
    }

    // ═════════════════════════════════════════════════════════════
    //  Coverage endpoint  (GET /admin/diagnostics/events/{id}/face-coverage)
    // ═════════════════════════════════════════════════════════════

    public function test_coverage_endpoint_returns_404_for_missing_event(): void
    {
        $admin = $this->actingAsAdmin();
        $this->getJson('/admin/diagnostics/events/9999/face-coverage')
             ->assertStatus(404)
             ->assertJson(['error' => 'event_not_found']);
    }

    public function test_coverage_endpoint_computes_percentage_correctly(): void
    {
        $this->actingAsAdmin();
        $this->setAwsCredentials();

        $this->makeEvent(5);
        $this->makePhoto(5, ['id' => 100, 'rekognition_face_id' => 'a']);
        $this->makePhoto(5, ['id' => 101, 'rekognition_face_id' => 'b']);
        $this->makePhoto(5, ['id' => 102, 'rekognition_face_id' => 'c']);
        $this->makePhoto(5, ['id' => 103]); // pending
        $this->makePhoto(5, ['id' => 104]); // pending

        $resp = $this->getJson('/admin/diagnostics/events/5/face-coverage');

        $resp->assertOk()
             ->assertJson([
                 'event_id'          => 5,
                 'total_photos'      => 5,
                 'active_photos'     => 5,
                 'indexed_photos'    => 3,
                 'pending_photos'    => 2,
                 'coverage_pct'      => 60.0,
                 'rekognition_ready' => true,
                 'collection_id'     => 'event-5',
             ]);
    }

    public function test_coverage_endpoint_handles_empty_event(): void
    {
        $this->actingAsAdmin();
        $this->makeEvent(6);

        $this->getJson('/admin/diagnostics/events/6/face-coverage')
             ->assertOk()
             ->assertJson([
                 'total_photos'   => 0,
                 'active_photos'  => 0,
                 'indexed_photos' => 0,
                 'coverage_pct'   => 0,
             ]);
    }

    public function test_coverage_endpoint_reports_rekognition_not_ready(): void
    {
        $this->actingAsAdmin();
        config(['services.aws.key' => '', 'services.aws.secret' => '']);

        $this->makeEvent(7);

        $this->getJson('/admin/diagnostics/events/7/face-coverage')
             ->assertOk()
             ->assertJson(['rekognition_ready' => false]);
    }

    // ═════════════════════════════════════════════════════════════
    //  PDPA consent gate  (POST /api/face-search/{id})
    // ═════════════════════════════════════════════════════════════

    public function test_face_search_rejects_request_without_consent(): void
    {
        $this->setAwsCredentials();
        $this->makeEvent(1);
        // Route is `auth`-gated (PDPA logging requires user identity).
        // The test exercises the consent validation INSIDE the controller,
        // so we authenticate as any user first.
        $this->actingAs($this->makeAuthUser());

        $resp = $this->postJson('/api/face-search/1', [
            'selfie' => \Illuminate\Http\UploadedFile::fake()->image('selfie.jpg', 200, 200),
            // consent intentionally missing
        ]);

        $resp->assertStatus(422);
        $this->assertArrayHasKey('consent', $resp->json('errors') ?? []);
    }

    public function test_face_search_rejects_when_consent_is_false(): void
    {
        $this->setAwsCredentials();
        $this->makeEvent(1);
        $this->actingAs($this->makeAuthUser());

        $resp = $this->postJson('/api/face-search/1', [
            'selfie'  => \Illuminate\Http\UploadedFile::fake()->image('selfie.jpg', 200, 200),
            'consent' => '0',
        ]);

        $resp->assertStatus(422);
    }

    /**
     * Minimal user creation helper. The test only needs an authenticated
     * principal — no profile, no relations — so we hand-build a row to
     * avoid factory dependencies that drag in the full domain graph.
     */
    private function makeAuthUser(): \App\Models\User
    {
        $u = \App\Models\User::create([
            'first_name'    => 'T',
            'last_name'     => 'User',
            'email'         => 'fs-test-' . uniqid() . '@example.com',
            'password_hash' => \Illuminate\Support\Facades\Hash::make('password'),
            'auth_provider' => 'local',
        ]);
        return $u;
    }

    // ═════════════════════════════════════════════════════════════
    //  Helpers
    // ═════════════════════════════════════════════════════════════

    private function setAwsCredentials(): void
    {
        DB::table('app_settings')->insert([
            ['key' => 'aws_key',    'value' => 'AKIAIOSFODNN7EXAMPLE',                     'updated_at' => now()],
            ['key' => 'aws_secret', 'value' => 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY', 'updated_at' => now()],
        ]);
        AppSetting::flushCache();
    }

    private function mockFaceServiceConfigured(): void
    {
        $mock = Mockery::mock(FaceSearchService::class);
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('indexPhoto')->andReturn(null);
        $this->app->instance(FaceSearchService::class, $mock);
    }

    private function makeEvent(int $id): void
    {
        if (DB::table('event_events')->where('id', $id)->exists()) return;

        DB::table('event_events')->insert([
            'id'         => $id,
            'name'       => 'Test Event ' . $id,
            'slug'       => 'test-event-' . $id,
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makePhoto(int $eventId, array $overrides = []): EventPhoto
    {
        // Use forceFill() so we can set the primary key in tests — `id` isn't
        // in $fillable so EventPhoto::create([...,'id'=>20,...]) would silently
        // drop the id and auto-increment instead, breaking mocks that match
        // on $photo->id.
        $attributes = array_merge([
            'event_id'          => $eventId,
            'source'            => 'upload',
            'filename'          => 'test.jpg',
            'original_filename' => 'test.jpg',
            'mime_type'         => 'image/jpeg',
            'file_size'         => 1024,
            'storage_disk'      => 'public',
            'original_path'     => 'photos/test.jpg',
            'thumbnail_path'    => 'photos/test_thumb.jpg',
            'watermarked_path'  => 'photos/test_wm.jpg',
            'status'            => 'active',
        ], $overrides);

        $photo = new EventPhoto();
        $photo->forceFill($attributes);
        $photo->save();
        return $photo;
    }

    /**
     * Bypass admin/auth middleware for admin-guarded diagnostic routes.
     * The diagnostics endpoint lives inside the `admin` middleware group; we
     * disable middleware wholesale rather than stand up a real admin session
     * — the controller logic is what we want to exercise.
     */
    private function actingAsAdmin(): self
    {
        $this->withoutMiddleware();
        return $this;
    }

    protected function setUp(): void
    {
        parent::setUp();
        // AppSetting keeps a static in-memory cache that survives RefreshDatabase —
        // flush it so each test starts with a clean slate (important for the
        // "rekognition not ready" test which relies on an unconfigured state).
        AppSetting::flushCache();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
