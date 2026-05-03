<?php

namespace App\Services;

use App\Models\Festival;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FestivalThemeService — single source of truth for "what festival is
 * happening right now" and "what does that festival look like."
 *
 * Two consumers:
 *   • festival-popup partial (renders the highest-priority active
 *     festival to the current user, gated by their dismissals + their
 *     province targeting)
 *   • Admin /admin/festivals page (lists current/upcoming/past)
 *
 * Theme variants are defined here as a static array (not in DB) so
 * adding a new look ("snow-blue", "rainbow") doesn't require a migration.
 * Each variant maps to a Tailwind gradient + decorative emoji + accent
 * colors. The popup partial reads from THEMES[$slug] when rendering.
 */
class FestivalThemeService
{
    /**
     * Theme variants — palette + decorative cues per festival mood.
     *
     * Keep keys lowercase-with-dashes to match what's stored in the
     * `theme_variant` column. Admin form should populate its dropdown
     * from this same array (single source of truth).
     */
    public const THEMES = [
        'water-blue' => [
            'label'      => '💦 น้ำสาด (สงกรานต์)',
            'gradient'   => 'from-sky-400 via-cyan-500 to-blue-600',
            'gradient_css' => 'linear-gradient(135deg, #38bdf8 0%, #06b6d4 50%, #2563eb 100%)',
            'accent'     => 'cyan',
            'icon'       => 'bi-droplet-fill',
            'sparkle'    => '💦',
        ],
        'lantern-gold' => [
            'label'      => '🏮 โคมทอง (ลอยกระทง)',
            'gradient'   => 'from-amber-400 via-orange-500 to-yellow-600',
            'gradient_css' => 'linear-gradient(135deg, #fbbf24 0%, #f97316 50%, #ca8a04 100%)',
            'accent'     => 'amber',
            'icon'       => 'bi-stars',
            'sparkle'    => '🏮',
        ],
        'red-firework' => [
            'label'      => '🎆 พลุปีใหม่ (NYE / ตรุษจีน)',
            'gradient'   => 'from-red-500 via-rose-500 to-pink-600',
            'gradient_css' => 'linear-gradient(135deg, #ef4444 0%, #f43f5e 50%, #db2777 100%)',
            'accent'     => 'rose',
            'icon'       => 'bi-fire',
            'sparkle'    => '🎆',
        ],
        'snow-white' => [
            'label'      => '❄️ คริสต์มาส',
            'gradient'   => 'from-blue-400 via-indigo-500 to-violet-600',
            'gradient_css' => 'linear-gradient(135deg, #60a5fa 0%, #6366f1 50%, #7c3aed 100%)',
            'accent'     => 'indigo',
            'icon'       => 'bi-snow2',
            'sparkle'    => '❄️',
        ],
        'sakura-pink' => [
            'label'      => '🌸 วาเลนไทน์ / แม่',
            'gradient'   => 'from-pink-400 via-rose-400 to-fuchsia-500',
            'gradient_css' => 'linear-gradient(135deg, #f472b6 0%, #fb7185 50%, #d946ef 100%)',
            'accent'     => 'pink',
            'icon'       => 'bi-heart-fill',
            'sparkle'    => '🌸',
        ],
        'rainbow-pride' => [
            'label'      => '🏳️‍🌈 Pride',
            'gradient'   => 'from-red-500 via-yellow-400 via-emerald-500 via-blue-500 to-violet-500',
            'gradient_css' => 'linear-gradient(135deg, #ef4444 0%, #facc15 25%, #10b981 50%, #3b82f6 75%, #8b5cf6 100%)',
            'accent'     => 'fuchsia',
            'icon'       => 'bi-rainbow',
            'sparkle'    => '🏳️‍🌈',
        ],
        'pumpkin-orange' => [
            'label'      => '🎃 ฮาโลวีน',
            'gradient'   => 'from-orange-500 via-amber-600 to-violet-800',
            'gradient_css' => 'linear-gradient(135deg, #f97316 0%, #d97706 50%, #5b21b6 100%)',
            'accent'     => 'orange',
            'icon'       => 'bi-emoji-smile-fill',
            'sparkle'    => '🎃',
        ],
    ];

    /**
     * Resolve a theme variant slug to its rendering metadata. Falls
     * back to water-blue (the safest neutral) for unknown variants
     * so a typo in admin doesn't crash the popup.
     */
    public static function theme(string $variant): array
    {
        return self::THEMES[$variant] ?? self::THEMES['water-blue'];
    }

    /**
     * The single highest-priority festival currently active for a user.
     * Priority order:
     *   1. Higher show_priority wins
     *   2. Closer starts_at wins (more "this week" feeling)
     *   3. More recent created_at as tiebreaker
     *
     * Filters:
     *   • enabled = true
     *   • now ∈ [popup_starts_at, ends_at]
     *   • province match OR target_province_id IS NULL
     *   • not in user's dismissals
     *
     * Cached 60s per user — same TTL as announcements popup.
     */
    public function activeForUser(User $user): ?Festival
    {
        if (!Schema::hasTable('festivals')) return null;

        return Cache::remember(
            'festival_popup_user_' . $user->id,
            60,
            function () use ($user) {
                $now = now()->toDateString();

                $query = Festival::query()
                    ->enabled()
                    // Active window: starts_at - lead_days <= now <= ends_at.
                    // We compute the lead-adjusted start via raw SQL because
                    // SQLite + PG syntax differ; raw expression keeps it
                    // portable across local dev (sqlite) and prod (pg).
                    ->whereRaw("(starts_at - (popup_lead_days || ' days')::interval)::date <= ?", [$now])
                    ->where('ends_at', '>=', $now)
                    ->where(function ($q) use ($user) {
                        $q->whereNull('target_province_id')
                          ->orWhere('target_province_id', $user->province_id ?? 0);
                    })
                    ->whereNotIn('id', function ($sub) use ($user) {
                        $sub->select('festival_id')
                            ->from('festival_dismissals')
                            ->where('user_id', $user->id);
                    })
                    ->orderByDesc('show_priority')
                    ->orderBy('starts_at')
                    ->orderByDesc('created_at');

                return $query->first();
            }
        );
    }

    /**
     * Festivals that haven't started yet but will start within the next
     * N days. Used by admin dashboard "อะไรกำลังจะมา" widget so they
     * can plan promotions ahead of time.
     */
    public function upcoming(int $days = 30)
    {
        if (!Schema::hasTable('festivals')) return collect();

        return Festival::enabled()
            ->whereDate('starts_at', '>', now())
            ->whereDate('starts_at', '<=', now()->addDays($days))
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * Festivals currently in their celebration window (popup-active
     * OR celebration-active). Admin uses this to see "what's live
     * RIGHT NOW" without scanning the full list.
     */
    public function currentlyActive()
    {
        if (!Schema::hasTable('festivals')) return collect();

        $now = now()->toDateString();

        return Festival::enabled()
            ->whereRaw("(starts_at - (popup_lead_days || ' days')::interval)::date <= ?", [$now])
            ->where('ends_at', '>=', $now)
            ->orderByDesc('show_priority')
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * Bust the cached popup decision for a single user — call after
     * they dismiss a festival, so next page load doesn't show the
     * same popup again from cache.
     */
    public function bustUserCache(int $userId): void
    {
        Cache::forget('festival_popup_user_' . $userId);
    }
}
