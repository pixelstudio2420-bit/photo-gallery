<?php

namespace Tests\Feature\Upload;

use App\Models\Event;
use App\Models\PhotographerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * HTTP-level locks for the chunked / resumable upload routes.
 *
 * What we cover here
 * ------------------
 *   • auth: every endpoint refuses guests
 *   • authorisation: the ownership check on /multipart/init refuses
 *     a photographer trying to upload to someone else's event
 *   • upload session HTTP lifecycle: open → progress → status → complete
 *   • record-part / list-parts: respect user_id isolation
 *
 * What's NOT here
 * ---------------
 *   • The actual init() / signPart() / complete() S3 round-trips —
 *     those need a live R2 connection. The MultipartUploadServiceTest
 *     covers the DB state-machine without S3 calls. CI's smoke env
 *     verifies the S3 leg.
 */
class MultipartUploadControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['media.r2_only' => false]);
    }

    private function makePhotographer(): array
    {
        $user = User::create([
            'first_name'    => 'MP',
            'last_name'     => 'Tester',
            'email'         => 'mp-' . uniqid() . '@example.com',
            'password_hash' => Hash::make('password'),
            'auth_provider' => 'local',
        ]);
        $profile = PhotographerProfile::create([
            'user_id'           => $user->id,
            'photographer_code' => 'PH-' . strtoupper(substr(uniqid(), -6)),
            'display_name'      => 'MP Tester',
            'status'            => 'approved',
        ]);
        $event = Event::create([
            'photographer_id' => $profile->id,
            'name'            => 'MP Event',
            'slug'            => 'mp-' . uniqid(),
            'status'          => 'active',
            'visibility'      => 'public',
            'price_per_photo' => 20.00,
            'is_free'         => false,
            'view_count'      => 0,
        ]);
        return [$user, $profile, $event];
    }

    // ─── Auth ──────────────────────────────────────────────────────────

    public function test_guest_cannot_open_session(): void
    {
        $r = $this->postJson(route('api.uploads.session.open'), [
            'category'    => 'events.photos',
            'resource_id' => 1,
        ]);
        // Either 401 (the controller's explicit check) or 302 (auth
        // middleware redirect) — both are valid "denied" outcomes.
        $this->assertContains($r->status(), [401, 302]);
    }

    public function test_guest_cannot_init_multipart(): void
    {
        $r = $this->postJson(route('api.uploads.multipart.init'), [
            'category'    => 'events.photos',
            'resource_id' => 1,
            'filename'    => 'a.jpg',
            'mime'        => 'image/jpeg',
            'total_bytes' => 1_000_000,
        ]);
        $this->assertContains($r->status(), [401, 302]);
    }

    // ─── Authorisation: ownership check on init ─────────────────────────

    public function test_init_refuses_uploads_to_someone_elses_event(): void
    {
        [$ownerUser, $ownerProfile, $event] = $this->makePhotographer();
        [$strangerUser, ,] = $this->makePhotographer();

        $r = $this->actingAs($strangerUser)
            ->postJson(route('api.uploads.multipart.init'), [
                'category'    => 'events.photos',
                'resource_id' => $event->id,
                'filename'    => 'a.jpg',
                'mime'        => 'image/jpeg',
                'total_bytes' => 5_000_000,
            ]);
        $r->assertStatus(403);
    }

    public function test_init_refuses_unknown_category(): void
    {
        [$user] = $this->makePhotographer();
        $r = $this->actingAs($user)->postJson(route('api.uploads.multipart.init'), [
            'category'    => 'made.up.category',
            'resource_id' => 1,
            'filename'    => 'a.jpg',
            'mime'        => 'image/jpeg',
            'total_bytes' => 5_000_000,
        ]);
        $r->assertStatus(403);
    }

    // ─── Session HTTP lifecycle ─────────────────────────────────────────

    public function test_session_open_progress_status_complete_lifecycle(): void
    {
        [$user, , $event] = $this->makePhotographer();

        $open = $this->actingAs($user)->postJson(route('api.uploads.session.open'), [
            'category'       => 'events.photos',
            'resource_id'    => $event->id,
            'expected_files' => 3,
        ]);
        $open->assertStatus(201);
        $token = $open->json('session_token');
        $this->assertNotEmpty($token);

        // record success
        $progress = $this->actingAs($user)
            ->postJson(route('api.uploads.session.progress', $token), [
                'success' => true,
                'bytes'   => 1024,
            ]);
        $progress->assertStatus(200);
        $this->assertSame(1, $progress->json('completed_files'));

        // record failure
        $progress2 = $this->actingAs($user)
            ->postJson(route('api.uploads.session.progress', $token), [
                'success' => false,
            ]);
        $progress2->assertStatus(200);
        $this->assertSame(1, $progress2->json('failed_files'));

        // status check
        $status = $this->actingAs($user)
            ->getJson(route('api.uploads.session.status', $token));
        $status->assertStatus(200);
        $status->assertJsonFragment([
            'completed_files' => 1,
            'failed_files'    => 1,
            'expected_files'  => 3,
            'status'          => 'open',
        ]);

        // complete
        $complete = $this->actingAs($user)
            ->postJson(route('api.uploads.session.complete', $token));
        $complete->assertStatus(200)->assertJson(['ok' => true]);

        // status now reflects 'completed'
        $status2 = $this->actingAs($user)
            ->getJson(route('api.uploads.session.status', $token));
        $status2->assertJsonFragment(['status' => 'completed']);
    }

    public function test_session_status_refuses_other_users(): void
    {
        [$ownerUser, , $event] = $this->makePhotographer();
        [$strangerUser] = $this->makePhotographer();

        $open = $this->actingAs($ownerUser)->postJson(route('api.uploads.session.open'), [
            'category'    => 'events.photos',
            'resource_id' => $event->id,
        ]);
        $token = $open->json('session_token');

        $r = $this->actingAs($strangerUser)
            ->getJson(route('api.uploads.session.status', $token));
        $r->assertStatus(404);
    }

    // ─── record-part / list-parts: user isolation ───────────────────────

    public function test_record_part_404_for_other_users_upload(): void
    {
        [$ownerUser, , $event] = $this->makePhotographer();
        [$strangerUser] = $this->makePhotographer();

        // Seed a chunk row owned by ownerUser.
        DB::table('upload_chunks')->insert([
            'user_id'           => $ownerUser->id,
            'event_id'          => $event->id,
            'category'          => 'events.photos',
            'object_key'        => 'k',
            'upload_id'         => 'OWNER-UP',
            'original_filename' => 'a.jpg',
            'mime_type'         => 'image/jpeg',
            'total_bytes'       => 5_000_000,
            'total_parts'       => 1,
            'status'            => 'uploading',
            'parts'             => json_encode([]),
            'expires_at'        => now()->addHour(),
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $r = $this->actingAs($strangerUser)
            ->postJson(route('api.uploads.multipart.record-part'), [
                'upload_id'   => 'OWNER-UP',
                'part_number' => 1,
                'etag'        => 'fake',
                'size_bytes'  => 5_000_000,
            ]);
        $r->assertStatus(404);
    }

    public function test_list_parts_404_for_other_users_upload(): void
    {
        [$ownerUser, , $event] = $this->makePhotographer();
        [$strangerUser] = $this->makePhotographer();

        DB::table('upload_chunks')->insert([
            'user_id'           => $ownerUser->id,
            'event_id'          => $event->id,
            'category'          => 'events.photos',
            'object_key'        => 'k',
            'upload_id'         => 'OWNER-UP-LIST',
            'original_filename' => 'a.jpg',
            'mime_type'         => 'image/jpeg',
            'total_bytes'       => 5_000_000,
            'total_parts'       => 1,
            'status'            => 'uploading',
            'parts'             => json_encode([['partNumber' => 1, 'etag' => 'e']]),
            'expires_at'        => now()->addHour(),
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $r = $this->actingAs($strangerUser)
            ->getJson(route('api.uploads.multipart.parts', 'OWNER-UP-LIST'));
        $r->assertStatus(404);
    }

    // ─── Validation: total_bytes must be in range ───────────────────────

    public function test_init_rejects_oversized_uploads(): void
    {
        [$user, , $event] = $this->makePhotographer();
        $r = $this->actingAs($user)->postJson(route('api.uploads.multipart.init'), [
            'category'    => 'events.photos',
            'resource_id' => $event->id,
            'filename'    => 'big.bin',
            'mime'        => 'image/jpeg',
            'total_bytes' => 10 * 1024 * 1024 * 1024, // 10 GB > 5 GB ceiling
        ]);
        $r->assertStatus(422);
    }

    public function test_abort_is_safe_on_unknown_upload_id(): void
    {
        [$user] = $this->makePhotographer();
        $r = $this->actingAs($user)->postJson(route('api.uploads.multipart.abort'), [
            'upload_id' => 'never-was-a-real-upload',
        ]);
        // abort is best-effort — returns ok:false but never errors.
        $r->assertStatus(200);
        $this->assertFalse($r->json('ok'));
    }
}
