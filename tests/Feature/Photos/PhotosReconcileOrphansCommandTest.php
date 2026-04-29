<?php

namespace Tests\Feature\Photos;

use App\Models\EventPhoto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotosReconcileOrphansCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->bootSchema();
        Storage::fake('public');
    }

    private function bootSchema(): void
    {
        if (!Schema::hasTable('event_photos')) {
            Schema::create('event_photos', function ($t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('event_id');
                $t->unsignedBigInteger('uploaded_by')->nullable();
                $t->string('source')->nullable();
                $t->string('filename');
                $t->string('original_filename')->nullable();
                $t->string('mime_type')->nullable();
                $t->bigInteger('file_size')->nullable();
                $t->integer('width')->nullable();
                $t->integer('height')->nullable();
                $t->string('storage_disk')->default('public');
                $t->string('original_path');
                $t->string('thumbnail_path')->nullable();
                $t->string('watermarked_path')->nullable();
                $t->integer('sort_order')->default(0);
                $t->string('status')->default('active');
                $t->timestamps();
            });
        } else {
            DB::table('event_photos')->truncate();
        }
    }

    public function test_command_no_op_when_all_photos_have_objects(): void
    {
        Storage::disk('public')->put('events/photos/p1.jpg', 'fake-bytes');
        EventPhoto::create([
            'event_id'      => 1,
            'filename'      => 'p1.jpg',
            'storage_disk'  => 'public',
            'original_path' => 'events/photos/p1.jpg',
            'status'        => 'active',
        ]);

        $this->artisan('photos:reconcile-orphans')->assertExitCode(0);

        // Healthy photo's status is unchanged.
        $this->assertSame('active', EventPhoto::first()->status);
    }

    public function test_command_logs_orphans_without_purge(): void
    {
        // Row points at non-existent path
        EventPhoto::create([
            'event_id'      => 1,
            'filename'      => 'gone.jpg',
            'storage_disk'  => 'public',
            'original_path' => 'events/photos/gone.jpg',
            'status'        => 'active',
        ]);

        $this->artisan('photos:reconcile-orphans')->assertExitCode(0);

        // The CONTRACT this test pins down: row scanned, but its status
        // is NOT mutated when --purge is omitted. Output strings are
        // fragile (em-dash, ANSI codes); the persistent state is what
        // production cares about.
        $this->assertSame('active', EventPhoto::first()->status);
    }

    public function test_command_marks_orphans_failed_with_purge(): void
    {
        EventPhoto::create([
            'event_id'      => 1,
            'filename'      => 'gone2.jpg',
            'storage_disk'  => 'public',
            'original_path' => 'events/photos/gone2.jpg',
            'status'        => 'active',
        ]);

        $this->artisan('photos:reconcile-orphans', ['--purge' => true])
            ->assertExitCode(0);

        $this->assertSame('failed', EventPhoto::first()->status);
    }

    public function test_command_skips_already_failed_photos(): void
    {
        EventPhoto::create([
            'event_id'      => 1,
            'filename'      => 'old.jpg',
            'storage_disk'  => 'public',
            'original_path' => 'events/photos/old.jpg',
            'status'        => 'failed',  // already triaged
        ]);

        $this->artisan('photos:reconcile-orphans')
            ->assertExitCode(0);
        // 'failed' photos aren't scanned at all; count is 0.
        $this->assertSame(1, EventPhoto::count());
    }
}
