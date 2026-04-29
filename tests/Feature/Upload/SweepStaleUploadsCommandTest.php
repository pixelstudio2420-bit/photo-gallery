<?php

namespace Tests\Feature\Upload;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Verifies `php artisan uploads:sweep-stale` works end-to-end on a
 * pre-seeded set of expired sessions and chunks.
 */
class SweepStaleUploadsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['media.r2_only' => false]);
    }

    public function test_command_aborts_expired_chunks_and_sessions(): void
    {
        // Seed: one chunk past its expires_at, one session past its
        // expires_at, plus one of each that's still fresh (control
        // group — they must NOT be touched).
        DB::table('upload_chunks')->insert([
            // expired
            [
                'user_id' => 1, 'event_id' => 1, 'category' => 'events.photos',
                'object_key' => 'old/k', 'upload_id' => 'UP-OLD',
                'original_filename' => 'x.jpg', 'mime_type' => 'image/jpeg',
                'total_bytes' => 1, 'total_parts' => 1,
                'status' => 'uploading', 'parts' => json_encode([]),
                'expires_at' => now()->subDay(),
                'created_at' => now(), 'updated_at' => now(),
            ],
            // fresh control
            [
                'user_id' => 1, 'event_id' => 1, 'category' => 'events.photos',
                'object_key' => 'fresh/k', 'upload_id' => 'UP-FRESH',
                'original_filename' => 'y.jpg', 'mime_type' => 'image/jpeg',
                'total_bytes' => 1, 'total_parts' => 1,
                'status' => 'uploading', 'parts' => json_encode([]),
                'expires_at' => now()->addHour(),
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        DB::table('upload_sessions')->insert([
            // expired
            [
                'session_token' => '11111111-1111-1111-1111-111111111111',
                'user_id' => 1, 'category' => 'events.photos',
                'status' => 'open', 'expected_files' => 1,
                'completed_files' => 0, 'failed_files' => 0,
                'total_bytes' => 0, 'expires_at' => now()->subDay(),
                'created_at' => now(), 'updated_at' => now(),
            ],
            // fresh
            [
                'session_token' => '22222222-2222-2222-2222-222222222222',
                'user_id' => 1, 'category' => 'events.photos',
                'status' => 'open', 'expected_files' => 1,
                'completed_files' => 0, 'failed_files' => 0,
                'total_bytes' => 0, 'expires_at' => now()->addHour(),
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        $this->artisan('uploads:sweep-stale')->assertExitCode(0);

        // Expired chunk → 'expired'
        $oldChunk   = DB::table('upload_chunks')->where('upload_id', 'UP-OLD')->first();
        $freshChunk = DB::table('upload_chunks')->where('upload_id', 'UP-FRESH')->first();
        $this->assertSame('expired', $oldChunk->status);
        $this->assertSame('uploading', $freshChunk->status, 'fresh chunk must not be touched');

        // Expired session → 'expired'
        $oldSession   = DB::table('upload_sessions')
            ->where('session_token', '11111111-1111-1111-1111-111111111111')->first();
        $freshSession = DB::table('upload_sessions')
            ->where('session_token', '22222222-2222-2222-2222-222222222222')->first();
        $this->assertSame('expired', $oldSession->status);
        $this->assertSame('open', $freshSession->status, 'fresh session must not be touched');
    }

    public function test_dry_run_does_not_change_anything(): void
    {
        DB::table('upload_chunks')->insert([
            'user_id' => 1, 'event_id' => 1, 'category' => 'events.photos',
            'object_key' => 'old/k', 'upload_id' => 'UP-DRY',
            'original_filename' => 'x.jpg', 'mime_type' => 'image/jpeg',
            'total_bytes' => 1, 'total_parts' => 1,
            'status' => 'uploading', 'parts' => json_encode([]),
            'expires_at' => now()->subDay(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('uploads:sweep-stale --dry-run')->assertExitCode(0);

        $row = DB::table('upload_chunks')->where('upload_id', 'UP-DRY')->first();
        $this->assertSame('uploading', $row->status, 'dry-run must NOT change row state');
    }
}
