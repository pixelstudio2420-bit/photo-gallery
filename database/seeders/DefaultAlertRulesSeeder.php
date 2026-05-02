<?php

namespace Database\Seeders;

use App\Models\AlertRule;
use Illuminate\Database\Seeder;

/**
 * Seeds the essential 16 alert rules every production install needs.
 *
 * Why a seeded baseline?
 * ──────────────────────
 * Before this seeder, AlertRule was an empty table on fresh installs and
 * admins were expected to know which metrics to watch. In practice that
 * meant most installs ran with NO alerts at all until a customer-impact
 * incident woke someone up. Seeding a sane baseline solves "you don't
 * know what you don't know" — admins can always edit/disable individual
 * rules in `admin/alerts/rules` once they understand their workload.
 *
 * Categories
 * ──────────
 *   • Infrastructure  — disk/CPU/memory/DB/queue
 *   • Money & Payouts — pending payouts, failed disbursements, slip backlog
 *   • Customer trust  — LINE delivery failures, email failures
 *   • Abuse / spam    — sudden user-signup bursts, mass photo flagging
 *
 * Severity guide
 * ──────────────
 *   info     — informational, doesn't wake anyone up. Used sparingly.
 *   warn     — needs admin attention within the day.
 *   critical — page someone now; customer impact in progress.
 *
 * Idempotent: each rule is keyed on `name`, so reseeding updates rather
 * than duplicates. Existing edits to threshold/cooldown/channels are
 * preserved (we only updateOrInsert the description + active flag).
 */
class DefaultAlertRulesSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            // ── Infrastructure ──────────────────────────────────────────────
            [
                'name'             => 'Disk เต็ม (warn 80%)',
                'description'      => 'ดิสก์ใช้งานเกิน 80% — ควรล้าง backup เก่า/ขยาย volume ก่อนถึง 90%',
                'metric'           => 'disk_used_pct',
                'operator'         => '>=',
                'threshold'        => 80,
                'severity'         => 'warn',
                'channels'         => ['admin', 'email'],
                'cooldown_minutes' => 360, // 6h — disk fill is slow, no need to spam
            ],
            [
                'name'             => 'Disk เต็ม (critical 92%)',
                'description'      => 'ดิสก์ใช้งานเกิน 92% — เสี่ยง upload ล้มเหลว, เซิร์ฟเวอร์อาจดับ',
                'metric'           => 'disk_used_pct',
                'operator'         => '>=',
                'threshold'        => 92,
                'severity'         => 'critical',
                'channels'         => ['admin', 'email', 'line'],
                'cooldown_minutes' => 60,
            ],
            [
                'name'             => 'CPU สูง (90%)',
                'description'      => 'CPU โหลดเกิน 90% นานต่อเนื่อง — อาจเกิด cron แย่ง resource หรือ traffic spike',
                'metric'           => 'cpu_pct',
                'operator'         => '>=',
                'threshold'        => 90,
                'severity'         => 'warn',
                'channels'         => ['admin', 'email'],
                'cooldown_minutes' => 30,
            ],
            [
                'name'             => 'RAM สูง (85%)',
                'description'      => 'RAM PHP process เกิน 85% — บางคำสั่งอาจ OOM',
                'metric'           => 'memory_pct',
                'operator'         => '>=',
                'threshold'        => 85,
                'severity'         => 'warn',
                'channels'         => ['admin', 'email'],
                'cooldown_minutes' => 60,
            ],
            [
                'name'             => 'DB connections ใกล้เต็ม (80%)',
                'description'      => 'Postgres connection pool > 80% — เริ่มมีคำขอรอ, อาจต้องเพิ่ม max_connections',
                'metric'           => 'db_connections_pct',
                'operator'         => '>=',
                'threshold'        => 80,
                'severity'         => 'warn',
                'channels'         => ['admin', 'email'],
                'cooldown_minutes' => 30,
            ],
            [
                'name'             => 'Queue คั่งค้าง (>500)',
                'description'      => 'Pending jobs เกิน 500 — worker ตามไม่ทัน, photo processing/email ดีเลย์',
                'metric'           => 'queue_pending',
                'operator'         => '>',
                'threshold'        => 500,
                'severity'         => 'warn',
                'channels'         => ['admin', 'email'],
                'cooldown_minutes' => 30,
            ],
            [
                'name'             => 'Queue พังเยอะ (24h > 50)',
                'description'      => 'Failed jobs ใน 24 ชม. > 50 รายการ — มี job ชนิดใดชนิดหนึ่งพังบ่อย, ตรวจ failed_jobs',
                'metric'           => 'queue_failed_24h',
                'operator'         => '>',
                'threshold'        => 50,
                'severity'         => 'warn',
                'channels'         => ['admin', 'email'],
                'cooldown_minutes' => 360,
            ],

            // ── Money & Payouts ─────────────────────────────────────────────
            [
                'name'             => 'สลิปรอตรวจคั่งค้าง (>20)',
                'description'      => 'มีสลิปรอตรวจมากกว่า 20 ใบ — auto-approve อาจไม่ทำงาน หรือ admin ยังไม่ได้เข้ามาพิจารณา',
                'metric'           => 'pending_slips',
                'operator'         => '>',
                'threshold'        => 20,
                'severity'         => 'warn',
                'channels'         => ['admin', 'email'],
                'cooldown_minutes' => 120,
            ],
            [
                'name'             => 'สลิปเก่าค้างนาน (>12 ชม.)',
                'description'      => 'มีสลิปที่รอตรวจมากกว่า 12 ชั่วโมง — ลูกค้ารอเข้าใช้งานนานเกินไป, SLA หลุด',
                'metric'           => 'stuck_slips_hours',
                'operator'         => '>',
                'threshold'        => 12,
                'severity'         => 'critical',
                'channels'         => ['admin', 'email', 'line'],
                'cooldown_minutes' => 240,
            ],
            [
                'name'             => 'Payout ค้างจ่าย (>30 รายการ)',
                'description'      => 'ช่างภาพมียอดรอจ่ายเกิน 30 รายการที่อายุเกิน 24 ชม. — engine อาจไม่รัน, threshold ตั้งสูงเกินไป',
                'metric'           => 'pending_payouts_count',
                'operator'         => '>',
                'threshold'        => 30,
                'severity'         => 'warn',
                'channels'         => ['admin', 'email'],
                'cooldown_minutes' => 180,
            ],
            [
                'name'             => 'Disbursement ล้มเหลว (24h > 5)',
                'description'      => 'มีการโอนเงินล้มเหลวเกิน 5 รายการใน 24 ชม. — provider/PromptPay มีปัญหา ตรวจ raw_response ของ disbursement',
                'metric'           => 'failed_disbursements_24h',
                'operator'         => '>',
                'threshold'        => 5,
                'severity'         => 'critical',
                'channels'         => ['admin', 'email', 'line'],
                'cooldown_minutes' => 120,
            ],

            // ── Customer trust (delivery channels) ──────────────────────────
            [
                'name'             => 'LINE ส่งล้มเหลวต่อเนื่อง (24h > 30)',
                'description'      => 'มีการส่ง LINE ล้มเหลวเกิน 30 รายการใน 24 ชม. — ลูกค้าอาจไม่ได้รับรูป/ข้อมูลโอนเงิน',
                'metric'           => 'line_failed_deliveries_24h',
                'operator'         => '>',
                'threshold'        => 30,
                'severity'         => 'warn',
                'channels'         => ['admin', 'email'],
                'cooldown_minutes' => 120,
            ],
            [
                'name'             => 'อีเมลส่งล้มเหลว (24h > 20)',
                'description'      => 'อีเมล ≥ 20 รายการล้มเหลวใน 24 ชม. — SMTP อาจล่ม, IP โดน blacklist, หรือ daily quota หมด',
                'metric'           => 'admin_email_failures_24h',
                'operator'         => '>',
                'threshold'        => 20,
                'severity'         => 'warn',
                'channels'         => ['admin', 'line'], // not email — same channel as the failure
                'cooldown_minutes' => 240,
            ],

            // ── Abuse & content moderation ──────────────────────────────────
            [
                'name'             => 'รูปถูก flag คั่ง (>30)',
                'description'      => 'รูปรอตรวจ flagged > 30 — ตรวจ moderation queue, อาจมี spam upload',
                'metric'           => 'flagged_photos',
                'operator'         => '>',
                'threshold'        => 30,
                'severity'         => 'warn',
                'channels'         => ['admin', 'email'],
                'cooldown_minutes' => 240,
            ],
            [
                'name'             => 'สมัครใหม่พุ่ง (24h > 500)',
                'description'      => 'ผู้ใช้ใหม่ใน 24 ชม. > 500 — อาจเป็น bot signup, ตรวจ rate-limit/CAPTCHA',
                'metric'           => 'new_users_24h',
                'operator'         => '>',
                'threshold'        => 500,
                'severity'         => 'warn',
                'channels'         => ['admin', 'email'],
                'cooldown_minutes' => 360,
            ],

            // ── Capacity / scaling ──────────────────────────────────────────
            [
                'name'             => 'Capacity ใกล้เต็ม (85%)',
                'description'      => 'การใช้ capacity เกิน 85% ของ safe_concurrent — เริ่มต้องคิดเรื่องสเกล',
                'metric'           => 'capacity_util_pct',
                'operator'         => '>=',
                'threshold'        => 85,
                'severity'         => 'info',
                'channels'         => ['admin'],
                'cooldown_minutes' => 360,
            ],

            // ── Security ───────────────────────────────────────────────────
            // Brute-force detection. >50 failed attempts in 24h = active
            // attack OR a user keeps fat-fingering their password — either
            // way admin should look. Cloudflare WAF rate-limits the login
            // path at edge, so any volume that gets THIS far is the guarded
            // signal worth alerting on.
            [
                'name'             => 'Login ล้มเหลวพุ่ง (24h > 50)',
                'description'      => 'มี Login ล้มเหลว >50 ครั้งใน 24 ชั่วโมง — อาจเป็น brute-force หรือมีคนล็อคบัญชีตัวเอง',
                'metric'           => 'failed_admin_logins_24h',
                'operator'         => '>=',
                'threshold'        => 50,
                'severity'         => 'warn',
                'channels'         => ['admin', 'email', 'line'],
                'cooldown_minutes' => 120,
            ],

            // ── Revenue / payment health ───────────────────────────────────
            // Failed transactions in 24h. >10 = either provider is down
            // or customers have widespread card issues. Both warrant
            // admin awareness within the hour.
            [
                'name'             => 'Payment ล้มเหลวพุ่ง (24h > 10)',
                'description'      => 'มี Payment transaction ล้มเหลว >10 ครั้งใน 24 ชั่วโมง — ตรวจสอบสถานะ Stripe/Omise/PromptPay',
                'metric'           => 'failed_payments_24h',
                'operator'         => '>=',
                'threshold'        => 10,
                'severity'         => 'warn',
                'channels'         => ['admin', 'email', 'line'],
                'cooldown_minutes' => 60,
            ],

            // ── Customer trust ─────────────────────────────────────────────
            // Refunds piling up = SLA slipping. Trigger early so admin
            // catches them before customer escalates to social media.
            [
                'name'             => 'คำขอคืนเงินคั่งค้าง (>5)',
                'description'      => 'มีคำขอคืนเงิน >5 รายการรอตอบ — ตอบช้า = ลูกค้าโกรธ + เสี่ยงรีวิวเสีย',
                'metric'           => 'pending_refunds',
                'operator'         => '>=',
                'threshold'        => 5,
                'severity'         => 'warn',
                'channels'         => ['admin', 'email'],
                'cooldown_minutes' => 240,
            ],

            // ── Business pulse — low / no orders ───────────────────────────
            // Reverse alert: fires when value DROPS below threshold (operator
            // <=). Zero orders in a day on a live marketplace usually means
            // checkout flow is broken. Off by default — admin should set
            // a baseline (e.g. <= 1 order in 24h) once they know their
            // typical volume. cooldown is high so this doesn't spam during
            // genuinely quiet days (e.g. holidays).
            [
                'name'             => 'ออเดอร์เงียบ (24h ≤ 0)',
                'description'      => 'ไม่มีออเดอร์เลยใน 24 ชั่วโมง — ตรวจ checkout flow / payment ว่ายังทำงานไหม (ปิดไว้ default — ปรับ threshold ตามปริมาณจริง)',
                'metric'           => 'orders_today_count',
                'operator'         => '<=',
                'threshold'        => 0,
                'severity'         => 'warn',
                'channels'         => ['admin', 'email'],
                'cooldown_minutes' => 720,    // 12h — don't spam at midnight
                'is_active'        => false,  // admin must enable explicitly
            ],

            // ── Admin awareness — big-ticket order ─────────────────────────
            // Single order > 5,000 baht in last hour. Useful for white-glove
            // follow-up (verify slip ASAP, notify photographer, double-check
            // event delivery). Not a problem — informational, info severity,
            // doesn't wake anyone at 3am (no LINE/email channel).
            [
                'name'             => 'ออเดอร์มูลค่าสูง (>฿5,000)',
                'description'      => 'มีออเดอร์ใหญ่ใน 1 ชั่วโมงที่ผ่านมา — ตรวจสลิปและแจ้งช่างภาพให้เร็ว',
                'metric'           => 'highest_order_amount_1h',
                'operator'         => '>=',
                'threshold'        => 5000,
                'severity'         => 'info',
                'channels'         => ['admin'],
                'cooldown_minutes' => 60,
            ],
        ];

        $created = 0; $updated = 0;
        foreach ($rules as $rule) {
            $existing = AlertRule::where('name', $rule['name'])->first();
            if ($existing) {
                // Don't overwrite admin's manual edits to threshold / channels
                // / cooldown — only refresh the description so seed-shipped
                // copy stays current.
                $existing->update([
                    'description' => $rule['description'],
                ]);
                $updated++;
            } else {
                // Defaults come FIRST so per-rule `is_active` (e.g. the
                // "quiet orders" rule that ships disabled) actually wins.
                // array_merge: later values overwrite earlier with same key.
                AlertRule::create(array_merge([
                    'is_active' => true,
                    'firing'    => false,
                ], $rule));
                $created++;
            }
        }

        $this->command?->info("AlertRules: {$created} created, {$updated} description-refreshed");
        $this->command?->info('  Edit at: /admin/alerts/rules');
    }
}
