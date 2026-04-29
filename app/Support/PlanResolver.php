<?php

namespace App\Support;

use App\Models\PhotographerProfile;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Single source for "what plan is this user on right now?".
 *
 * The platform has TWO parallel subscription systems:
 *   - Photographer subscriptions   (auth_users → photographerProfile.subscription_plan_code)
 *   - Consumer storage subscriptions (auth_users.storage_plan_code)
 *
 * Most callsites care about the photographer plan and fall back to 'free'
 * when the user is anonymous, has no photographer profile, or hasn't yet
 * been synced. A handful of callsites (notably DataExport which serves
 * BOTH user classes) want the photographer plan if present, else the
 * storage plan, else 'free'.
 *
 * Centralising this here:
 *   1. Removes 5+ copy-pastes of the null-coalescing chain
 *   2. Stops drift when one callsite forgets the `(string)` cast
 *   3. Gives one place to add future plan-resolution rules (e.g. an
 *      admin-impersonation override, a feature-flag-overridden plan)
 *
 * Pure read — no side effects, no DB query (reads denormalised cache
 * columns the SubscriptionService keeps in sync).
 */
final class PlanResolver
{
    public const FREE = 'free';

    /**
     * Resolve the photographer plan code only. Used by gates that ONLY
     * apply to photographer-side features (event creation, AI credits,
     * commission rates).
     */
    public static function photographerCode(?Authenticatable $user): string
    {
        if (!$user instanceof User) {
            return self::FREE;
        }
        $profile = $user->photographerProfile ?? null;
        if ($profile instanceof PhotographerProfile && $profile->subscription_plan_code) {
            return (string) $profile->subscription_plan_code;
        }
        return self::FREE;
    }

    /**
     * Resolve the consumer-storage plan code only. Used by the
     * FileManager (UserFile) flow which is its own paid product
     * separate from photographer subscriptions.
     */
    public static function storageCode(?Authenticatable $user): string
    {
        if (!$user instanceof User) {
            return self::FREE;
        }
        $code = $user->storage_plan_code ?? null;
        return $code ? (string) $code : self::FREE;
    }

    /**
     * Resolve a plan code with a photographer-first / storage-second
     * fallback chain. Used by services that bill BOTH user classes
     * against the same `usage_events` ledger (e.g. DataExport).
     */
    public static function resolveCode(?Authenticatable $user): string
    {
        if (!$user instanceof User) {
            return self::FREE;
        }
        // Photographer plan wins when present — they're the heavier-billed
        // class, and the storage plan would under-report margin.
        $photographer = self::photographerCode($user);
        if ($photographer !== self::FREE) {
            return $photographer;
        }
        // Fall through to consumer storage plan (denormalised on auth_users).
        $storagePlan = $user->storage_plan_code ?? null;
        return $storagePlan ? (string) $storagePlan : self::FREE;
    }
}
