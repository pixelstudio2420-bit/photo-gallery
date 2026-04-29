<?php

namespace Tests\Unit;

use App\Models\AppSetting;
use App\Models\EventPhoto;
use App\Services\FaceSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class FaceSearchServiceTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────────
    //  isConfigured()
    // ─────────────────────────────────────────────────────────────

    public function test_is_configured_returns_false_when_no_keys(): void
    {
        config(['services.aws.key' => '', 'services.aws.secret' => '']);

        $service = new FaceSearchService();
        $this->assertFalse($service->isConfigured());
    }

    public function test_is_configured_returns_true_when_keys_set(): void
    {
        $this->setAwsCredentials();

        $service = new FaceSearchService();
        $this->assertTrue($service->isConfigured());
    }

    // ─────────────────────────────────────────────────────────────
    //  detectFaces() — returns empty when unconfigured
    // ─────────────────────────────────────────────────────────────

    public function test_detect_faces_returns_empty_when_not_configured(): void
    {
        config(['services.aws.key' => '', 'services.aws.secret' => '']);

        $service = new FaceSearchService();
        $result  = $service->detectFaces('fake-image-bytes');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // ─────────────────────────────────────────────────────────────
    //  indexPhoto() — auto-indexing hook used by upload pipeline
    // ─────────────────────────────────────────────────────────────

    public function test_index_photo_returns_null_when_aws_not_configured(): void
    {
        config(['services.aws.key' => '', 'services.aws.secret' => '']);

        $photo = $this->makePhoto();

        $service = new FaceSearchService();
        $result  = $service->indexPhoto($photo);

        $this->assertNull($result);
        $this->assertNull($photo->fresh()->rekognition_face_id);
    }

    public function test_index_photo_short_circuits_when_already_indexed(): void
    {
        $this->setAwsCredentials();

        $photo = $this->makePhoto(['rekognition_face_id' => 'existing-face-abc']);

        // Spy on indexFace — it must NOT be called when face_id already exists
        $service = Mockery::mock(FaceSearchService::class)->makePartial();
        $service->shouldReceive('indexFace')->never();

        $result = $service->indexPhoto($photo);

        $this->assertSame('existing-face-abc', $result);
    }

    public function test_index_photo_returns_null_when_image_path_and_disk_both_unreadable(): void
    {
        $this->setAwsCredentials();

        $photo = $this->makePhoto([
            'storage_disk'  => 'public',
            'original_path' => 'does-not-exist.jpg',
        ]);

        // Ensure disk has no such file
        Storage::fake('public');

        // Partial mock — indexFace must NOT be called if we cannot obtain bytes
        $service = Mockery::mock(FaceSearchService::class)->makePartial();
        $service->shouldReceive('indexFace')->never();

        $result = $service->indexPhoto($photo, '/nonexistent/temp/path.jpg');

        $this->assertNull($result);
        $this->assertNull($photo->fresh()->rekognition_face_id);
    }

    public function test_index_photo_persists_face_id_on_successful_indexing(): void
    {
        $this->setAwsCredentials();

        $photo   = $this->makePhoto();
        $tmpPath = $this->makeTempImageFile();

        $service = Mockery::mock(FaceSearchService::class)->makePartial();
        $service
            ->shouldReceive('indexFace')
            ->once()
            ->with(
                Mockery::type('string'),
                'event-' . $photo->event_id,          // collection name format
                (string) $photo->id                    // external image id format
            )
            ->andReturn([
                ['face_id' => 'rekog-face-xyz-123', 'external_id' => (string) $photo->id],
            ]);

        $result = $service->indexPhoto($photo, $tmpPath);

        $this->assertSame('rekog-face-xyz-123', $result);
        $this->assertSame('rekog-face-xyz-123', $photo->fresh()->rekognition_face_id);

        @unlink($tmpPath);
    }

    public function test_index_photo_returns_null_when_no_face_detected(): void
    {
        $this->setAwsCredentials();

        $photo   = $this->makePhoto();
        $tmpPath = $this->makeTempImageFile();

        // Rekognition returns [] → no face detected in image
        $service = Mockery::mock(FaceSearchService::class)->makePartial();
        $service->shouldReceive('indexFace')->once()->andReturn([]);

        $result = $service->indexPhoto($photo, $tmpPath);

        $this->assertNull($result);
        $this->assertNull($photo->fresh()->rekognition_face_id);

        @unlink($tmpPath);
    }

    public function test_index_photo_falls_back_to_disk_when_temp_path_missing(): void
    {
        $this->setAwsCredentials();
        Storage::fake('public');

        // Put a real fake image on the disk
        Storage::disk('public')->put('photos/fallback.jpg', 'fake-jpeg-bytes-for-test');

        $photo = $this->makePhoto([
            'storage_disk'  => 'public',
            'original_path' => 'photos/fallback.jpg',
        ]);

        $service = Mockery::mock(FaceSearchService::class)->makePartial();
        $service
            ->shouldReceive('indexFace')
            ->once()
            ->andReturn([['face_id' => 'fallback-face-id']]);

        // Pass null as tmp path — service must pull bytes from disk
        $result = $service->indexPhoto($photo, null);

        $this->assertSame('fallback-face-id', $result);
        $this->assertSame('fallback-face-id', $photo->fresh()->rekognition_face_id);
    }

    public function test_index_photo_returns_null_when_event_id_missing(): void
    {
        $this->setAwsCredentials();

        // EventPhoto::create requires event_id; we simulate a broken record via
        // direct instantiation (not saved) to exercise the guard clause.
        $photo = new EventPhoto();
        $photo->id       = 999;
        $photo->event_id = null;

        $service = new FaceSearchService();
        $this->assertNull($service->indexPhoto($photo));
    }

    // ─────────────────────────────────────────────────────────────
    //  deleteFace() — cleanup hook used by EventPhoto::deleting
    // ─────────────────────────────────────────────────────────────

    public function test_delete_face_returns_false_when_not_configured(): void
    {
        config(['services.aws.key' => '', 'services.aws.secret' => '']);

        $service = new FaceSearchService();
        $this->assertFalse($service->deleteFace('event-1', 'some-face-id'));
    }

    public function test_delete_face_returns_false_with_empty_args(): void
    {
        $this->setAwsCredentials();

        $service = new FaceSearchService();
        $this->assertFalse($service->deleteFace('', 'face-id'));
        $this->assertFalse($service->deleteFace('event-1', ''));
    }

    public function test_event_photo_deleting_hook_triggers_face_delete(): void
    {
        $this->setAwsCredentials();

        $photo = $this->makePhoto(['rekognition_face_id' => 'face-to-cleanup-xyz']);

        $mock = Mockery::mock(FaceSearchService::class);
        $mock->shouldReceive('deleteFace')
             ->once()
             ->with('event-' . $photo->event_id, 'face-to-cleanup-xyz')
             ->andReturn(true);
        $this->app->instance(FaceSearchService::class, $mock);

        // Triggering ->delete() should fire the model's deleting event
        $photo->delete();

        $this->assertDatabaseMissing('event_photos', ['id' => $photo->id]);
    }

    public function test_event_photo_deleting_hook_skipped_when_no_face_id(): void
    {
        $this->setAwsCredentials();

        $photo = $this->makePhoto(); // no rekognition_face_id

        $mock = Mockery::mock(FaceSearchService::class);
        $mock->shouldReceive('deleteFace')->never();
        $this->app->instance(FaceSearchService::class, $mock);

        $photo->delete();
        $this->assertTrue(true); // reached here → mock expectation held
    }

    public function test_event_photo_delete_succeeds_even_if_face_cleanup_throws(): void
    {
        $this->setAwsCredentials();

        $photo = $this->makePhoto(['rekognition_face_id' => 'face-will-fail']);

        $mock = Mockery::mock(FaceSearchService::class);
        $mock->shouldReceive('deleteFace')
             ->once()
             ->andThrow(new \RuntimeException('AWS unreachable'));
        $this->app->instance(FaceSearchService::class, $mock);

        // Must NOT throw — AWS failure should never block a photo delete
        $photo->delete();
        $this->assertDatabaseMissing('event_photos', ['id' => $photo->id]);
    }

    // ─────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────

    private function setAwsCredentials(): void
    {
        DB::table('app_settings')->insert([
            ['key' => 'aws_key',    'value' => 'AKIAIOSFODNN7EXAMPLE',                       'updated_at' => now()],
            ['key' => 'aws_secret', 'value' => 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',   'updated_at' => now()],
            ['key' => 'aws_region', 'value' => 'ap-southeast-1',                              'updated_at' => now()],
        ]);
        AppSetting::flushCache();
    }

    /**
     * Create a minimal event_events row + EventPhoto record bound to it,
     * working around the FK so tests stay independent from the full event
     * fixture stack.
     */
    private function makePhoto(array $overrides = []): EventPhoto
    {
        $eventId = $overrides['event_id'] ?? 1;

        if (!DB::table('event_events')->where('id', $eventId)->exists()) {
            DB::table('event_events')->insert([
                'id'         => $eventId,
                'name'       => 'Test Event',
                'slug'       => 'test-event-' . $eventId,
                'status'     => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return EventPhoto::create(array_merge([
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
        ], $overrides));
    }

    /**
     * Create a throw-away image file on the local filesystem. The bytes don't
     * need to be a valid JPEG because we mock indexFace() — we only need the
     * service to successfully read bytes via file_get_contents().
     */
    private function makeTempImageFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'face_test_');
        file_put_contents($path, str_repeat('x', 64)); // 64 bytes, enough to pass the empty-check
        return $path;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
