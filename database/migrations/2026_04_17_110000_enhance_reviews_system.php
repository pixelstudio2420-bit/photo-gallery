<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Add new columns to existing reviews table
        Schema::table('reviews', function (Blueprint $table) {
            if (!Schema::hasColumn('reviews', 'photographer_reply')) {
                $table->text('photographer_reply')->nullable()->after('admin_reply_at');
            }
            if (!Schema::hasColumn('reviews', 'photographer_reply_at')) {
                $table->timestamp('photographer_reply_at')->nullable()->after('photographer_reply');
            }
            if (!Schema::hasColumn('reviews', 'is_flagged')) {
                $table->boolean('is_flagged')->default(false)->after('is_visible');
            }
            if (!Schema::hasColumn('reviews', 'flag_reason')) {
                $table->string('flag_reason', 255)->nullable()->after('is_flagged');
            }
            if (!Schema::hasColumn('reviews', 'helpful_count')) {
                $table->unsignedInteger('helpful_count')->default(0)->after('flag_reason');
            }
            if (!Schema::hasColumn('reviews', 'report_count')) {
                $table->unsignedInteger('report_count')->default(0)->after('helpful_count');
            }
            if (!Schema::hasColumn('reviews', 'is_verified_purchase')) {
                $table->boolean('is_verified_purchase')->default(true)->after('report_count');
            }
            if (!Schema::hasColumn('reviews', 'images')) {
                $table->json('images')->nullable()->after('is_verified_purchase');
            }
            if (!Schema::hasColumn('reviews', 'status')) {
                $table->enum('status', ['pending', 'approved', 'hidden', 'rejected'])->default('approved')->after('images');
            }
        });

        // Review helpful votes — tracks which users found reviews helpful
        Schema::create('review_helpful_votes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('review_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['review_id', 'user_id']);
            $table->index('review_id');
            $table->index('user_id');
        });

        // Review reports — users flag inappropriate reviews
        Schema::create('review_reports', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('review_id');
            $table->unsignedBigInteger('user_id');
            $table->enum('reason', [
                'spam',           // สแปม / ซ้ำๆ
                'offensive',      // ข้อความไม่เหมาะสม
                'fake',           // รีวิวปลอม
                'irrelevant',     // ไม่เกี่ยวข้อง
                'private_info',   // มีข้อมูลส่วนตัว
                'other',
            ]);
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'reviewed', 'dismissed'])->default('pending');
            $table->unsignedBigInteger('resolved_by_admin_id')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['review_id', 'status']);
            $table->index('status');
        });

        // Add indexes to reviews for performance
        Schema::table('reviews', function (Blueprint $table) {
            if (!$this->indexExists('reviews', 'reviews_status_visible_idx')) {
                $table->index(['status', 'is_visible'], 'reviews_status_visible_idx');
            }
            if (!$this->indexExists('reviews', 'reviews_event_visible_idx')) {
                $table->index(['event_id', 'is_visible'], 'reviews_event_visible_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_reports');
        Schema::dropIfExists('review_helpful_votes');

        Schema::table('reviews', function (Blueprint $table) {
            $columns = [
                'photographer_reply', 'photographer_reply_at',
                'is_flagged', 'flag_reason', 'helpful_count', 'report_count',
                'is_verified_purchase', 'images', 'status',
            ];
            foreach ($columns as $col) {
                if (Schema::hasColumn('reviews', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    /**
     * Check if an index exists on a table — driver-agnostic.
     * Uses Doctrine Schema Manager via Laravel's Schema facade so it works
     * on MySQL, MariaDB, and PostgreSQL without raw SQL.
     */
    private function indexExists(string $table, string $index): bool
    {
        try {
            $driver = \DB::connection()->getDriverName();
            if ($driver === 'pgsql') {
                $result = \DB::select(
                    "SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?",
                    [$table, $index]
                );
                return !empty($result);
            }
            // MySQL / MariaDB
            $result = \DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]);
            return !empty($result);
        } catch (\Throwable $e) {
            return false;
        }
    }
};
