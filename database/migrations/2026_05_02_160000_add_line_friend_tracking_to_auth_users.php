<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Track LINE OA friendship per user.
 *
 *   line_user_id           — the LINE userId (set when user signs in via
 *                            LINE OAuth or messages our OA bot for the
 *                            first time). Foreign key into LINE's namespace,
 *                            34 chars starting with 'U'.
 *   line_is_friend         — boolean. True when LINE webhook fires 'follow'
 *                            or LINE OAuth completes with bot_prompt=aggressive.
 *                            False when 'unfollow' webhook fires.
 *   line_friend_changed_at — when the flag last flipped. Helps with
 *                            "is this a new follower?" prompts and
 *                            re-engagement timing.
 *
 * Why on auth_users (not a separate table)?
 *   - 1:1 relationship — exactly one LINE userId per app user
 *   - Most queries that need this flag also need user fields, single
 *     row lookup beats a JOIN
 *   - Indexed on line_user_id for the webhook handler's reverse lookup
 *     (LINE event → which app user does this belong to?)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('auth_users', function (Blueprint $t) {
            if (!Schema::hasColumn('auth_users', 'line_user_id')) {
                $t->string('line_user_id', 64)->nullable()->after('avatar')->index();
            }
            if (!Schema::hasColumn('auth_users', 'line_is_friend')) {
                $t->boolean('line_is_friend')->default(false)->after('line_user_id');
            }
            if (!Schema::hasColumn('auth_users', 'line_friend_changed_at')) {
                $t->timestamp('line_friend_changed_at')->nullable()->after('line_is_friend');
            }
        });
    }

    public function down(): void
    {
        Schema::table('auth_users', function (Blueprint $t) {
            if (Schema::hasColumn('auth_users', 'line_user_id'))            $t->dropColumn('line_user_id');
            if (Schema::hasColumn('auth_users', 'line_is_friend'))          $t->dropColumn('line_is_friend');
            if (Schema::hasColumn('auth_users', 'line_friend_changed_at')) $t->dropColumn('line_friend_changed_at');
        });
    }
};
