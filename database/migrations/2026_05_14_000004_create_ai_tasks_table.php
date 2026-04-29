<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI task ledger.
 *
 * Every AI operation a photographer kicks off (face index, quality scan,
 * duplicate sweep, auto-tag, color enhance, smart caption generation,
 * etc.) goes through SubscriptionService::consumeAiCredits() FIRST and
 * lands a row here so we have:
 *   • Audit trail (who did what, when, on which event/photo)
 *   • Async progress display (status pending/running/done/failed)
 *   • Per-feature cost accounting (credits_used per task)
 *
 * The `kind` enum mirrors the SubscriptionPlan.ai_features keys so
 * the gating middleware can read this directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_tasks', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('photographer_id');
            $t->unsignedBigInteger('event_id')->nullable();
            $t->string('kind', 40);                  // face_search / quality_filter / duplicate_detection / auto_tagging / best_shot / color_enhance / smart_captions / video_thumbnails
            $t->enum('status', ['pending', 'running', 'done', 'failed'])->default('pending');
            $t->unsignedInteger('credits_used')->default(0);
            $t->unsignedInteger('items_processed')->default(0);
            $t->json('input_meta')->nullable();      // request payload (params, target ids)
            $t->json('result_meta')->nullable();     // output (matched ids, scores, tags, etc.)
            $t->text('error_message')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->timestamps();

            $t->index(['photographer_id', 'kind', 'status']);
            $t->index(['event_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_tasks');
    }
};
