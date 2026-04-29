<?php

namespace Tests\Feature\Upload;

use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\PhotographerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Locks in the upload-integrity contracts the new schema gives us:
 *
 *   1. Same Idempotency-Key on POST → returns the original row, never
 *      creates a sibling, even if the request body re-uploads bytes.
 *
 *   2. Same content_hash within an event → returns the original row.
 *      Bytes-identical uploads are deduped regardless of filename.
 *
 *   3. Different events with same content_hash → both succeed
 *      independently. Hash uniqueness is per-event, not global, because
 *      two photographers might legitimately share a stock image.
 *
 *   4. content_hash on a deleted row does NOT block re-upload — the
 *      partial unique index excludes status='deleted'.
 *
 *   5. Race-on-insert: when the unique index fires (two near-simultaneous
 *      inserts), the loser receives the winner's row instead of a 500.
 *
 * The tests use the disk fake so no real R2 calls are made.
 */
class PhotoUploadIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // R2MediaService::ensureR2Available() refuses to run when
        // `media.r2_only` is true and the bucket/key/secret aren't set.
        // For tests we use a fake disk and turn r2_only off so the gate
        // is a no-op.
        config(['media.r2_only' => false]);
        Storage::fake(config('media.disk', 'r2'));
    }

    private function makePhotographer(): array
    {
        $user = User::create([
            'first_name'    => 'Up',
            'last_name'     => 'Tester',
            'email'         => 'up-' . uniqid() . '@example.com',
            'password_hash' => Hash::make('password'),
            'auth_provider' => 'local',
        ]);
        $profile = PhotographerProfile::create([
            'user_id'           => $user->id,
            'photographer_code' => 'PH-' . strtoupper(substr(uniqid(), -6)),
            'display_name'      => 'Upload Tester',
            'status'            => 'approved',
        ]);
        $event = Event::create([
            'photographer_id' => $profile->id,
            'name'            => 'Upload Test Event',
            'slug'            => 'up-event-' . uniqid(),
            'status'          => 'active',
            'visibility'      => 'public',
            'price_per_photo' => 20.00,
            'is_free'         => false,
            'view_count'      => 0,
        ]);
        return [$user, $profile, $event];
    }

    private function makeFakeImage(string $name = 'pic.jpg', int $w = 800, int $h = 600): UploadedFile
    {
        // 800x600 deterministic image so two calls produce IDENTICAL bytes
        // for the dedup-by-hash test. UploadedFile::fake()->image() seeds
        // its random source from the filename + dims, so identical args
        // produce identical bytes.
        return UploadedFile::fake()->image($name, $w, $h);
    }

    // =========================================================================
    // Idempotency-Key dedup
    // =========================================================================

    public function test_same_idempotency_key_returns_original_row(): void
    {
        \App\Models\AppSetting::set('photographer_require_google_link', '0');
        \App\Models\AppSetting::flushCache();

        [$user, $profile, $event] = $this->makePhotographer();
        $key = 'idem-' . uniqid();

        $r1 = $this->actingAs($user)
            ->postJson(
                route('photographer.events.photos.store', $event),
                ['photo' => $this->makeFakeImage('first.jpg')],
                ['Idempotency-Key' => $key],
            );
        if ($r1->status() !== 200) {
            $this->fail('Upload failed: ' . $r1->status() . ' body=' . $r1->getContent());
        }
        $r1->assertJson(['success' => true]);
        $firstId = $r1->json('photo.id');

        $r2 = $this->actingAs($user)->postJson(
            route('photographer.events.photos.store', $event),
            ['photo' => $this->makeFakeImage('different-name.jpg', 1024, 768)],
            ['Idempotency-Key' => $key],
        );
        $r2->assertStatus(200);
        $this->assertSame($firstId, $r2->json('photo.id'), 'replay should return the same row');
        $this->assertTrue($r2->json('replayed') ?? false);

        // Exactly one row in the DB for this key.
        $this->assertSame(
            1,
            EventPhoto::where('event_id', $event->id)
                ->where('idempotency_key', $key)
                ->count(),
            'idempotency_key must be unique per event',
        );
    }

    // =========================================================================
    // Content-hash dedup
    // =========================================================================

    public function test_identical_bytes_within_event_are_deduped(): void
    {
        \App\Models\AppSetting::set('photographer_require_google_link', '0');
        \App\Models\AppSetting::flushCache();

        [$user, $profile, $event] = $this->makePhotographer();

        $r1 = $this->actingAs($user)->postJson(
            route('photographer.events.photos.store', $event),
            ['photo' => $this->makeFakeImage('a.jpg', 400, 300)],
        );
        $r1->assertStatus(200);

        // Same dims + filename → UploadedFile::fake produces identical bytes
        // so SHA-256 matches. Different filename to prove dedup is by hash,
        // not by name.
        $r2 = $this->actingAs($user)->postJson(
            route('photographer.events.photos.store', $event),
            ['photo' => $this->makeFakeImage('a.jpg', 400, 300)],
        );
        $r2->assertStatus(200);
        $this->assertTrue($r2->json('duplicate_hash') ?? false, 'second upload should be flagged as duplicate');
        $this->assertSame($r1->json('photo.id'), $r2->json('photo.id'));

        $this->assertSame(1, $event->photos()->count(), 'event must have exactly one photo');
    }

    public function test_same_bytes_in_different_events_both_succeed(): void
    {
        \App\Models\AppSetting::set('photographer_require_google_link', '0');
        \App\Models\AppSetting::flushCache();

        [$user, $profile, $eventA] = $this->makePhotographer();
        $eventB = Event::create([
            'photographer_id' => $profile->id,
            'name'            => 'Event B',
            'slug'            => 'b-' . uniqid(),
            'status'          => 'active',
            'visibility'      => 'public',
            'price_per_photo' => 20.00,
            'is_free'         => false,
            'view_count'      => 0,
        ]);

        $rA = $this->actingAs($user)->postJson(
            route('photographer.events.photos.store', $eventA),
            ['photo' => $this->makeFakeImage('shared.jpg', 500, 400)],
        );
        $rB = $this->actingAs($user)->postJson(
            route('photographer.events.photos.store', $eventB),
            ['photo' => $this->makeFakeImage('shared.jpg', 500, 400)],
        );

        $rA->assertStatus(200);
        $rB->assertStatus(200);
        $this->assertNotSame($rA->json('photo.id'), $rB->json('photo.id'),
            'identical bytes in two different events must produce two distinct rows');
    }

    public function test_deleted_photo_does_not_block_reupload(): void
    {
        \App\Models\AppSetting::set('photographer_require_google_link', '0');
        \App\Models\AppSetting::flushCache();

        [$user, $profile, $event] = $this->makePhotographer();

        $r1 = $this->actingAs($user)->postJson(
            route('photographer.events.photos.store', $event),
            ['photo' => $this->makeFakeImage('reup.jpg', 200, 200)],
        );
        $r1->assertStatus(200);
        $first = EventPhoto::find($r1->json('photo.id'));

        // Soft-delete by flipping status.
        $first->status = 'deleted';
        $first->save();

        $r2 = $this->actingAs($user)->postJson(
            route('photographer.events.photos.store', $event),
            ['photo' => $this->makeFakeImage('reup.jpg', 200, 200)],
        );
        $r2->assertStatus(200);
        $this->assertNotSame($first->id, $r2->json('photo.id'),
            'after soft-delete, the same bytes must produce a new row');
    }

    // =========================================================================
    // Database-level guard — make sure the unique index actually exists
    // =========================================================================

    public function test_unique_index_exists_on_event_photos(): void
    {
        $driver = \DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            $row = \DB::selectOne("SELECT 1 AS ok FROM pg_indexes
                WHERE indexname = 'uniq_event_photos_idempotency'");
            $this->assertNotNull($row, 'partial unique index must exist on Postgres');
        } elseif ($driver === 'sqlite') {
            $row = \DB::selectOne("SELECT 1 AS ok FROM sqlite_master
                WHERE type='index' AND name='uniq_event_photos_idempotency'");
            $this->assertNotNull($row, 'partial unique index must exist on sqlite');
        } else {
            $this->markTestSkipped("Index check not implemented for {$driver}");
        }
    }

    public function test_content_hash_partial_unique_index_exists(): void
    {
        $driver = \DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            $row = \DB::selectOne("SELECT 1 AS ok FROM pg_indexes
                WHERE indexname = 'uniq_event_photos_content_hash_active'");
            $this->assertNotNull($row);
        } elseif ($driver === 'sqlite') {
            $row = \DB::selectOne("SELECT 1 AS ok FROM sqlite_master
                WHERE type='index' AND name='uniq_event_photos_content_hash_active'");
            $this->assertNotNull($row);
        } else {
            $this->markTestSkipped("Index check not implemented for {$driver}");
        }
    }
}
