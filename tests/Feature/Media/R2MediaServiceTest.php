<?php

namespace Tests\Feature\Media;

use App\Services\Media\Exceptions\InvalidMediaFileException;
use App\Services\Media\Exceptions\StorageNotConfiguredException;
use App\Services\Media\MediaContext;
use App\Services\Media\R2MediaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Feature tests for R2MediaService.
 *
 * Strategy: swap the 'r2' disk for a fake (in-memory) Flysystem during
 * setUp, drive the service exactly as production code would, then assert
 * what landed on the fake disk. We don't need real R2 credentials and the
 * tests run in a few hundred milliseconds.
 */
class R2MediaServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Swap r2 disk for an in-memory fake. R2MediaService talks to it
        // exactly the same way it talks to real R2.
        Storage::fake('r2');

        // Ensure media config knows it's pointing at the fake disk.
        config(['media.disk' => 'r2']);
        config(['media.r2_only' => false]); // skip the bucket-cred sanity check
    }

    public function test_upload_avatar_writes_to_canonical_path(): void
    {
        $service = $this->app->make(R2MediaService::class);
        $file    = UploadedFile::fake()->image('selfie.png', 600, 600);

        $result = $service->uploadAvatar(123, $file);

        $this->assertStringStartsWith('auth/avatar/user_123/', $result->key);
        $this->assertStringEndsWith('.png', $result->key);
        Storage::disk('r2')->assertExists($result->key);
    }

    public function test_upload_event_photo_writes_inside_event_folder(): void
    {
        $service = $this->app->make(R2MediaService::class);
        $file    = UploadedFile::fake()->image('DSC_0001.jpg', 1024, 768);

        $result = $service->uploadEventPhoto(45, 789, $file);

        $this->assertStringStartsWith('events/photos/user_45/event_789/', $result->key);
        $this->assertSame('image/jpeg', $result->mimeType);
        Storage::disk('r2')->assertExists($result->key);
    }

    public function test_upload_payment_slip_isolates_by_order(): void
    {
        $service = $this->app->make(R2MediaService::class);
        $file    = UploadedFile::fake()->image('slip.jpg', 1024, 1024);

        $a = $service->uploadPaymentSlip(678, 5511, $file);
        $b = $service->uploadPaymentSlip(678, 5512, $file);

        $this->assertStringContainsString('user_678/order_5511/', $a->key);
        $this->assertStringContainsString('user_678/order_5512/', $b->key);
        $this->assertNotSame($a->key, $b->key);
    }

    public function test_upload_rejects_oversize_file(): void
    {
        // auth.avatar max = 2MB. Send 3MB.
        $service = $this->app->make(R2MediaService::class);
        $file    = UploadedFile::fake()->create('huge.jpg', 3 * 1024, 'image/jpeg');

        $this->expectException(InvalidMediaFileException::class);
        $this->expectExceptionMessageMatches('/too large/i');

        $service->uploadAvatar(1, $file);
    }

    public function test_upload_rejects_disallowed_mime(): void
    {
        $service = $this->app->make(R2MediaService::class);
        // Avatar allows only jpeg/png/webp — reject application/pdf
        $file = UploadedFile::fake()->create('not-an-image.pdf', 100, 'application/pdf');

        $this->expectException(InvalidMediaFileException::class);
        $this->expectExceptionMessageMatches('/MIME type.*not allowed/i');

        $service->uploadAvatar(1, $file);
    }

    public function test_upload_rejects_disallowed_extension_even_if_mime_matches(): void
    {
        $service = $this->app->make(R2MediaService::class);
        // Smuggling: jpeg-typed file but `.exe` extension
        $file = UploadedFile::fake()->create('shell.exe', 100, 'image/jpeg');

        $this->expectException(InvalidMediaFileException::class);

        $service->uploadAvatar(1, $file);
    }

    public function test_two_uploads_with_same_filename_dont_collide(): void
    {
        $service = $this->app->make(R2MediaService::class);
        $first   = UploadedFile::fake()->image('avatar.png', 200, 200);
        $second  = UploadedFile::fake()->image('avatar.png', 200, 200);

        $r1 = $service->uploadAvatar(1, $first);
        $r2 = $service->uploadAvatar(1, $second);

        $this->assertNotSame($r1->key, $r2->key, 'Two uploads must NOT overwrite each other');
        Storage::disk('r2')->assertExists($r1->key);
        Storage::disk('r2')->assertExists($r2->key);
    }

    public function test_two_users_uploading_to_same_resource_are_isolated(): void
    {
        $service = $this->app->make(R2MediaService::class);
        $file    = UploadedFile::fake()->image('photo.jpg', 800, 600);

        // User 100 photographer for event 50
        $a = $service->uploadEventPhoto(100, 50, $file);
        // User 200 photographer for the same event id 50 (different photographer)
        $b = $service->uploadEventPhoto(200, 50, $file);

        $this->assertStringContainsString('user_100/event_50/', $a->key);
        $this->assertStringContainsString('user_200/event_50/', $b->key);
        // Different parents — no cross-contamination possible
        $this->assertNotEquals(
            substr($a->key, 0, strrpos($a->key, '/')),
            substr($b->key, 0, strrpos($b->key, '/')),
        );
    }

    public function test_delete_removes_object_from_r2(): void
    {
        $service = $this->app->make(R2MediaService::class);
        $file    = UploadedFile::fake()->image('temp.jpg', 100, 100);

        $upload = $service->uploadEventPhoto(1, 1, $file);
        Storage::disk('r2')->assertExists($upload->key);

        $this->assertTrue($service->delete($upload->key));
        Storage::disk('r2')->assertMissing($upload->key);
    }

    public function test_delete_resource_wipes_only_that_resource_folder(): void
    {
        $service = $this->app->make(R2MediaService::class);

        // Two photos in event 99, one photo in event 100 — all by photographer 1
        $service->uploadEventPhoto(1, 99, UploadedFile::fake()->image('a.jpg'));
        $service->uploadEventPhoto(1, 99, UploadedFile::fake()->image('b.jpg'));
        $survivor = $service->uploadEventPhoto(1, 100, UploadedFile::fake()->image('c.jpg'));

        $deleted = $service->deleteResource(MediaContext::make('events', 'photos', 1, 99));

        $this->assertSame(2, $deleted);
        Storage::disk('r2')->assertExists($survivor->key); // event 100 untouched
    }

    public function test_delete_user_wipes_files_across_systems(): void
    {
        $service = $this->app->make(R2MediaService::class);

        $service->uploadAvatar(7, UploadedFile::fake()->image('me.jpg'));
        $service->uploadEventPhoto(7, 1, UploadedFile::fake()->image('p.jpg'));

        // Different user — should NOT be deleted
        $other = $service->uploadAvatar(8, UploadedFile::fake()->image('other.jpg'));

        $deleted = $service->deleteUser(7);
        $this->assertGreaterThanOrEqual(2, $deleted);

        Storage::disk('r2')->assertExists($other->key);
    }

    public function test_uploads_under_same_user_for_different_events_dont_share_directory(): void
    {
        $service = $this->app->make(R2MediaService::class);

        $a = $service->uploadEventPhoto(45, 1, UploadedFile::fake()->image('a.jpg'));
        $b = $service->uploadEventPhoto(45, 2, UploadedFile::fake()->image('b.jpg'));

        $this->assertStringContainsString('event_1/', $a->key);
        $this->assertStringContainsString('event_2/', $b->key);
        $this->assertNotSame(dirname($a->key), dirname($b->key));
    }

    public function test_r2_only_mode_throws_when_bucket_not_configured(): void
    {
        config(['media.r2_only' => true]);
        config(['filesystems.disks.r2.bucket' => null]);

        $service = $this->app->make(R2MediaService::class);
        $file    = UploadedFile::fake()->image('a.jpg');

        $this->expectException(StorageNotConfiguredException::class);
        $service->uploadAvatar(1, $file);
    }
}
