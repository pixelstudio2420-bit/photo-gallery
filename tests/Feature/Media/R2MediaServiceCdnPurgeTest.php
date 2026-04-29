<?php

namespace Tests\Feature\Media;

use App\Jobs\PurgeR2CdnCacheJob;
use App\Services\Media\MediaContext;
use App\Services\Media\R2MediaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Verifies that delete / deleteResource / deleteUser dispatch CDN purges
 * via the queue (rather than blocking the request).
 *
 * No RefreshDatabase — this test only exercises in-memory R2 (fake) +
 * the queue (fake). The pre-existing migration issue in the test DB
 * (CHECK constraint mismatch on payment_methods.method_type when running
 * Postgres-specific DDL on SQLite) is orthogonal to this work.
 */
class R2MediaServiceCdnPurgeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('r2');
        config(['media.disk' => 'r2']);
        config(['media.r2_only' => false]);
    }

    public function test_delete_dispatches_a_cdn_purge_job(): void
    {
        Queue::fake();

        $service = $this->app->make(R2MediaService::class);
        $upload  = $service->uploadAvatar(1, UploadedFile::fake()->image('a.png'));

        $service->delete($upload->key);

        Queue::assertPushed(PurgeR2CdnCacheJob::class, function ($job) use ($upload) {
            return in_array($upload->key, $job->keys, true);
        });
    }

    public function test_delete_resource_dispatches_one_purge_for_the_whole_folder(): void
    {
        Queue::fake();

        $service = $this->app->make(R2MediaService::class);
        $service->uploadEventPhoto(1, 99, UploadedFile::fake()->image('a.jpg'));
        $service->uploadEventPhoto(1, 99, UploadedFile::fake()->image('b.jpg'));
        $service->uploadEventPhoto(1, 99, UploadedFile::fake()->image('c.jpg'));

        $deleted = $service->deleteResource(MediaContext::make('events', 'photos', 1, 99));

        $this->assertSame(3, $deleted);

        // Exactly one purge job should have been dispatched even though we
        // deleted 3 objects — the job receives all 3 keys at once.
        Queue::assertPushed(PurgeR2CdnCacheJob::class, function ($job) {
            return count($job->keys) === 3;
        });
        Queue::assertPushed(PurgeR2CdnCacheJob::class, 1);
    }

    public function test_delete_user_dispatches_purge_for_all_keys_across_systems(): void
    {
        Queue::fake();

        $service = $this->app->make(R2MediaService::class);
        $service->uploadAvatar(7, UploadedFile::fake()->image('me.jpg'));
        $service->uploadEventPhoto(7, 1, UploadedFile::fake()->image('event.jpg'));

        $service->deleteUser(7);

        Queue::assertPushed(PurgeR2CdnCacheJob::class, function ($job) {
            return count($job->keys) >= 2;
        });
    }

    public function test_skip_cdn_purge_config_disables_dispatching(): void
    {
        Queue::fake();
        config(['media.skip_cdn_purge' => true]);

        $service = $this->app->make(R2MediaService::class);
        $upload  = $service->uploadAvatar(1, UploadedFile::fake()->image('a.png'));

        $service->delete($upload->key);

        Queue::assertNothingPushed();
    }

    public function test_delete_with_no_files_does_not_dispatch(): void
    {
        Queue::fake();

        $service = $this->app->make(R2MediaService::class);
        $service->deleteResource(MediaContext::make('events', 'photos', 1, 999_999_999));

        Queue::assertNothingPushed();
    }
}
