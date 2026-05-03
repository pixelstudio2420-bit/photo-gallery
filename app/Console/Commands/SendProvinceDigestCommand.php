<?php

namespace App\Console\Commands;

use App\Models\Festival;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/**
 * Per-province weekly email digest — sends each user a summary of:
 *   1. New events in their province from the past 7 days
 *   2. Festivals currently active or upcoming within 14 days
 *   3. Province-targeted announcements published in the past 7 days
 *
 * Filters:
 *   • User must have province_id set (NULL = no geo-personalisation
 *     so they get nothing — by design, opting out)
 *   • User must NOT have unsubscribed from digest emails
 *     (auth_users.email_digest_opt_out — added by separate migration
 *     if needed; for now we check email_verified=true as a proxy
 *     for "wants emails")
 *   • Email is verified (skip noisy unverified accounts)
 *   • Skip if NOTHING fresh to report — empty digest emails
 *     are spammy. We require ≥1 piece of content per user.
 *
 * Usage:
 *   php artisan digest:send-weekly             # all eligible users
 *   php artisan digest:send-weekly --dry-run   # log only, no send
 *   php artisan digest:send-weekly --user=42   # one specific user
 *
 * Cron: scheduled in routes/console.php to run Mondays at 09:00 — a
 * tested-good "open rate" window for Thai consumers (after coffee,
 * before lunch).
 */
class SendProvinceDigestCommand extends Command
{
    protected $signature = 'digest:send-weekly
                            {--dry-run : Log what would be sent without actually sending}
                            {--user= : Send to one specific user_id (testing)}';

    protected $description = 'Send weekly province-targeted email digest to users';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $userId = $this->option('user');

        $this->info($dryRun
            ? '─── DRY RUN — no emails sent ───'
            : '─── Sending weekly province digests ───');

        $query = User::query()
            ->whereNotNull('province_id')
            ->whereNotNull('email')
            ->where('email_verified', true);

        if ($userId) {
            $query->where('id', (int) $userId);
        }

        $totalUsers   = $query->count();
        $sent         = 0;
        $skippedEmpty = 0;
        $failed       = 0;

        if ($totalUsers === 0) {
            $this->warn('No eligible users (need province_id + verified email).');
            return self::SUCCESS;
        }

        $this->info("Eligible users: {$totalUsers}");

        // Bulk-fetch the shared content first to amortise DB cost
        // across all recipients in the same province.
        $cachePerProvince = [];

        $query->chunk(50, function ($users) use (&$sent, &$skippedEmpty, &$failed, &$cachePerProvince, $dryRun) {
            foreach ($users as $user) {
                try {
                    $provinceId = (int) $user->province_id;

                    if (!isset($cachePerProvince[$provinceId])) {
                        $cachePerProvince[$provinceId] = $this->buildProvinceContent($provinceId);
                    }
                    $content = $cachePerProvince[$provinceId];

                    // Empty content = skip the user. Don't spam them
                    // with "here's nothing new!" emails.
                    if ($content['is_empty']) {
                        $skippedEmpty++;
                        continue;
                    }

                    if ($dryRun) {
                        $this->line("  [DRY] would send to {$user->email} (province #{$provinceId}) — "
                            . "{$content['event_count']} events / {$content['festival_count']} festivals / {$content['announcement_count']} announcements");
                    } else {
                        $this->sendDigest($user, $content);
                    }
                    $sent++;
                } catch (\Throwable $e) {
                    $failed++;
                    Log::warning('Province digest failed for user ' . $user->id . ': ' . $e->getMessage());
                }
            }
        });

        $this->newLine();
        $this->info("✓ Sent: {$sent}");
        $this->info("✗ Skipped (empty content): {$skippedEmpty}");
        if ($failed > 0) $this->error("✗ Failed: {$failed}");

        return self::SUCCESS;
    }

    /**
     * Gather all the province-scoped content one time per province.
     * Cached in-memory across users in the same chunk so we don't
     * re-query for every recipient.
     */
    private function buildProvinceContent(int $provinceId): array
    {
        $now      = now();
        $weekAgo  = $now->copy()->subDays(7);
        $twoWeeks = $now->copy()->addDays(14);

        // ── New events in province (past 7 days) ──────────────
        // Table is `event_events` (plural-of-plural Laravel naming
        // for the Event model — the standalone `events` name is taken
        // by `marketing_events` analytics).
        $events = collect();
        if (Schema::hasTable('event_events')) {
            $events = DB::table('event_events')
                ->where('province_id', $provinceId)
                ->where('status', 'public')
                ->where('created_at', '>=', $weekAgo)
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(['id', 'name', 'slug', 'shoot_date', 'cover_image']);
        }

        // ── Active + upcoming festivals (next 14 days) ────────
        // We don't filter by province for festivals — most are
        // nationwide. The popup gating handles per-province
        // targeting separately.
        $festivals = collect();
        if (Schema::hasTable('festivals')) {
            $festivals = Festival::enabled()
                ->where(function ($q) use ($provinceId) {
                    $q->whereNull('target_province_id')
                      ->orWhere('target_province_id', $provinceId);
                })
                ->whereDate('ends_at', '>=', $now)
                ->whereDate('starts_at', '<=', $twoWeeks)
                ->orderBy('starts_at')
                ->limit(3)
                ->get();
        }

        // ── Recent province-targeted announcements ─────────────
        $announcements = collect();
        if (Schema::hasTable('announcements')) {
            $announcements = DB::table('announcements')
                ->where('status', 'published')
                ->where(function ($q) use ($provinceId) {
                    $q->whereNull('target_province_id')
                      ->orWhere('target_province_id', $provinceId);
                })
                ->whereNull('deleted_at')
                ->where('created_at', '>=', $weekAgo)
                ->orderByDesc('created_at')
                ->limit(3)
                ->get(['id', 'title', 'slug', 'excerpt', 'cta_label', 'cta_url']);
        }

        $eventCount        = $events->count();
        $festivalCount     = $festivals->count();
        $announcementCount = $announcements->count();
        $isEmpty           = $eventCount === 0 && $festivalCount === 0 && $announcementCount === 0;

        // Resolve province name for the email greeting
        $provinceName = DB::table('thai_provinces')->where('id', $provinceId)->value('name_th') ?? 'จังหวัดของคุณ';

        return [
            'province_id'         => $provinceId,
            'province_name'       => $provinceName,
            'events'              => $events,
            'festivals'           => $festivals,
            'announcements'       => $announcements,
            'event_count'         => $eventCount,
            'festival_count'      => $festivalCount,
            'announcement_count'  => $announcementCount,
            'is_empty'            => $isEmpty,
        ];
    }

    /**
     * Send the digest as plain HTML email via Mail::raw — keeps
     * dependencies minimal (no Mailable class) and matches the
     * pattern used by CheckQueueHeartbeatCommand.
     */
    private function sendDigest(User $user, array $content): void
    {
        $name = trim($user->first_name . ' ' . $user->last_name) ?: 'คุณลูกค้า';
        $subject = "📬 สรุปข่าว {$content['province_name']} ประจำสัปดาห์";

        $html = view('emails.province-digest', [
            'user'    => $user,
            'name'    => $name,
            'content' => $content,
        ])->render();

        Mail::send([], [], function ($message) use ($user, $subject, $html) {
            $message->to($user->email, trim($user->first_name . ' ' . $user->last_name))
                    ->subject($subject)
                    ->html($html);
        });
    }
}
