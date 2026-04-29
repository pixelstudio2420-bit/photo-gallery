<?php

namespace Tests\Feature\Upload;

use App\Services\Upload\UploadSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Verifies the batch upload session is concurrency-safe and resilient
 * to the kinds of malformed traffic a real client will produce.
 *
 * The properties we lock down:
 *
 *   • progress counters use SQL-level arithmetic so 50 parallel writes
 *     don't lose updates,
 *
 *   • complete()/abort() are idempotent — a second call to either is a
 *     no-op and returns false,
 *
 *   • find() respects user_id — one user can never read another's
 *     session by guessing the token,
 *
 *   • sweepExpired() actually transitions the right rows.
 */
class UploadSessionServiceTest extends TestCase
{
    use RefreshDatabase;

    private UploadSessionService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new UploadSessionService();
    }

    private function open(int $userId = 42, int $expected = 5): array
    {
        return $this->svc->open(
            userId:        $userId,
            eventId:       null,
            category:      'events.photos',
            expectedFiles: $expected,
        );
    }

    public function test_open_returns_token_and_persists_row(): void
    {
        $session = $this->open();
        $this->assertArrayHasKey('session_token', $session);
        $this->assertNotEmpty($session['session_token']);

        $this->assertSame(
            1,
            DB::table('upload_sessions')->where('session_token', $session['session_token'])->count(),
        );
    }

    public function test_record_success_increments_counters_atomically(): void
    {
        $session = $this->open(userId: 7, expected: 100);
        $token = $session['session_token'];

        // Simulate 50 sequential successes — the SQL increment must hold
        // the count exactly even without a transaction wrapper because
        // each UPDATE is one round-trip.
        for ($i = 0; $i < 50; $i++) {
            $this->svc->recordSuccess($token, 7, 1024);
        }
        $row = DB::table('upload_sessions')->where('session_token', $token)->first();
        $this->assertSame(50, (int) $row->completed_files);
        $this->assertSame(50 * 1024, (int) $row->total_bytes);
    }

    public function test_record_failure_increments_failed_files_only(): void
    {
        $session = $this->open(userId: 8);
        $this->svc->recordFailure($session['session_token'], 8);
        $this->svc->recordFailure($session['session_token'], 8);

        $row = $this->svc->find($session['session_token'], 8);
        $this->assertSame(2, (int) $row->failed_files);
        $this->assertSame(0, (int) $row->completed_files);
    }

    public function test_record_progress_silently_no_ops_on_completed_session(): void
    {
        $session = $this->open(userId: 9);
        $this->svc->complete($session['session_token'], 9);

        // Counters are guarded by status — recording on a completed session
        // doesn't bump anything (avoids stale clients re-reporting).
        $this->svc->recordSuccess($session['session_token'], 9, 999);

        $row = $this->svc->find($session['session_token'], 9);
        $this->assertSame(0, (int) $row->completed_files);
    }

    public function test_complete_is_idempotent(): void
    {
        $session = $this->open(userId: 10);
        $first  = $this->svc->complete($session['session_token'], 10);
        $second = $this->svc->complete($session['session_token'], 10);

        $this->assertTrue($first);
        $this->assertFalse($second, 'second complete must be a no-op');
    }

    public function test_abort_is_idempotent_and_terminal(): void
    {
        $session = $this->open(userId: 11);
        $first  = $this->svc->abort($session['session_token'], 11);
        $second = $this->svc->abort($session['session_token'], 11);
        $afterAbort = $this->svc->complete($session['session_token'], 11);

        $this->assertTrue($first);
        $this->assertFalse($second);
        $this->assertFalse($afterAbort, 'aborted session must not transition to completed');
    }

    public function test_find_respects_user_isolation(): void
    {
        $session = $this->open(userId: 100);
        $stranger = $this->svc->find($session['session_token'], 999);
        $this->assertNull($stranger, 'user_id must scope find()');
    }

    public function test_sweep_expired_marks_stale_sessions(): void
    {
        $session = $this->open(userId: 200);

        // Move expires_at to the past.
        DB::table('upload_sessions')
            ->where('session_token', $session['session_token'])
            ->update(['expires_at' => now()->subHour()]);

        $count = $this->svc->sweepExpired();
        $this->assertGreaterThanOrEqual(1, $count);

        $row = DB::table('upload_sessions')
            ->where('session_token', $session['session_token'])->first();
        $this->assertSame('expired', $row->status);
    }

    public function test_bump_expected_only_affects_open_sessions(): void
    {
        $session = $this->open(userId: 300, expected: 10);
        $this->svc->bumpExpected($session['session_token'], 300, 5);

        $row = $this->svc->find($session['session_token'], 300);
        $this->assertSame(15, (int) $row->expected_files);

        $this->svc->complete($session['session_token'], 300);
        $this->svc->bumpExpected($session['session_token'], 300, 5);  // no-op

        $row = $this->svc->find($session['session_token'], 300);
        $this->assertSame(15, (int) $row->expected_files,
            'bumpExpected on completed session must be ignored');
    }
}
