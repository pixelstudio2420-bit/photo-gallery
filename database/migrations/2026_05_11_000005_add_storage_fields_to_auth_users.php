<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Denormalised consumer-storage pointers cached on auth_users so hot-path
 * quota / plan-gating middleware can skip a join on every request.
 *
 * - `storage_used_bytes` / `storage_quota_bytes` — cached usage + plan cap
 * - `current_storage_sub_id` — FK to user_storage_subscriptions; null = free
 * - `storage_plan_code` / `storage_plan_status` — mirror of the sub row
 * - `storage_renews_at` — next billing date, surfaced in dashboard
 *
 * UserStorageService owns writes to these columns — don't touch them from
 * anywhere else or they'll drift from the source of truth.
 *
 * `storage_quota_bytes` defaults to 5 GB (the Free plan cap) so existing
 * users and new signups automatically get a usable free tier without
 * needing an active row in user_storage_subscriptions.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('auth_users')) {
            return;
        }

        Schema::table('auth_users', function (Blueprint $t) {
            if (!Schema::hasColumn('auth_users', 'storage_used_bytes')) {
                $t->unsignedBigInteger('storage_used_bytes')->default(0)->after('login_count');
            }
            if (!Schema::hasColumn('auth_users', 'storage_quota_bytes')) {
                // 5 GB default = the free-tier cap
                $t->unsignedBigInteger('storage_quota_bytes')->default(5368709120)->after('storage_used_bytes');
            }
            if (!Schema::hasColumn('auth_users', 'current_storage_sub_id')) {
                $t->unsignedBigInteger('current_storage_sub_id')->nullable()->after('storage_quota_bytes');
                $t->index('current_storage_sub_id');
            }
            if (!Schema::hasColumn('auth_users', 'storage_plan_code')) {
                $t->string('storage_plan_code', 50)->default('free')->after('current_storage_sub_id');
                $t->index('storage_plan_code');
            }
            if (!Schema::hasColumn('auth_users', 'storage_plan_status')) {
                $t->string('storage_plan_status', 16)->default('active')->after('storage_plan_code');
                $t->index('storage_plan_status');
            }
            if (!Schema::hasColumn('auth_users', 'storage_renews_at')) {
                $t->timestamp('storage_renews_at')->nullable()->after('storage_plan_status');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('auth_users')) {
            return;
        }

        Schema::table('auth_users', function (Blueprint $t) {
            foreach (['storage_renews_at', 'storage_plan_status', 'storage_plan_code',
                      'current_storage_sub_id', 'storage_quota_bytes', 'storage_used_bytes'] as $col) {
                if (Schema::hasColumn('auth_users', $col)) {
                    try { $t->dropIndex(['auth_users_'.$col.'_index']); } catch (\Throwable) {}
                    $t->dropColumn($col);
                }
            }
        });
    }
};
