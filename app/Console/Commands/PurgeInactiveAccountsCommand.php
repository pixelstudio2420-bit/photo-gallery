<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Models\PhotographerProfile;
use App\Models\User;
use App\Services\MailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Identify dormant Free photographers and sweep them out.
 *
 * A Free account that hasn't logged in for 90+ days AND has never made a
 * sale (or no sale for 180+ days) is dead weight — they upload, never come
 * back, never sell, but the platform pays R2 storage for their photos
 * indefinitely. This command finds them, warns them once, then deletes
 * their account + all events after a 30-day grace.
 *
 * Safety rails
 * ────────────
 *   • OFF by default — admin must set `inactive_sweep_enabled=1`
 *   • Skips ANY photographer with active subscription (no matter how long
 *     they've been quiet — they paid)
 *   • Skips photographers with `inactive_sweep_exempt=true` on their profile
 *   • Two-stage: WARN first (email + mark pending_deletion_at), then DELETE
 *     after grace days have elapsed
 *   • Hard cap on deletions per run (so a config error can't nuke the whole
 *     userbase in one cron tick)
 *   • --dry-run flag for safe preview
 *
 * Usage
 * ─────
 *   php artisan accounts:sweep-inactive --dry-run
 *   php artisan accounts:sweep-inactive --force
 *   php artisan accounts:sweep-inactive --limit=5
 *
 * Scheduled weekly (Sunday 05:30) so the DELETE pass isn't tied to nightly
 * retention purges (different operational concern + lower frequency keeps
 * surprises rare).
 */
class PurgeInactiveAccountsCommand extends Command
{
    protected $signature = 'accounts:sweep-inactive
        {--dry-run             : Preview only}
        {--force               : Override inactive_sweep_enabled=0}
        {--limit=              : Cap deletions this run}
        {--warn-only           : Send warnings only; skip the delete pass}
        {--quiet-success       : Quiet output when no candidates}';

    protected $description = 'ลบบัญชีช่างภาพ Free ที่ไม่ active เพื่อคืน R2 + ลด ghost users';

    /** Absolute safety cap — never delete more accounts than this per run. */
    private const MAX_DELETIONS_PER_RUN = 20;

    public function handle(MailService $mail): int
    {
        $dryRun  = (bool) $this->option('dry-run');
        $force   = (bool) $this->option('force');
        $warnOnly = (bool) $this->option('warn-only');
        $quiet   = (bool) $this->option('quiet-success');

        $enabled = (string) AppSetting::get('inactive_sweep_enabled', '0') === '1';
        if (!$enabled && !$force) {
            if (!$quiet) {
                $this->warn('inactive_sweep_enabled=0 — ใช้ --force หรือเปิดที่ Admin → Settings');
            }
            return self::SUCCESS;
        }

        $loginDays    = (int) AppSetting::get('inactive_sweep_login_days', 90);
        $noSalesDays  = (int) AppSetting::get('inactive_sweep_no_sales_days', 180);
        $warnDays     = (int) AppSetting::get('inactive_sweep_warning_days_ahead', 30);
        $limit        = (int) ($this->option('limit') ?? self::MAX_DELETIONS_PER_RUN);
        $limit        = min(self::MAX_DELETIONS_PER_RUN, max(1, $limit));

        $loginCutoff  = now()->subDays($loginDays);
        $salesCutoff  = now()->subDays($noSalesDays);

        $this->line('');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line(' Inactive Account Sweep  ' . ($dryRun ? '<fg=yellow>[DRY RUN]</>' : ''));
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line(" Login threshold  : <fg=cyan>{$loginDays} days</> (no login since {$loginCutoff->format('Y-m-d')})");
        $this->line(" Sales threshold  : <fg=cyan>{$noSalesDays} days</>");
        $this->line(" Grace ahead      : <fg=cyan>{$warnDays} days</>");
        $this->line(" Max deletes/run  : {$limit}");
        $this->line('');

        $warned = $this->warnPass($mail, $loginCutoff, $salesCutoff, $warnDays, $dryRun);

        $deleted = 0;
        if (!$warnOnly) {
            $deleted = $this->deletePass($limit, $dryRun);
        }

        $this->line('');
        $this->line('━━━ Summary ━━━');
        $this->line(" Warned (new)  : <fg=" . ($warned ? 'green' : 'gray') . ">{$warned}</>");
        $this->line(" Deleted       : <fg=" . ($deleted ? 'red' : 'gray') . ">{$deleted}</>");
        $this->line('');

        return self::SUCCESS;
    }

    /**
     * Find candidates and mark them with pending_deletion_at + send warning email.
     * Skips anyone already marked (idempotent).
     */
    private function warnPass(MailService $mail, $loginCutoff, $salesCutoff, int $warnDays, bool $dryRun): int
    {
        // Build candidate set:
        //   - photographer_profiles.status = approved
        //   - tier = creator (Free) OR subscription_plan_code = free
        //   - auth_users.last_login_at < login cutoff (or NULL = never logged in)
        //   - No active subscription
        //   - Not already marked pending_deletion_at
        //   - No exemption flag
        $candidates = DB::table('photographer_profiles as pp')
            ->join('auth_users as u', 'u.id', '=', 'pp.user_id')
            ->leftJoin('photographer_subscriptions as ps', function ($j) {
                $j->on('ps.photographer_id', '=', 'pp.user_id')
                  ->whereIn('ps.status', ['active', 'grace']);
            })
            ->where('pp.status', 'approved')
            ->where(function ($q) {
                $q->where('pp.tier', PhotographerProfile::TIER_CREATOR)
                  ->orWhere('pp.subscription_plan_code', 'free')
                  ->orWhereNull('pp.subscription_plan_code');
            })
            ->where(function ($q) use ($loginCutoff) {
                $q->whereNull('u.last_login_at')
                  ->orWhere('u.last_login_at', '<', $loginCutoff);
            })
            ->whereNull('ps.id')                          // no active sub
            ->whereNull('pp.pending_deletion_at')         // not already warned
            ->where(function ($q) {
                $q->whereNull('pp.inactive_sweep_exempt')
                  ->orWhere('pp.inactive_sweep_exempt', false);
            })
            ->select(
                'pp.id as profile_id',
                'pp.user_id',
                'pp.display_name',
                'u.email',
                'u.first_name',
                'u.last_name',
                'u.last_login_at'
            )
            ->limit(100)
            ->get();

        // Filter out anyone with a recent sale via order rows.
        // Note: order_items.photo_id is character varying, event_photos.id
        // is bigint — direct join needs a CAST. Use orders.event_id instead
        // (avoiding the type-mismatch join) since each order is associated
        // with at most one event in this schema.
        $candidates = $candidates->filter(function ($c) use ($salesCutoff) {
            return !DB::table('orders')
                ->join('event_events as ee', 'ee.id', '=', 'orders.event_id')
                ->where('ee.photographer_id', $c->user_id)
                ->whereIn('orders.status', ['paid', 'completed'])
                ->where('orders.created_at', '>=', $salesCutoff)
                ->exists();
        });

        if ($candidates->isEmpty()) {
            return 0;
        }

        $warned = 0;
        $deleteAt = now()->addDays($warnDays);

        foreach ($candidates as $c) {
            $name = trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? '')) ?: ($c->display_name ?? 'ช่างภาพ');
            $lastLogin = $c->last_login_at ? \Illuminate\Support\Carbon::parse($c->last_login_at)->format('Y-m-d') : 'ไม่เคย';

            $this->line(sprintf(
                "  <fg=yellow>warn</> u#%d %s — last login %s, will delete %s",
                $c->user_id, $name, $lastLogin, $deleteAt->format('Y-m-d')
            ));

            if ($dryRun) {
                $warned++;
                continue;
            }

            try {
                // Mark pending_deletion_at on the profile
                DB::table('photographer_profiles')->where('id', $c->profile_id)->update([
                    'pending_deletion_at' => $deleteAt,
                    'updated_at'          => now(),
                ]);

                // Send warning email if possible
                if (!empty($c->email)) {
                    try {
                        $mail->sendTemplate(
                            $c->email,
                            '⏰ บัญชีของคุณจะถูกลบใน ' . $warnDays . ' วัน — ล็อกอินเพื่อเก็บไว้',
                            'emails.photographer.inactive-warning',
                            [
                                'name'         => $name,
                                'warnDays'     => $warnDays,
                                'deleteAt'     => $deleteAt->format('Y-m-d'),
                                'lastLoginAt'  => $lastLogin,
                                'dashboardUrl' => url('/photographer'),
                                'upgradeUrl'   => url('/photographer/upgrade'),
                            ],
                            'photographer_inactive_warning'
                        );
                    } catch (\Throwable $e) {
                        Log::warning("Inactive sweep email failed for user #{$c->user_id}: " . $e->getMessage());
                    }
                }
                $warned++;
            } catch (\Throwable $e) {
                Log::error("Inactive sweep warn failed for user #{$c->user_id}: " . $e->getMessage());
            }
        }

        return $warned;
    }

    /**
     * Delete photographer accounts whose grace window has expired.
     * Honours the per-run cap.
     */
    private function deletePass(int $limit, bool $dryRun): int
    {
        $rows = DB::table('photographer_profiles as pp')
            ->join('auth_users as u', 'u.id', '=', 'pp.user_id')
            ->whereNotNull('pp.pending_deletion_at')
            ->where('pp.pending_deletion_at', '<=', now())
            ->where(function ($q) {
                $q->whereNull('pp.inactive_sweep_exempt')
                  ->orWhere('pp.inactive_sweep_exempt', false);
            })
            ->select('pp.id as profile_id', 'pp.user_id', 'pp.display_name', 'u.email')
            ->limit($limit)
            ->get();

        $deleted = 0;
        foreach ($rows as $r) {
            $this->line(sprintf(
                "  <fg=red>DELETE</> u#%d %s",
                $r->user_id, $r->display_name ?? '?'
            ));

            if ($dryRun) {
                $deleted++;
                continue;
            }

            try {
                // Delete the user via Eloquent so cascading hooks fire
                // (events → photos → R2 cleanup via PurgeEventJob).
                $user = User::find($r->user_id);
                if ($user) {
                    // Mark soft-delete first if the model supports it; else hard delete.
                    if (method_exists($user, 'forceDelete')) {
                        $user->delete();
                    } else {
                        $user->forceDelete();
                    }
                    $deleted++;
                    Log::info("Inactive sweep: deleted user #{$r->user_id} ({$r->email})");
                }
            } catch (\Throwable $e) {
                Log::error("Inactive sweep delete failed for user #{$r->user_id}: " . $e->getMessage());
            }
        }

        return $deleted;
    }
}
