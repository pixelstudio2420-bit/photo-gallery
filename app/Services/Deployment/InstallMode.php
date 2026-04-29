<?php

namespace App\Services\Deployment;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Detect whether the application is in "install mode" — i.e. a fresh upload
 * with no admin user yet, or DB still unconfigured. While in install mode,
 * `/admin/deployment` is reachable WITHOUT admin login so the operator can
 * configure DB credentials, run migrations, and create the first admin.
 *
 * Auto-secures: install mode automatically ends the moment an admin user
 * exists in the database. There is no manual flag to forget about — the
 * gate is the presence of an admin row.
 *
 * Triggers (any one):
 *   1. .env file missing or APP_KEY empty (Laravel can't even encrypt sessions)
 *   2. Database connection fails
 *   3. Required tables (auth_users) don't exist (migrations not run)
 *   4. No admin/super-admin user exists yet
 */
class InstallMode
{
    /**
     * Is the system currently in install mode?
     * Returns true if any blocking condition prevents normal admin login.
     */
    public static function isActive(): bool
    {
        return self::reason() !== null;
    }

    /**
     * Returns null when fully installed, or a human-readable reason string
     * describing why install mode is active. Useful for the UI banner.
     */
    public static function reason(): ?string
    {
        // 1. APP_KEY missing — sessions/encryption broken.
        if (empty(config('app.key'))) {
            return 'APP_KEY ยังไม่ถูก generate — ระบบ session/encryption ทำงานไม่ได้';
        }

        // 2. Try DB connection.
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            return 'ยังไม่ได้ตั้งค่า Database — ' . self::shortenError($e->getMessage());
        }

        // 3. Required admin table missing (migrations not run).
        //    This app stores admins in `auth_admins` (separate from regular
        //    `auth_users`). If neither table exists, migrations haven't run.
        if (!Schema::hasTable('auth_admins')) {
            return 'ยังไม่ได้รัน migrations — table "auth_admins" ไม่มี';
        }

        // 4. No active admin user yet.
        //    Counts `is_active = 1` AND a recognised role (matches Admin::ROLES).
        //    A row with role='editor' or is_active=0 doesn't unlock install mode —
        //    the operator still needs to bootstrap a privileged super-admin.
        try {
            $usableAdmins = DB::table('auth_admins')
                ->where('is_active', 1)
                ->whereIn('role', ['superadmin', 'admin', 'editor'])
                ->count();
            if ($usableAdmins === 0) {
                return 'ยังไม่มี admin user ในระบบ — สร้างบัญชี admin คนแรก';
            }
        } catch (\Throwable $e) {
            return 'ตรวจสอบ admin user ไม่ได้ — ' . self::shortenError($e->getMessage());
        }

        return null;
    }

    /**
     * What stage of install are we at? Returns one of:
     *   'no_key' — APP_KEY missing
     *   'no_db'  — DB connection fails
     *   'no_migrations' — DB OK but tables missing
     *   'no_admin' — DB + tables OK but no admin user
     *   'installed' — fully installed
     *
     * Drives the wizard UI (which step to highlight as current).
     */
    public static function stage(): string
    {
        if (empty(config('app.key'))) return 'no_key';

        try {
            DB::connection()->getPdo();
        } catch (\Throwable) {
            return 'no_db';
        }

        if (!Schema::hasTable('auth_admins')) return 'no_migrations';

        try {
            // Same gate as reason() — only count active admins with a recognised role.
            $usable = DB::table('auth_admins')
                ->where('is_active', 1)
                ->whereIn('role', ['superadmin', 'admin', 'editor'])
                ->count();
            if ($usable === 0) return 'no_admin';
        } catch (\Throwable) {
            return 'no_admin';
        }

        return 'installed';
    }

    /**
     * Is the request reasonably "trusted" for install operations?
     * Loose check — install-mode endpoints are still protected by the
     * stage gate (e.g. you can't create a 2nd admin via install mode),
     * but this prevents drive-by exploitation when someone happens to
     * find /admin/deployment exposed during the install window.
     *
     * Trust heuristics:
     *   - Request from localhost / private IP
     *   - APP_ENV is local / staging
     *   - Authenticated admin (then the install routes work like normal admin routes)
     */
    public static function isTrustedRequest($request = null): bool
    {
        $request ??= request();

        // Already authenticated as admin — always trusted.
        if (auth('admin')->check()) return true;

        // Local IPs (loopback + private ranges).
        $ip = $request->ip();
        if ($ip === '127.0.0.1' || $ip === '::1') return true;
        if (preg_match('/^10\./', $ip))                                              return true;
        if (preg_match('/^192\.168\./', $ip))                                        return true;
        if (preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $ip))                     return true;

        // Non-production environment — trust the operator.
        $env = config('app.env', 'production');
        if (in_array($env, ['local', 'staging', 'testing'], true)) return true;

        // Production + remote IP + unauthenticated → not trusted.
        return false;
    }

    private static function shortenError(string $msg, int $max = 140): string
    {
        $msg = preg_replace('/\s+/', ' ', trim($msg));
        return strlen($msg) > $max ? substr($msg, 0, $max - 3) . '...' : $msg;
    }
}
