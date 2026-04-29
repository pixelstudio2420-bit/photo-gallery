<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * line_inbound_media — stores metadata about images/videos/files users
 * sent to our LINE OA after DownloadLineMediaJob saved them to R2.
 *
 * Why a separate table from line_inbound_events
 * ---------------------------------------------
 * line_inbound_events captures the wire-level event (one row per
 * webhook event). This table captures the storage-level result of the
 * deferred download (R2 path + hash). Joining the two gives a complete
 * "user sent us a photo" picture, but they have different lifecycles:
 *
 *   • The event is processed in <100ms inside the webhook.
 *   • The media download runs in a queue, can take seconds, can
 *     retry, can fail permanently.
 *
 * Putting both into one row would force one table to carry two
 * different status state machines. Two tables, one foreign-key
 * concept (message_id), is cleaner.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('line_inbound_media')) return;

        Schema::create('line_inbound_media', function (Blueprint $t) {
            $t->id();
            // LINE message id — unique per OA, matches
            // line_inbound_events.message_id. Constrained as a
            // primary-key-quality string here so a retry of the job
            // updates instead of inserts.
            $t->string('message_id', 64)->unique();
            $t->string('line_user_id', 64);
            // image | video | audio | file (matches the event subtype)
            $t->string('content_type', 16);
            $t->string('mime_type', 128)->nullable();
            // Where on R2 this lives (visibility: private; signed URLs
            // for read).
            $t->string('object_key', 1000)->nullable();
            $t->unsignedBigInteger('size_bytes')->default(0);
            // SHA-256 of bytes — matches event_photos.content_hash
            // shape so cross-table dedup works if we ever want it.
            $t->string('content_hash', 64)->nullable();
            // pending → completed | failed
            $t->string('status', 16)->default('pending');
            $t->string('error', 500)->nullable();
            $t->timestamp('downloaded_at')->nullable();
            $t->timestamps();

            $t->index('line_user_id');
            $t->index('status');
            $t->index('content_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('line_inbound_media');
    }
};
