<?php

namespace Tests\Feature\Line;

use App\Jobs\Line\DownloadLineMediaJob;
use App\Models\AppSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Locks down the inbound-image download job.
 *
 * Properties under test:
 *
 *   • Successful download → R2 stores the bytes, line_inbound_media
 *     row is 'completed' with size + content_hash + object_key.
 *
 *   • Idempotent rerun: a second handle() with the same message_id
 *     skips work entirely.
 *
 *   • Missing token → throws (queue retries).
 *
 *   • Empty body → throws (treated as failure, not silent success).
 *
 *   • Oversized body → throws and writes failed row via failed() hook
 *     (we test the failed-hook contract directly).
 *
 *   • file extension is derived from Content-Type header, falling back
 *     to the event's content_type for unknown MIMEs.
 */
class DownloadLineMediaJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['media.r2_only' => false]);
        Storage::fake(config('media.disk', 'r2'));
        AppSetting::set('line_channel_access_token', 'test-token');
        AppSetting::flushCache();
    }

    private function lineUser(): string
    {
        return 'U' . str_repeat('a', 32);
    }

    public function test_successful_download_stores_bytes_and_writes_row(): void
    {
        $bytes = random_bytes(2048);
        Http::fake([
            '*api-data.line.me*' => Http::response($bytes, 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);

        $job = new DownloadLineMediaJob('M-OK-1', $this->lineUser(), 'image');
        $job->handle();

        $row = DB::table('line_inbound_media')->where('message_id', 'M-OK-1')->first();
        $this->assertNotNull($row);
        $this->assertSame('completed', $row->status);
        $this->assertSame(2048, (int) $row->size_bytes);
        $this->assertNotNull($row->object_key);
        $this->assertSame('image', $row->content_type);
        $this->assertSame(64, strlen((string) $row->content_hash));

        // File actually landed on the fake disk.
        Storage::disk(config('media.disk'))->assertExists($row->object_key);
    }

    public function test_idempotent_rerun_skips_redownload(): void
    {
        // Pre-seed a completed row.
        DB::table('line_inbound_media')->insert([
            'message_id'    => 'M-IDEM',
            'line_user_id'  => $this->lineUser(),
            'content_type'  => 'image',
            'object_key'    => 'pre/existing.jpg',
            'size_bytes'    => 100,
            'status'        => 'completed',
            'downloaded_at' => now(),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // The Http::fake will throw if called, so this test passes only
        // when we DO NOT call LINE.
        Http::fake(['*' => function () {
            throw new \RuntimeException('LINE should not be called for idempotent rerun');
        }]);

        $job = new DownloadLineMediaJob('M-IDEM', $this->lineUser(), 'image');
        $job->handle();   // must NOT call LINE

        $this->assertSame(
            'completed',
            DB::table('line_inbound_media')->where('message_id', 'M-IDEM')->value('status'),
        );
    }

    public function test_missing_token_throws(): void
    {
        AppSetting::set('line_channel_access_token', '');
        AppSetting::flushCache();

        $job = new DownloadLineMediaJob('M-NOK', $this->lineUser(), 'image');
        $this->expectException(\RuntimeException::class);
        $job->handle();
    }

    public function test_empty_body_is_failure(): void
    {
        Http::fake(['*api-data.line.me*' => Http::response('', 200, [
            'Content-Type' => 'image/jpeg',
        ])]);

        $job = new DownloadLineMediaJob('M-EMPTY', $this->lineUser(), 'image');
        $this->expectException(\RuntimeException::class);
        $job->handle();
    }

    public function test_failed_hook_writes_failed_row(): void
    {
        $job = new DownloadLineMediaJob('M-FAILHK', $this->lineUser(), 'image');
        $job->failed(new \RuntimeException('5xx from LINE'));

        $row = DB::table('line_inbound_media')->where('message_id', 'M-FAILHK')->first();
        $this->assertNotNull($row);
        $this->assertSame('failed', $row->status);
        $this->assertStringContainsString('5xx from LINE', $row->error);
    }

    public function test_extension_resolves_from_content_type(): void
    {
        Http::fake([
            '*api-data.line.me*' => Http::response('PNGBYTES', 200, [
                'Content-Type' => 'image/png',
            ]),
        ]);

        $job = new DownloadLineMediaJob('M-PNG', $this->lineUser(), 'image');
        $job->handle();

        $key = DB::table('line_inbound_media')->where('message_id', 'M-PNG')->value('object_key');
        $this->assertStringEndsWith('.png', $key,
            'object_key extension must reflect the Content-Type header');
    }

    public function test_extension_falls_back_to_content_type_when_header_missing(): void
    {
        Http::fake([
            '*api-data.line.me*' => Http::response('FAKEBYTES', 200, []),  // no header
        ]);

        $job = new DownloadLineMediaJob('M-NOMIME', $this->lineUser(), 'video');
        $job->handle();

        $key = DB::table('line_inbound_media')->where('message_id', 'M-NOMIME')->value('object_key');
        $this->assertStringEndsWith('.mp4', $key,
            'when MIME header is missing, fall back to event-level content_type');
    }
}
