<?php

namespace App\Services;

use App\Models\AdminNotification;
use App\Models\AlertEvent;
use App\Models\AlertRule;
use App\Models\BusinessExpense;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Reads every metric we can evaluate + fires alerts through admin/email/LINE/push.
 *
 * Scheduled via `alerts:check` every 5 min (or ad-hoc via admin "ทดสอบ" button).
 * Idempotent per-rule via last_triggered_at + cooldown_minutes.
 */
class AlertEvaluatorService
{
    public function __construct(
        private CapacityPlannerService $capacity,
        private SystemMonitorService $monitor,
    ) {}

    /**
     * Catalogue of every metric admins can build a rule on.
     * Each entry: label (Thai), unit, source (for docs).
     */
    public static function metrics(): array
    {
        return [
            'disk_used_pct'       => ['label' => 'Disk ใช้งาน (%)',             'unit' => '%',   'src' => 'SystemMonitor'],
            'cpu_pct'             => ['label' => 'CPU load (%)',                'unit' => '%',   'src' => 'SystemMonitor'],
            'memory_pct'          => ['label' => 'RAM (PHP process) (%)',       'unit' => '%',   'src' => 'SystemMonitor'],
            'db_connections_pct'  => ['label' => 'DB connections (%)',          'unit' => '%',   'src' => 'SystemMonitor'],
            'queue_pending'       => ['label' => 'Queue pending jobs',          'unit' => 'jobs','src' => 'jobs table'],
            'queue_failed_24h'    => ['label' => 'Failed jobs (24h)',           'unit' => 'jobs','src' => 'failed_jobs'],
            'online_users'        => ['label' => 'ผู้ใช้ออนไลน์',                 'unit' => 'คน',  'src' => 'user_sessions'],
            'capacity_util_pct'   => ['label' => 'ใช้ capacity (%)',            'unit' => '%',   'src' => 'CapacityPlanner'],
            'pending_slips'       => ['label' => 'สลิปรอตรวจ',                   'unit' => 'ใบ', 'src' => 'payment_slips'],
            'pending_orders'      => ['label' => 'ออเดอร์รอยืนยัน',              'unit' => 'ออเดอร์','src' => 'orders'],
            'monthly_expense_thb' => ['label' => 'รายจ่าย/เดือน (บาท)',          'unit' => 'THB', 'src' => 'business_expenses'],
            'flagged_photos'      => ['label' => 'รูปถูก flag รอตรวจ',          'unit' => 'รูป', 'src' => 'event_photos'],
            'new_users_24h'       => ['label' => 'ผู้ใช้ใหม่ (24h)',             'unit' => 'คน',  'src' => 'auth_users'],

            // ── Security & business-health metrics (added 2026-05-02) ──
            // Security: brute-force attempts on /admin/login. Catches both
            // mass scans and targeted attacks before they breach an account.
            'failed_admin_logins_24h' => ['label' => 'Admin login ล้มเหลว (24h)','unit' => 'ครั้ง','src' => 'security_login_attempts'],
            // Revenue: provider/gateway dropped a transaction (Stripe/Omise/PromptPay).
            // A spike usually means the provider is having issues — admin should
            // check the provider's status page before customers complain.
            'failed_payments_24h'     => ['label' => 'Payment ล้มเหลว (24h)',   'unit' => 'ครั้ง','src' => 'payment_transactions'],
            // Customer trust: refund requests waiting for admin decision.
            // Hitting 5+ pending typically means SLA is slipping → unhappy users.
            'pending_refunds'         => ['label' => 'คำขอคืนเงินรอตอบ',        'unit' => 'คำขอ','src' => 'refund_requests'],
            // Business health: zero orders in the last 24h is a "the lights
            // are still on but no one's buying" signal — could be a pricing
            // page bug, payment outage, or marketing pause. Use operator <=
            // so the rule fires when the number DROPS below threshold.
            'orders_today_count'      => ['label' => 'ออเดอร์ใน 24h',           'unit' => 'ออเดอร์','src' => 'orders'],
            // Admin awareness: largest single order in the last hour.
            // Useful for big-ticket events (e.g. studio bookings) so admin
            // can ensure the photographer is notified + slip is verified
            // promptly. Threshold is configurable per business.
            'highest_order_amount_1h' => ['label' => 'ออเดอร์มูลค่าสูงสุด (1h)','unit' => 'THB','src' => 'orders'],

            // ── Payment & Payout health (added 2026-04-29) ─────────────────
            //
            // Why these metrics matter:
            //   pending_payouts_count is the per-batch backlog — how many
            //   PhotographerPayout rows are stuck in 'pending' for >24h
            //   awaiting disbursement. A growing number means the payout
            //   engine is starved (provider down, scheduler dead, threshold
            //   too high to ever trigger).
            //   failed_disbursements_24h catches systemic provider failures
            //   that the per-disbursement notifyPhotographer hook only sees
            //   one at a time.
            //   stuck_slips_hours is the SLA clock for the manual-review
            //   queue — admin should act within N hours of the oldest
            //   pending slip, this surfaces when that's slipping.
            //   line_failed_deliveries_24h backs up the per-job escalation
            //   in SendLinePushJob.failed() with a windowed count so a
            //   sudden burst (LINE API outage) is caught even when the
            //   per-order dedup quiets the individual escalations.
            //   admin_email_failures_24h surfaces SMTP/relay misconfig.
            'pending_payouts_count'      => ['label' => 'Payouts รอจ่ายเกิน 24h',     'unit' => 'รายการ', 'src' => 'photographer_payouts'],
            'failed_disbursements_24h'   => ['label' => 'Disbursement ล้มเหลว (24h)',  'unit' => 'รายการ', 'src' => 'photographer_disbursements'],
            'stuck_slips_hours'          => ['label' => 'อายุสลิปเก่าสุดที่รอตรวจ',     'unit' => 'ชม.',   'src' => 'payment_slips'],
            'line_failed_deliveries_24h' => ['label' => 'LINE ส่งล้มเหลว (24h)',         'unit' => 'รายการ', 'src' => 'line_deliveries'],
            'admin_email_failures_24h'   => ['label' => 'Email ล้มเหลว (24h)',           'unit' => 'รายการ', 'src' => 'email_logs'],
        ];
    }

    // ═══════════════════════════════════════════════════════════════════
    //  Metric value fetcher — one big switch
    // ═══════════════════════════════════════════════════════════════════

    public function currentValue(string $metric): ?float
    {
        try {
            return match ($metric) {
                'disk_used_pct'       => (float) ($this->capacity->serverSpecs()['disk_used_pct'] ?? 0),
                'cpu_pct'             => (float) ($this->capacity->currentLoad()['cpu_pct'] ?? 0),
                'memory_pct'          => (float) ($this->capacity->currentLoad()['memory_pct'] ?? 0),
                'db_connections_pct'  => $this->dbConnectionsPct(),
                'queue_pending'       => (float) $this->safeCount('jobs'),
                'queue_failed_24h'    => (float) $this->safeCount('failed_jobs', fn ($q) => $q->where('failed_at', '>=', now()->subDay())),
                'online_users'        => (float) ($this->capacity->currentLoad()['online_users'] ?? 0),
                'capacity_util_pct'   => $this->capacityUtilPct(),
                'pending_slips'       => (float) $this->safeCount('payment_slips', fn ($q) => $q->where('verify_status', 'pending')),
                'pending_orders'      => (float) $this->safeCount('orders', fn ($q) => $q->whereIn('status', ['pending_payment', 'pending_review'])),
                'monthly_expense_thb' => (float) (BusinessExpense::active()->get()->sum(fn ($e) => $e->monthlyCost()) ?: 0),
                'flagged_photos'      => (float) $this->safeCount('event_photos', fn ($q) => $q->whereIn('moderation_status', ['flagged', 'pending'])->where('status', 'active')),
                'new_users_24h'       => (float) $this->safeCount('auth_users', fn ($q) => $q->where('created_at', '>=', now()->subDay())),

                // Payouts older than 24h still pending — backlog signal.
                'pending_payouts_count' => (float) $this->safeCount('photographer_payouts', fn ($q) =>
                    $q->where('status', 'pending')
                      ->where('created_at', '<=', now()->subDay())
                ),
                // Disbursements that flipped to failed in the last 24h —
                // captures provider rejection bursts.
                'failed_disbursements_24h' => (float) $this->safeCount('photographer_disbursements', fn ($q) =>
                    $q->where('status', 'failed')
                      ->where('updated_at', '>=', now()->subDay())
                ),
                // Oldest pending slip's age in hours. Returns 0 when queue is
                // empty so the alert doesn't false-fire on a healthy system.
                'stuck_slips_hours' => $this->oldestPendingSlipAgeHours(),
                // LINE delivery failures (24h window).
                'line_failed_deliveries_24h' => (float) $this->safeCount('line_deliveries', fn ($q) =>
                    $q->where('status', 'failed')
                      ->where('created_at', '>=', now()->subDay())
                ),
                // Email send failures from the email_logs audit.
                'admin_email_failures_24h' => (float) $this->safeCount('email_logs', fn ($q) =>
                    $q->where('status', 'failed')
                      ->where('created_at', '>=', now()->subDay())
                ),

                // Security: failed login attempts in last 24h. Both admin
                // and user attempts roll up here — a sudden spike is the
                // signal regardless of which guard is being targeted.
                // Note: table column is `attempted_at`, not `created_at`.
                'failed_admin_logins_24h' => (float) $this->safeCount('security_login_attempts', fn ($q) =>
                    $q->where('success', false)
                      ->where('attempted_at', '>=', now()->subDay())
                ),
                // Revenue: payment transactions that flipped to failed in
                // the last 24h. Captures gateway issues + customer card
                // declines — spike = provider problem, low = card issue.
                'failed_payments_24h' => (float) $this->safeCount('payment_transactions', fn ($q) =>
                    $q->where('status', 'failed')
                      ->where('updated_at', '>=', now()->subDay())
                ),
                // Customer trust: refunds awaiting admin decision. SLA
                // typically wants this < 5 outstanding at any time.
                'pending_refunds' => (float) $this->safeCount('refund_requests', fn ($q) =>
                    $q->where('status', 'pending')
                ),
                // Business health: orders placed in last 24h. Use operator
                // `<=` on the alert rule to fire when this DROPS below a
                // floor (e.g. 0 = something's broken, the funnel is dead).
                'orders_today_count' => (float) $this->safeCount('orders', fn ($q) =>
                    $q->where('created_at', '>=', now()->subDay())
                ),
                // Admin awareness: largest single-order value in last hour.
                // Helps admins spot big-ticket sales for white-glove follow
                // up (slip approval, photographer notification). Returns 0
                // when there are no orders — alert won't fire on quiet hour.
                'highest_order_amount_1h' => (float) (
                    Schema::hasTable('orders')
                        ? (DB::table('orders')
                            ->where('created_at', '>=', now()->subHour())
                            ->max('total') ?? 0)
                        : 0
                ),
                default               => null,
            };
        } catch (\Throwable $e) {
            Log::warning('AlertEvaluator metric fetch failed', ['metric' => $metric, 'error' => $e->getMessage()]);
            return null;
        }
    }

    protected function dbConnectionsPct(): float
    {
        $max = $this->capacity->serverSpecs()['mysql_max_conn'] ?? 150;
        if ($max <= 0) return 0;
        $current = (int) ($this->capacity->currentLoad()['db_connections'] ?? 0);
        return round($current / $max * 100, 2);
    }

    protected function capacityUtilPct(): float
    {
        $safe = $this->capacity->capacityEstimate()['safe_concurrent'] ?? 0;
        if ($safe <= 0) return 0;
        $online = (int) ($this->capacity->currentLoad()['online_users'] ?? 0);
        return round($online / $safe * 100, 2);
    }

    /**
     * Age in hours of the oldest pending payment_slip. Returns 0 when the
     * queue is empty so a healthy system never trips a "stuck slips" rule
     * just because the metric is undefined.
     */
    protected function oldestPendingSlipAgeHours(): float
    {
        try {
            if (!Schema::hasTable('payment_slips')) return 0.0;
            $row = DB::table('payment_slips')
                ->where('verify_status', 'pending')
                ->min('created_at');
            if (!$row) return 0.0;
            $oldest = \Carbon\Carbon::parse($row);
            return round($oldest->diffInMinutes(now()) / 60, 2);
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    //  Rule evaluator
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Walk every active rule, evaluate, dispatch notifications.
     *
     * Semantics (state machine, fire-once-per-episode):
     *   (a) firing=false + matches → NEW EPISODE → fire + firing=true
     *   (b) firing=true  + matches → suppressed (admin already acknowledged once)
     *   (c) firing=true  + !matches → AUTO-RESOLVE → firing=false + dismiss open
     *                                 admin notifications so the bell clears
     *   (d) firing=false + !matches → idle (no-op)
     *
     * This replaces the old "fire every cooldown_minutes if condition persists"
     * behavior, which nagged admins repeatedly even after they acknowledged
     * the alert. Cooldown_minutes is now only used as a *safety floor* between
     * back-to-back episodes (condition flaps below→above→below→above).
     *
     * @return array{checked:int, triggered:int, skipped_cooldown:int, resolved:int, details:array}
     */
    public function run(): array
    {
        $rules = AlertRule::active()->get();
        $triggered = 0;
        $skipped   = 0;
        $resolved  = 0;
        $details   = [];

        foreach ($rules as $rule) {
            $value = $this->currentValue($rule->metric);
            $rule->last_checked_at = now();

            if ($value === null) {
                $rule->save();
                $details[] = ['rule' => $rule->name, 'status' => 'metric_unavailable'];
                continue;
            }

            $rule->last_value = $value;
            $breaching = $rule->matches($value);

            if ($breaching && !$rule->firing) {
                // (a) New firing episode — but still respect cooldown as a flap-floor
                if (!$rule->canTrigger()) {
                    $skipped++;
                    $rule->save();
                    $details[] = ['rule' => $rule->name, 'status' => 'cooldown'];
                    continue;
                }

                $sent = $this->dispatch($rule, $value);
                $rule->firing            = true;
                $rule->last_triggered_at = now();
                $rule->resolved_at       = null;
                $rule->save();

                AlertEvent::create([
                    'rule_id'       => $rule->id,
                    'triggered_at'  => now(),
                    'value'         => $value,
                    'severity'      => $rule->severity,
                    'channels_sent' => $sent,
                ]);

                $triggered++;
                $details[] = ['rule' => $rule->name, 'status' => 'triggered', 'value' => $value, 'channels' => $sent];
                continue;
            }

            if ($breaching && $rule->firing) {
                // (b) Already firing — suppress duplicate notifications
                $rule->save();
                $details[] = ['rule' => $rule->name, 'status' => 'suppressed_firing', 'value' => $value];
                continue;
            }

            if (!$breaching && $rule->firing) {
                // (c) Condition cleared — auto-resolve
                $rule->firing      = false;
                $rule->resolved_at = now();
                $rule->save();

                $this->dismissOpenNotifications($rule);
                $this->logResolved($rule, $value);

                $resolved++;
                $details[] = ['rule' => $rule->name, 'status' => 'resolved', 'value' => $value];
                continue;
            }

            // (d) Idle — below threshold, not firing
            $rule->save();
        }

        return [
            'checked'          => $rules->count(),
            'triggered'        => $triggered,
            'skipped_cooldown' => $skipped,
            'resolved'         => $resolved,
            'details'          => $details,
        ];
    }

    /**
     * Mark every unread admin notification for this rule as read + silently
     * remove the bell-pulse so the admin doesn't keep staring at a stale
     * "DISK FULL" that was already fixed.
     */
    protected function dismissOpenNotifications(AlertRule $rule): void
    {
        try {
            AdminNotification::where('type', 'ilike', 'alert.%')
                ->where('ref_id', (string) $rule->id)
                ->where('is_read', false)
                ->update([
                    'is_read' => true,
                    'read_at' => now(),
                ]);
        } catch (\Throwable $e) {
            Log::warning('Alert auto-dismiss failed', ['rule' => $rule->id, 'err' => $e->getMessage()]);
        }
    }

    /**
     * Record the resolution as an AlertEvent row for audit trail.
     * We also post a "resolved" admin notification so the history is visible
     * but keep it low-priority (not a toast).
     */
    protected function logResolved(AlertRule $rule, float $value): void
    {
        try {
            AlertEvent::create([
                'rule_id'       => $rule->id,
                'triggered_at'  => now(),
                'value'         => $value,
                'severity'      => 'info',
                'channels_sent' => [],
                'note'          => 'Auto-resolved (condition cleared)',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Alert resolution log failed', ['rule' => $rule->id, 'err' => $e->getMessage()]);
        }
    }

    /** Manually fire a rule for admin "ทดสอบ" button (bypasses cooldown). */
    public function trigger(AlertRule $rule, ?float $overrideValue = null): array
    {
        $value = $overrideValue ?? ($this->currentValue($rule->metric) ?? 0);
        $sent = $this->dispatch($rule, $value, testMode: true);

        AlertEvent::create([
            'rule_id'       => $rule->id,
            'triggered_at'  => now(),
            'value'         => $value,
            'severity'      => $rule->severity,
            'channels_sent' => $sent,
            'note'          => 'Manual test trigger',
        ]);

        return ['value' => $value, 'channels_sent' => $sent];
    }

    // ═══════════════════════════════════════════════════════════════════
    //  Channel dispatchers
    // ═══════════════════════════════════════════════════════════════════

    protected function dispatch(AlertRule $rule, float $value, bool $testMode = false): array
    {
        $channels = $rule->channels ?? ['admin'];
        $sent = [];

        $title = ($testMode ? '[ทดสอบ] ' : '') . '⚠ Alert: ' . $rule->name;
        $metricLabel = static::metrics()[$rule->metric]['label'] ?? $rule->metric;
        $unit = static::metrics()[$rule->metric]['unit'] ?? '';
        $message = sprintf(
            '%s = %s %s (threshold %s %s %s)',
            $metricLabel,
            rtrim(rtrim(number_format($value, 2), '0'), '.'),
            $unit,
            $rule->operator,
            rtrim(rtrim(number_format((float) $rule->threshold, 2), '0'), '.'),
            $unit
        );

        foreach ($channels as $ch) {
            try {
                match ($ch) {
                    'admin' => $this->sendAdmin($rule, $title, $message),
                    'email' => $this->sendEmail($rule, $title, $message),
                    'line'  => $this->sendLine($rule, $title, $message),
                    'push'  => $this->sendPush($rule, $title, $message),
                    default => null,
                };
                $sent[] = $ch;
            } catch (\Throwable $e) {
                Log::warning('Alert dispatch failed', ['rule' => $rule->id, 'channel' => $ch, 'error' => $e->getMessage()]);
            }
        }

        return $sent;
    }

    protected function sendAdmin(AlertRule $rule, string $title, string $message): void
    {
        // NB: store a RELATIVE path — the admin-notifications.js bell dropdown
        // prepends baseUrl + '/' when it opens the link. Using route() here
        // would produce an absolute URL and get double-prefixed to
        // "http://host/http://host/admin/..." → 404.
        AdminNotification::create([
            'type'    => 'alert.' . $rule->severity,
            'title'   => $title,
            'message' => $message,
            'link'    => 'admin/alerts/events?rule=' . $rule->id,
            'ref_id'  => (string) $rule->id,
            'is_read' => false,
        ]);
    }

    protected function sendEmail(AlertRule $rule, string $title, string $message): void
    {
        if (!class_exists(\App\Services\MailService::class)) return;
        $to = (string) (\App\Models\AppSetting::get('admin_email') ?? config('mail.from.address'));
        if (!$to) return;

        $html = '<h2>' . e($title) . '</h2>'
              . '<p>' . e($message) . '</p>'
              . '<p><a href="' . route('admin.alerts.events') . '">ดูประวัติ Alert</a></p>';
        app(\App\Services\MailService::class)->send($to, $title, $html, 'alert');
    }

    protected function sendLine(AlertRule $rule, string $title, string $message): void
    {
        if (!class_exists(\App\Services\LineNotifyService::class)) return;
        app(\App\Services\LineNotifyService::class)->notifyAdmin($title . "\n" . $message);
    }

    protected function sendPush(AlertRule $rule, string $title, string $message): void
    {
        // Web Push requires an existing campaign; for alerts we just log that it would be sent
        // (admins subscribe once to alerts channel — we store as admin notification instead)
        $this->sendAdmin($rule, $title, $message);
    }

    // ═══════════════════════════════════════════════════════════════════
    //  Helpers
    // ═══════════════════════════════════════════════════════════════════

    protected function safeCount(string $table, ?\Closure $query = null): int
    {
        try {
            if (!Schema::hasTable($table)) return 0;
            $q = DB::table($table);
            if ($query) $query($q);
            return (int) $q->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
