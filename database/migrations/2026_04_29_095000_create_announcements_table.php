<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Announcements — short news/promo posts targeted at photographer or
 * customer audiences (or both). Distinct from blog_posts because:
 *
 *   • Lifecycle is short (week-long campaign vs months-long evergreen).
 *   • Audience is segmented (photographer-only vs customer-only vs all)
 *     so dashboards can render the right ones without category filtering.
 *   • Priority surfaces hot items at the top of the dashboard banner.
 *   • Schedule window (starts_at / ends_at) lets admins queue future
 *     announcements without flipping a draft toggle at exact dates.
 *
 * Why a separate table over blog_posts + audience tag:
 *   blog_posts is heavy (SEO fields, AI-gen flags, reading-time, focus
 *   keywords). Announcements are thin and high-volume. Mixing them
 *   bloats blog queries and forces every blog admin tool to special-
 *   case "is this an announcement?" logic. Two tables, clean separation.
 *
 * Soft-delete enabled so admin "undo" is one click and the audit trail
 * survives. Indexed on (audience, status, starts_at) since that's the
 * shape of every list query.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $t) {
            $t->id();
            $t->string('title', 200);
            $t->string('slug', 220)->unique();
            $t->string('excerpt', 300)->nullable();
            $t->text('body')->nullable();   // rich HTML
            $t->string('cover_image_path', 500)->nullable();   // R2 key

            // Audience routing: 'photographer' | 'customer' | 'all'.
            // Photographer-only banners live on /photographer dashboard;
            // customer-only on the public events/dashboard; 'all' shows
            // on both. Indexed alone because filter queries are
            // `WHERE audience IN (...)` first, status second.
            $t->string('audience', 20)->default('all');

            // Visual / behavioural priority.
            $t->string('priority', 10)->default('normal');     // low | normal | high
            // Optional CTA on the announcement card.
            $t->string('cta_label', 60)->nullable();
            $t->string('cta_url', 500)->nullable();

            // Lifecycle:
            //   draft     — invisible to users, admin work-in-progress
            //   published — visible to (audience) users when within the
            //               starts_at/ends_at window
            //   archived  — past its time, kept for audit but hidden
            $t->string('status', 20)->default('draft');

            // Schedule window. NULL starts_at = "publish immediately on
            // status flip"; NULL ends_at = "no auto-archive".
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();

            // Pinning — stick this to the top of its dashboard regardless
            // of recency.
            $t->boolean('is_pinned')->default(false);

            // Author / audit.
            $t->unsignedInteger('created_by_admin_id')->nullable();
            $t->unsignedInteger('updated_by_admin_id')->nullable();

            // View counter — bumped lazily on detail-page render. Helps
            // admins see which announcements actually got read.
            $t->unsignedInteger('view_count')->default(0);

            $t->timestamps();
            $t->softDeletes();

            $t->index(['audience', 'status', 'starts_at'], 'announcements_audience_status_idx');
            $t->index('status');
            $t->index('is_pinned');
        });

        // Multiple images per announcement. Cover image lives on the row
        // itself (cover_image_path); attachments are inline body images
        // and gallery shots. Sort order lets admin reorder via drag-drop.
        Schema::create('announcement_attachments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $t->string('image_path', 500);   // R2 key
            $t->string('caption', 200)->nullable();
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamps();

            $t->index(['announcement_id', 'sort_order'], 'announcements_attachments_sort_idx');
        });

        // Postgres-only constraint: starts_at must be <= ends_at when both
        // are set. Defence in depth — admins fat-fingering the date on
        // the form would otherwise create an announcement that never
        // shows (window closed before it opened).
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE announcements
                ADD CONSTRAINT announcements_window_chk
                CHECK (starts_at IS NULL OR ends_at IS NULL OR starts_at <= ends_at)
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE announcements DROP CONSTRAINT IF EXISTS announcements_window_chk');
        }
        Schema::dropIfExists('announcement_attachments');
        Schema::dropIfExists('announcements');
    }
};
