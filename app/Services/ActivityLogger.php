<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

/**
 * ActivityLogger — thin writer for the `activity_logs` table.
 *
 * Schema columns:
 *   admin_id, user_id, action, target_type, target_id,
 *   description, ip_address, user_agent, old_values, new_values, created_at
 *
 * Keys matching /secret|token|password|api_key/i are redacted automatically
 * before being stored in old_values/new_values.
 */
class ActivityLogger
{
    /** Keys that should never be written to the audit log verbatim. */
    protected const SECRET_KEY_PATTERN = '/(secret|token|password|api_key|private_key|webhook_secret|client_secret|access_key)/i';

    /** Value placeholder for redacted secret fields. */
    protected const REDACT_PLACEHOLDER = '***REDACTED***';

    /**
     * Write an admin-initiated entry.
     *
     * @param  string       $action       Short verb like "user.suspended" or "order.cancelled"
     * @param  mixed        $target       Eloquent model, [type, id] pair, or null
     * @param  string|null  $description  Free-text description (Thai OK)
     * @param  array|null   $oldValues    Pre-change snapshot (optional)
     * @param  array|null   $newValues    Post-change snapshot (optional)
     */
    public static function admin(
        string $action,
        $target = null,
        ?string $description = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): ?ActivityLog {
        return self::write([
            'admin_id'    => Auth::guard('admin')->id(),
            'user_id'     => null,
            'action'      => $action,
            'target_type' => self::resolveTargetType($target),
            'target_id'   => self::resolveTargetId($target),
            'description' => $description,
            'old_values'  => self::redact($oldValues),
            'new_values'  => self::redact($newValues),
        ]);
    }

    /**
     * Write a user-initiated entry (public-facing actions).
     */
    public static function user(
        string $action,
        $target = null,
        ?string $description = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): ?ActivityLog {
        return self::write([
            'admin_id'    => null,
            'user_id'     => Auth::id(),
            'action'      => $action,
            'target_type' => self::resolveTargetType($target),
            'target_id'   => self::resolveTargetId($target),
            'description' => $description,
            'old_values'  => self::redact($oldValues),
            'new_values'  => self::redact($newValues),
        ]);
    }

    /**
     * Write a system-initiated entry — no admin or user attribution.
     *
     * Used by background workers, the OrderStateMachine, webhook handlers,
     * and any other code path where the actor isn't a logged-in person.
     * Without this, those events were being silently dropped because the
     * try/catch around the legacy admin()/user() calls hides the
     * "actor is null" failure.
     */
    public static function system(
        string $action,
        $target = null,
        ?string $description = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): ?ActivityLog {
        return self::write([
            'admin_id'    => null,
            'user_id'     => null,
            'action'      => $action,
            'target_type' => self::resolveTargetType($target),
            'target_id'   => self::resolveTargetId($target),
            'description' => $description,
            'old_values'  => self::redact($oldValues),
            'new_values'  => self::redact($newValues),
        ]);
    }

    /**
     * Backward-compatible shim for older `log(action, description, module, userId)` calls.
     * Maps to `user()` logging; the `$module` arg is ignored (no DB column).
     */
    public static function log(
        string $action,
        string $description = '',
        ?string $module = null,
        ?int $userId = null
    ): ?ActivityLog {
        return self::write([
            'admin_id'    => null,
            'user_id'     => $userId ?? Auth::id(),
            'action'      => $action,
            'target_type' => null,
            'target_id'   => null,
            'description' => $description,
            'old_values'  => null,
            'new_values'  => null,
        ]);
    }

    /**
     * Core writer — never throws; failures are logged but swallowed so a broken
     * audit table can't take down a whole controller action.
     */
    protected static function write(array $data): ?ActivityLog
    {
        try {
            return ActivityLog::create(array_merge($data, [
                'ip_address' => Request::ip(),
                'user_agent' => substr((string) Request::userAgent(), 0, 500),
                'created_at' => now(),
            ]));
        } catch (\Throwable $e) {
            Log::warning('ActivityLogger write failed: ' . $e->getMessage(), [
                'action' => $data['action'] ?? null,
            ]);
            return null;
        }
    }

    /**
     * Remove or mask sensitive values before persisting to DB.
     */
    protected static function redact(?array $values): ?array
    {
        if (!is_array($values) || empty($values)) {
            return $values;
        }

        $out = [];
        foreach ($values as $key => $value) {
            if (is_string($key) && preg_match(self::SECRET_KEY_PATTERN, $key)) {
                $out[$key] = self::REDACT_PLACEHOLDER;
                continue;
            }
            if (is_array($value)) {
                $out[$key] = self::redact($value);
                continue;
            }
            // Defensive truncation — huge blobs bloat the table
            if (is_string($value) && strlen($value) > 2000) {
                $out[$key] = substr($value, 0, 2000) . '…';
                continue;
            }
            $out[$key] = $value;
        }
        return $out;
    }

    /** Resolve a model/array target to a `target_type` string. */
    protected static function resolveTargetType($target): ?string
    {
        if ($target === null) return null;
        if (is_array($target) && isset($target[0])) return (string) $target[0];
        if (is_object($target)) {
            // Short class name — "User" instead of "App\Models\User"
            $class = get_class($target);
            $parts = explode('\\', $class);
            return end($parts);
        }
        return null;
    }

    /** Resolve a model/array target to a `target_id` integer. */
    protected static function resolveTargetId($target): ?int
    {
        if ($target === null) return null;
        if (is_array($target) && isset($target[1])) return (int) $target[1];
        if (is_object($target) && isset($target->id)) return (int) $target->id;
        return null;
    }
}
