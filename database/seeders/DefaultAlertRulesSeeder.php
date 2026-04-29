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
                AlertRule::create(array_merge($rule, [
                    'is_active' => true,
                    'firing'    => false,
                ]));
                $created++;
            }
        }

        $this->command?->info("AlertRules: {$created} created, {$updated} description-refreshed");
        $this->command?->info('  Edit at: /admin/alerts/rules');
    }
}
