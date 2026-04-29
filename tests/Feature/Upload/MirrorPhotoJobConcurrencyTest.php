<?php

namespace Tests\Feature\Upload;

use App\Jobs\MirrorPhotoJob;
use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\PhotographerProfile;
use App\Models\User;
use App\Services\StorageManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

/**
 * Locks down MirrorPhotoJob's race-safe merge.
 *
 * The bug we're guarding against: two concurrent mirror jobs on the same
 * photo each read the current `storage_mirrors` array, append their own
 * target, and write back. Without a lock, the second write wins and the
 * first job's target is dropped — the photo claims to be on, say, B2,
 * but our DB has no record of it.
 *
 * The new implementation does the heavy copy work outside the lock and
 * uses lockForUpdate() only for the merge. These tests prove the merge
 * is preserve-and-union (not last-write-wins).
 */
class MirrorPhotoJobConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makePhoto(): EventPhoto
    {
        $user = User::create([
            'first_name'    => 'Mirror',
            'last_name'     => 'Tester',
            'email'         => 'mirror-' . uniqid() . '@example.com',
            'password_hash' => Hash::make('password'),
            'auth_provider' => 'local',
        ]);
        $profile = PhotographerProfile::create([
            'user_id'           => $user->id,
            'photographer_code' => 'PH-' . strtoupper(substr(uniqid(), -6)),
            'display_name'      => 'Mirror Tester',
            'status'            => 'approved',
        ]);
        $event = Event::create([
            'photographer_id' => $profile->id,
            'name'            => 'Mirror Event',
            'slug'            => 'mirror-' . uniqid(),
            'status'          => 'active',
            'visibility'      => 'public',
            'price_per_photo' => 20.00,
            'is_free'         => false,
            'view_count'      => 0,
        ]);
        return EventPhoto::create([
            'event_id'          => $event->id,
            'uploaded_by'       => $user->id,
            'source'            => 'upload',
            'filename'          => 'm.jpg',
            'original_filename' => 'm.jpg',
            'mime_type'         => 'image/jpeg',
            'file_size'         => 1024,
            'width'             => 100,
            'height'            => 100,
            'storage_disk'      => 'r2',
            'original_path'     => 'events/photos/u1/e1/m.jpg',
            'storage_mirrors'   => [],
            'sort_order'        => 1,
            'status'            => 'active',
        ]);
    }

    public function test_concurrent_jobs_union_targets_instead_of_clobbering(): void
    {
        $photo = $this->makePhoto();

        // Simulate the post-copy state: both jobs have already done their
        // copies but neither has merged yet. The race is in the merge.
        // We pre-seed one target via a "first job already finished" UPDATE,
        // then run a second job that targets a different mirror.

        // Pre-seed mirror A (as if Job-A already merged).
        $photo->storage_mirrors = ['mirror_a'];
        $photo->save();

        // Now run Job-B with target mirror_b. The job's race-safe merge
        // must union — final state should be both mirrors.
        $storage = Mockery::mock(StorageManager::class);
        $storage->shouldReceive('primaryDriver')->andReturn('r2');
        $storage->shouldReceive('mirrorTargets')->andReturn(['mirror_b']);
        $storage->shouldReceive('copyBetweenDrivers')
            ->andReturn(true);  // pretend the copy succeeded

        $job = new MirrorPhotoJob($photo->id);
        $job->handle($storage);

        $fresh = $photo->fresh();
        $this->assertEqualsCanonicalizing(
            ['mirror_a', 'mirror_b'],
            $fresh->storage_mirrors,
            'race-safe merge must union both targets, never drop one',
        );
    }

    public function test_failed_copy_does_not_record_target(): void
    {
        $photo = $this->makePhoto();

        $storage = Mockery::mock(StorageManager::class);
        $storage->shouldReceive('primaryDriver')->andReturn('r2');
        $storage->shouldReceive('mirrorTargets')->andReturn(['mirror_x']);
        $storage->shouldReceive('copyBetweenDrivers')->andReturn(false); // copy fails

        $job = new MirrorPhotoJob($photo->id);
        $job->handle($storage);

        $fresh = $photo->fresh();
        $this->assertSame([], $fresh->storage_mirrors,
            'a target must NOT be recorded if the copy failed');
    }

    public function test_idempotent_rerun_does_not_grow_array(): void
    {
        $photo = $this->makePhoto();

        $storage = Mockery::mock(StorageManager::class);
        $storage->shouldReceive('primaryDriver')->andReturn('r2');
        $storage->shouldReceive('mirrorTargets')->andReturn(['mirror_x']);
        $storage->shouldReceive('copyBetweenDrivers')->andReturn(true);

        $job = new MirrorPhotoJob($photo->id);
        $job->handle($storage);
        $job->handle($storage);
        $job->handle($storage);

        $fresh = $photo->fresh();
        $this->assertSame(['mirror_x'], $fresh->storage_mirrors,
            'array_unique() must keep the array stable across reruns');
    }

    public function test_skips_when_target_equals_primary(): void
    {
        $photo = $this->makePhoto();

        $storage = Mockery::mock(StorageManager::class);
        $storage->shouldReceive('primaryDriver')->andReturn('r2');
        $storage->shouldReceive('mirrorTargets')->andReturn(['r2']); // self
        $storage->shouldReceive('copyBetweenDrivers')->never();

        $job = new MirrorPhotoJob($photo->id);
        $job->handle($storage);

        $fresh = $photo->fresh();
        $this->assertSame([], $fresh->storage_mirrors,
            'mirror to primary disk should be skipped, not recorded');
    }
}
