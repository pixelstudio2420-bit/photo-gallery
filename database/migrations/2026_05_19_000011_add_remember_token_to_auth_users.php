<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the standard Laravel `remember_token` column to `auth_users`.
 *
 * Why this is necessary
 * ─────────────────────
 * `Auth::login($user, $remember=true)` is called from:
 *   - app/Http/Controllers/Photographer/SocialAuthController.php:186
 *   - app/Http/Controllers/Photographer/SocialAuthController.php:272
 * (and likely by future "remember me" checkboxes on email/password
 * login). Laravel's session guard responds to `$remember=true` by:
 *   1. Generating a 60-char token via Str::random(60)
 *   2. Setting it on the user model via setRememberToken()
 *   3. Persisting via $user->save()
 *
 * Step 3 fires `UPDATE auth_users SET remember_token = ? WHERE id = ?`,
 * which fails on PostgreSQL with `42703 column "remember_token" does
 * not exist` because the table was created without it.
 *
 * Symptom this fixes
 * ──────────────────
 * Photographer LINE Login first-time signup → 500 on
 * /photographer/auth/line/callback. The DB::transaction that creates
 * the User + PhotographerProfile + SocialLogin succeeds, but the
 * subsequent `Auth::login($user, true)` blows up.
 *
 * Idempotent: column add is guarded by Schema::hasColumn so re-running
 * this migration is a no-op (defensive — Laravel's migrator already
 * tracks "ran" status, but the guard protects against partial state
 * on a botched previous run).
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('auth_users')) {
            return;
        }

        if (Schema::hasColumn('auth_users', 'remember_token')) {
            return;
        }

        Schema::table('auth_users', function (Blueprint $table) {
            // VARCHAR(100) NULL — matches Laravel's default rememberToken
            // shape exactly. The framework's getRememberTokenName()
            // returns 'remember_token' so naming must match verbatim.
            $table->rememberToken();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('auth_users')) {
            return;
        }
        if (!Schema::hasColumn('auth_users', 'remember_token')) {
            return;
        }

        Schema::table('auth_users', function (Blueprint $table) {
            $table->dropColumn('remember_token');
        });
    }
};
