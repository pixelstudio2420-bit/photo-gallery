<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ────────────────────────────────────────────────────────────────────
//  Scheduler
//  Run via cron:  * * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
// ────────────────────────────────────────────────────────────────────

// Presence cleanup — keeps user_sessions lean even with thousands of users
Schedule::command('presence:cleanup')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Stale upload sweep — aborts multipart uploads / batch sessions whose
// expires_at has passed. Without this, R2 keeps charging us for in-flight
// parts that no client will ever finish (the photographer closed the tab
// or lost network). Hourly is enough — TTL is 24 h by default.
Schedule::command('uploads:sweep-stale')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

// LINE health check — calls /v2/bot/info hourly to verify the channel
// access token still works. On failure, alerts admins via LINE
// multicast (if partially functional) AND email (out-of-band).
// Deduped via cache so a 4-hour outage doesn't spam 4 alerts.
Schedule::job(new \App\Jobs\Line\LineHealthCheckJob(), 'default')
    ->hourly()
    ->withoutOverlapping();

// LINE delivery-health sweeper — every 15 min, scans line_deliveries
// for stuck-pending backlog (>30 min old) and high-failure-rate windows.
// Alerts admins via email + LINE OA multicast on either threshold,
// dedup'd 1h per failure mode. Complements the per-job failed() hook
// in SendLinePushJob: that catches per-order incidents; this catches
// systemic outages where many orders are failing at once.
Schedule::command('line:check-delivery-health --quiet-if-clean')
    ->everyFifteenMinutes()
    ->name('line-check-delivery-health')
    ->withoutOverlapping();

// Google Calendar Watch channel renewal. Channels expire after 7 days;
// we renew anything with <12h headroom. Daily is plenty.
Schedule::call(function () {
    app(\App\Services\GoogleCalendarWatchService::class)->renewExpiringChannels();
})->name('gcal-watch-renew')->daily()->withoutOverlapping();

// Recurring-bookings materializer. Walks every active booking_series
// and creates concrete `bookings` rows up to the configured horizon
// (default 90 days ahead). Idempotent — already-materialized
// occurrences are skipped via the series_id + scheduled_at unique
// composite check.
Schedule::command('bookings:materialize-series')
    ->daily()
    ->withoutOverlapping()
    ->runInBackground();

// Queue heartbeat — every 5 min the cron schedules a tiny job; the
// worker that runs it stamps a cache key. The check command (every
// 10 min) reads that key and alerts if it's stale > 15 min.
//   • If queue is healthy: heartbeat fresh, check passes silently.
//   • If queue worker died: stamp goes stale, check alerts via
//     direct email + LINE multicast (both bypass the queue).
Schedule::job(new \App\Jobs\Operations\QueueHeartbeatJob(), 'default')
    ->name('queue-heartbeat')
    ->everyFiveMinutes();
Schedule::command('queue:check-heartbeat')
    ->name('queue-check-heartbeat')
    ->everyTenMinutes()
    ->withoutOverlapping();

// Audit-table retention — each table has its own TTL via env. See
// PruneAuditTablesCommand for the list. Off-peak so batch deletes
// don't fight with customer traffic.
Schedule::command('audit:prune')
    ->name('audit-prune')
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->runInBackground();

// Analytics — drain cache counters into request_minute_buckets every
// minute so the dashboard reads stay cheap. Then aggregate "yesterday"
// into usage_daily once per day. Capacity alerts hourly.
//
// Note on argument syntax: Laravel's Schedule::command() treats the
// whole first arg as the command name. To pass positional args use
// the parameters array (second argument).
Schedule::command('analytics:rollup', ['minute'])
    ->name('analytics-rollup-minute')
    ->everyMinute()
    ->withoutOverlapping();
Schedule::command('analytics:rollup', ['daily'])
    ->name('analytics-rollup-daily')
    ->dailyAt('00:05')
    ->withoutOverlapping();
Schedule::job(new \App\Jobs\Operations\CapacityAlertJob(), 'default')
    ->name('capacity-alert')
    ->hourly()
    ->withoutOverlapping();

// Booking LINE reminders — fires at T-3d / T-1d / T-1h / T-0 / T+1d windows.
// Idempotent (per-window timestamp dedup), so safe to run every 5 minutes.
Schedule::command('bookings:send-reminders')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Photo orphan reconciler — daily detection + auto-mark failed.
// Catches "row points at missing R2 object" drift (production logs
// showed ModeratePhotoJob spamming "cannot read bytes for photo #N"
// for orphaned rows). Marks them status='failed' so the queue stops
// retrying forever; ops investigates the root cause.
Schedule::command('photos:reconcile-orphans --purge --quiet-success --limit=5000')
    ->dailyAt('04:30')
    ->withoutOverlapping()
    ->runInBackground();

// ── Usage tracking maintenance ───────────────────────────────────
// Spike detector runs hourly at :05 (well after the hour rolls over so
// the most-recently-completed hour has its counter rows). Idempotent —
// the sentinel rows it writes are tagged as resource='_spike' for grep.
Schedule::command('usage:detect-spikes --quiet-success')
    ->hourlyAt(5)
    ->withoutOverlapping()
    ->runInBackground();

// Prune old usage rows daily at 02:35 — after the events:purge-expired at
// 02:30 (so we drop the counters generated during purge too) and before
// the 03:00 backup so the dump captures the smaller post-prune dataset.
Schedule::command('usage:prune --quiet-success')
    ->dailyAt('02:35')
    ->withoutOverlapping()
    ->runInBackground();

// ── Auto database backup ─────────────────────────────────────────
// Runs at 03:00 daily — early enough that the backup completes before
// other 03:* nightly tasks, late enough that nothing is actively
// writing. Uses pg_dump / mysqldump (with PHP fallback) and auto-prunes
// snapshots older than 14 days. Safe to skip a day if the lock is held
// (--withoutOverlapping protects against double-run).
Schedule::command('backup:database --keep-days=14 --quiet-success')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->runInBackground();

// Old activity log cleanup — runs nightly
Schedule::command('activity-log:cleanup --days=180')
    ->dailyAt('03:15')
    ->withoutOverlapping();

// Abandoned cart processing (already defined as command)
Schedule::command('carts:process-abandoned')
    ->hourly()
    ->withoutOverlapping();

// Drop stale carts weekly
Schedule::command('carts:cleanup --days=30')
    ->weekly()
    ->at('04:00')
    ->withoutOverlapping();

// Warn photographers 24h before their events are auto-deleted.
// Runs FIRST (02:00) so warnings are sent before the purge at 02:30.
// No-ops silently if retention_warning_enabled=0.
Schedule::job(new \App\Jobs\WarnUpcomingCleanupJob(), 'mail')
    ->dailyAt('02:00')
    ->withoutOverlapping();

// Auto-delete expired events (retention policy).
// Disabled by default: the command bails out unless
// AppSetting `event_auto_delete_enabled=1` is toggled on from
// Admin → Settings → Retention. Runs daily at 02:30 before the
// 03:00 backup so deleted data isn't in tonight's snapshot.
Schedule::command('events:purge-expired')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->runInBackground();

// Reconcile photographer_profiles.storage_used_bytes nightly — fixes drift
// from mass deletes (PurgeEventJob cascade) that bypass Eloquent events.
Schedule::command('photographers:recalc-storage --sync')
    ->dailyAt('03:45')
    ->withoutOverlapping()
    ->runInBackground();

// Cleanup orphan faces in AWS Rekognition collections — nightly safety net
// for cases where the EventPhoto::deleting hook failed (AWS outage, hard DB
// delete, etc.). Bails out silently if AWS is not configured.
Schedule::command('rekognition:cleanup-orphans')
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->runInBackground();

// Alert Rules Engine — check every active rule vs the current metrics
// every 5 min. `--quiet-if-none` keeps logs tidy during normal operation.
Schedule::command('alerts:check --quiet-if-none')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Gift cards — daily sweep at 02:10 to expire past-due cards
Schedule::command('gift-cards:expire-due')
    ->dailyAt('02:10')
    ->withoutOverlapping();

// Upload credits — nightly expiry sweep (02:15, right after gift-cards).
// Bundles with expires_at in the past get credits_remaining=0 and an
// EXPIRE row appended to credit_transactions. Idempotent.
Schedule::command('credits:expire-due')
    ->dailyAt('02:15')
    ->withoutOverlapping();

// Upload credits — monthly free grant on the 1st at 03:30. Amounts come
// from AppSetting monthly_free_credits_{tier}. Bundles expire in 45 days
// so unclaimed freebies roll off and the monthly cadence stays meaningful.
Schedule::command('credits:grant-monthly-free')
    ->monthlyOn(1, '03:30')
    ->withoutOverlapping()
    ->runInBackground();

// ────────────────────────────────────────────────────────────────────
//  Subscription billing
// ────────────────────────────────────────────────────────────────────

// Subscription renewals — hourly check for subs whose current_period_end
// lands within the next 24h. Creates renewal invoices + orders that the
// photographer's payment method will settle. Idempotent: rerunning doesn't
// duplicate invoices because each sub only gets one pending renewal at a
// time (the status flip to 'active' on the new period clears the flag).
Schedule::command('subscriptions:renew-due --hours=24')
    ->hourlyAt(10)
    ->withoutOverlapping()
    ->runInBackground();

// Subscription grace expiry — daily sweep at 02:20 to downgrade
// photographers whose grace window ran out (no successful payment within
// N days of the first failure). Sets them to the free plan, which
// immediately shrinks their storage_quota_bytes — enforced on next
// upload attempt by EnforceStorageQuota middleware.
Schedule::command('subscriptions:expire-grace')
    ->dailyAt('02:20')
    ->withoutOverlapping()
    ->runInBackground();

// Subscription expiring-soon reminders — daily at 09:30 (waking hours
// so the photographer sees the email same morning, not pre-dawn).
// Fires T-7 / T-3 / T-1 day reminders + grace-period countdowns.
// Idempotent via UserNotification::notifyOnce(refId per day-bucket),
// so re-running on the same day is a no-op.
Schedule::command('subscriptions:notify-expiring --quiet-if-none')
    ->name('subscriptions-notify-expiring')
    ->dailyAt('09:30')
    ->withoutOverlapping()
    ->runInBackground();

// Add-on expiry sweeper — daily at 02:22 (right after subscription
// grace expiry, before the 02:30 events purge). Does two things:
//   1. Warns photographers whose add-on expires in 3 days
//   2. Flips status='activated' → 'expired' on rows whose expires_at
//      is past, fires the "addon expired" notification
Schedule::command('addons:notify-expiring --quiet-if-none')
    ->name('addons-notify-expiring')
    ->dailyAt('02:22')
    ->withoutOverlapping()
    ->runInBackground();

// Usage threshold notifier — hourly. Storage 80%/95% + AI credits
// depleted. Each notification dedup'd per calendar month so the
// photographer hovering at 81% all month gets ONE alert, not 720.
Schedule::command('usage:notify-thresholds --quiet-if-none')
    ->name('usage-notify-thresholds')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

// ────────────────────────────────────────────────────────────────────
//  Consumer cloud storage billing
//  These mirror the photographer subscription schedule but target
//  user_storage_subscriptions. Each command bails out silently when
//  user_storage_enabled=0, so they're safe to keep scheduled on fresh
//  installs — they only do work once an admin flips the toggle on.
// ────────────────────────────────────────────────────────────────────

// Consumer storage renewals — hourly at :15 (offset from photographer
// renewals at :10 to prevent concurrent Omise API bursts). Looks 24h
// ahead and creates pending invoices + Orders for whichever subs land
// in the window; the payment-gateway cron charges them.
Schedule::command('user-storage:renew-due --hours=24')
    ->hourlyAt(15)
    ->withoutOverlapping()
    ->runInBackground();

// Consumer storage grace expiry — daily at 02:25 (5 min after the
// photographer equivalent). Downgrades subs whose grace_ends_at is
// in the past to the free plan; files stay on disk but further
// uploads are blocked until usage fits the 5 GB free quota.
Schedule::command('user-storage:expire-grace')
    ->dailyAt('02:25')
    ->withoutOverlapping()
    ->runInBackground();

// Consumer storage quota drift healer — weekly Sunday 03:30. SUMs
// user_files.size_bytes per user and rewrites auth_users.storage_used_bytes
// to match. Catches any drift introduced by failed transactions or
// admin tools that bypass FileManagerService. Idempotent.
Schedule::command('user-storage:recalc-quotas')
    ->weeklyOn(0, '03:30')
    ->withoutOverlapping()
    ->runInBackground();

// Photo quality — weekly rescore across active events (Sunday 03:00)
Schedule::command('photos:rescore')
    ->weeklyOn(0, '03:00')
    ->withoutOverlapping()
    ->runInBackground();

// ────────────────────────────────────────────────────────────────────
//  Admin notifications cleanup
//  Trim already-read entries older than 90 days so the bell-icon table
//  doesn't grow forever. Idempotent.
// ────────────────────────────────────────────────────────────────────
Schedule::command('notifications:cleanup --days=90')
    ->dailyAt('02:05')
    ->withoutOverlapping();

// ────────────────────────────────────────────────────────────────────
//  User notifications cleanup
//  Mirror of the admin cleanup, targeting user_notifications (the table
//  shared by photographer + customer bell icons). Same policy: read
//  rows older than 90 days deleted, unread rows older than 1 year
//  deleted regardless. Without this the table grew unbounded since the
//  app's launch — it had no cleanup at all before now.
// ────────────────────────────────────────────────────────────────────
Schedule::command('notifications:cleanup-users --days=90')
    ->dailyAt('02:10')
    ->withoutOverlapping();

// ────────────────────────────────────────────────────────────────────
//  Security scanner — daily 14-check sweep at 04:30 (after rekognition
//  cleanup). `--quiet-if-clean` suppresses output when score == 100;
//  otherwise the scanner pings the admin bell when critical/high
//  issues are found.
// ────────────────────────────────────────────────────────────────────
Schedule::command('security:scan --quiet-if-clean')
    ->dailyAt('04:30')
    ->withoutOverlapping()
    ->runInBackground();

// ────────────────────────────────────────────────────────────────────
//  Threat intelligence cleanup — daily at 04:45. Drops threat_patterns
//  older than 30 days, expired fingerprint blocks, and resolved
//  incidents past the 30-day mark. Bails out silently when AI/threat
//  modules are unused (every table is tolerant of being empty).
// ────────────────────────────────────────────────────────────────────
Schedule::call(function () {
    try {
        app(\App\Services\ThreatIntelligenceService::class)->cleanup(30);
    } catch (\Throwable) {
        // Never break the scheduler over cleanup.
    }
})
    ->dailyAt('04:45')
    ->name('threat-intelligence-cleanup')
    ->withoutOverlapping();

// ────────────────────────────────────────────────────────────────────
//  Geo IP cache pruning — weekly Sunday 04:50. Drops entries older
//  than 30 days. ip-api.com data is cached 24h server-side anyway, so
//  anything older than a month is dead weight that bloats the table.
// ────────────────────────────────────────────────────────────────────
Schedule::call(function () {
    try {
        \Illuminate\Support\Facades\DB::table('geo_ip_cache')
            ->where('cached_at', '<', now()->subDays(30))
            ->delete();
    } catch (\Throwable) {
        // geo_ip_cache table may not exist on fresh installs.
    }
})
    ->weeklyOn(0, '04:50')
    ->name('geo-ip-cache-cleanup')
    ->withoutOverlapping();

// ────────────────────────────────────────────────────────────────────
//  SEO audit — daily 05:00 sweep over critical pages (home, events,
//  blog, photographers, products, contact). Persists the report to
//  app_settings (`seo_audit_report`) and pings the admin bell when
//  any page scores below 60 or carries critical issues.
// ────────────────────────────────────────────────────────────────────
Schedule::command('seo:audit --quiet-if-clean')
    ->dailyAt('05:00')
    ->withoutOverlapping()
    ->runInBackground();

// ────────────────────────────────────────────────────────────────────
//  Payout trigger engine
//  Runs hourly; the engine itself enforces schedule/threshold semantics
//  (daily vs weekly_thu vs monthly_1 etc.). Globally disabled until the
//  admin flips `payout_enabled=1` in Admin → Payout Settings — safe to
//  keep scheduled even on fresh installs. Jobs dispatched from here land
//  on the `payouts` queue so ops can isolate payout workers.
//
//  NOTE: no ->runInBackground() here. Schedule::job() wraps the dispatch
//  in a CallbackEvent (closure), and Laravel throws on background-running
//  closures. We don't need it anyway — the job is queued immediately and
//  the queue worker runs in its own process.
// ────────────────────────────────────────────────────────────────────
Schedule::job(new \App\Jobs\CheckPayoutTriggersJob(), 'payouts')
    ->hourlyAt(5)
    ->withoutOverlapping();

// ────────────────────────────────────────────────────────────────────
//  Async slip re-verification sweeper
//
//  Picks up payment slips that landed in `pending` because the inline
//  SlipOK call was unreachable at upload time, and dispatches a
//  VerifyPaymentSlipJob for each. The job is idempotent (uniqueId per
//  slip), so re-dispatching a slip that's already enqueued or already
//  decided is a no-op.
//
//  Scope:
//    - verify_status='pending'
//    - slipok_trans_ref IS NULL (we only retry slips that didn't get
//      a transRef on the first inline pass)
//    - submitted_at older than 5 min (give the inline path time to win)
//    - submitted_at within last 24h (don't infinitely retry abandoned
//      slips; admin queue picks those up)
//
//  Cadence: every 15 min — fast enough to recover from a 30-min SlipOK
//  outage in 2 sweeps, slow enough that we don't hammer the API.
// ────────────────────────────────────────────────────────────────────
Schedule::call(function () {
    try {
        $slipok = app(\App\Services\Payment\SlipOKService::class);
        if (!$slipok->isEnabled() || !$slipok->isConfigured()) {
            return; // nothing to do without SlipOK creds
        }
        $candidates = \App\Models\PaymentSlip::query()
            ->where('verify_status', 'pending')
            ->whereNull('slipok_trans_ref')
            ->where('created_at', '<', now()->subMinutes(5))
            ->where('created_at', '>', now()->subDay())
            ->limit(50)
            ->pluck('id');
        foreach ($candidates as $slipId) {
            \App\Jobs\Payment\VerifyPaymentSlipJob::dispatch((int) $slipId);
        }
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::warning('slip-reverify-sweeper failed', [
            'error' => $e->getMessage(),
        ]);
    }
})
    ->everyFifteenMinutes()
    ->name('slip-reverify-sweeper')
    ->withoutOverlapping();

