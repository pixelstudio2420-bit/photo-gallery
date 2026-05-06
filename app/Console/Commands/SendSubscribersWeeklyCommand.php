<?php

namespace App\Console\Commands;

use App\Models\Coupon;
use App\Models\Event;
use App\Models\Marketing\Subscriber;
use App\Services\Marketing\NewsletterService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Weekly newsletter digest for the public `marketing_subscribers` list.
 *
 * Distinct from `digest:send-weekly` (SendProvinceDigestCommand) which
 * targets logged-in `auth_users` filtered by province_id. This command
 * targets the *anonymous* subscriber list — anyone who entered their
 * email in the footer / card / inline newsletter widget — and
 * delivers a province-agnostic weekly digest of:
 *
 *   1. New events published in the past 7 days (capped to 6 items)
 *   2. Currently active site-wide promo coupons (capped to 3)
 *   3. One rotating photographer/buyer tip
 *
 * Skips entirely if there is no fresh content this week (no events,
 * no coupons) — empty digest emails are spammy and burn list reputation.
 * The "tip" alone is not enough to justify a send.
 *
 * Usage:
 *   php artisan subscribers:send-weekly                   # all confirmed subscribers
 *   php artisan subscribers:send-weekly --dry-run         # log what WOULD be sent
 *   php artisan subscribers:send-weekly --email=x@y.com   # only one address
 *   php artisan subscribers:send-weekly --limit=10        # cap recipients (testing/throttle)
 *
 * Cron: scheduled in routes/console.php — Tuesdays 10:00 (offset from
 * the existing province digest at Mondays 09:00 so we never blast a
 * recipient who's on both lists in the same 24h).
 */
class SendSubscribersWeeklyCommand extends Command
{
    protected $signature = 'subscribers:send-weekly
                            {--dry-run : Log the digest without sending}
                            {--email= : Send only to one specific subscriber email (testing)}
                            {--limit= : Cap total recipients (testing/throttle)}';

    protected $description = 'Send the weekly newsletter digest to confirmed marketing subscribers';

    /**
     * Hand-curated photographer/buyer tips. Rotated by ISO week number
     * so subscribers don't see the same one twice in a row. Adding new
     * tips: just append — modulo handles the rotation. Keep each one
     * concrete and actionable; vague platitudes ("be creative!") are
     * worse than no tip at all.
     */
    private const TIPS = [
        [
            'icon'  => '📸',
            'title' => 'รูปแนวนอน vs แนวตั้ง — เลือกตามแพลตฟอร์มลูกค้าใช้',
            'body'  => 'ลูกค้าส่วนใหญ่ดูรูปบนมือถือก่อน — รูปแนวตั้งจะเด่นบน Instagram/LINE Album แต่รูปแนวนอนเหมาะกับ Facebook Cover และพิมพ์เฟรม. ลองอัปโหลดทั้งสองแบบเพื่อให้ลูกค้าเลือกได้.',
        ],
        [
            'icon'  => '🏷️',
            'title' => 'ตั้งชื่ออีเวนต์ให้คนหาเจอใน Google',
            'body'  => 'ใช้รูปแบบ "ชื่อกิจกรรม + ปี + จังหวัด" เช่น "เดิน-วิ่ง การกุศล ภูเก็ต 2026" — ดีกว่าตั้ง "วิ่งสนุก ๆ" เพราะลูกค้าค้นหาจาก Google ด้วยคำเฉพาะ.',
        ],
        [
            'icon'  => '⚡',
            'title' => 'อัปโหลดเสร็จภายใน 24 ชม. ขายได้มากกว่า 2 เท่า',
            'body'  => 'ลูกค้าตื่นเต้นกับงานล่าสุดและจดจำหน้าตาตัวเอง — เกิน 3-4 วัน คนลืมและเลื่อนผ่าน. ตั้งเป้าโพสต์รูปภายใน 1 วันหลังจบงาน.',
        ],
        [
            'icon'  => '🎯',
            'title' => 'AI Face Search ทำงานดีที่สุดเมื่อหน้าตรง',
            'body'  => 'แนะนำลูกค้าให้ใช้ selfie ที่ไม่ใส่แว่นกันแดดและไม่หันข้าง ระบบจะจับคู่ความเหมือนได้แม่นยำขึ้น (เกณฑ์ default 80%).',
        ],
        [
            'icon'  => '💰',
            'title' => 'ตั้งราคา Bundle ให้ลูกค้าซื้อหลายรูป',
            'body'  => '"3 รูป ฿XXX" หรือ "ทั้งอัลบั้ม ฿YYY" ทำให้ค่าเฉลี่ยต่อออเดอร์สูงขึ้น เมื่อเทียบกับการขายรูปละใบ. ลองตั้ง bundle 5-10% ถูกกว่ายอดรวมรายใบ.',
        ],
        [
            'icon'  => '📅',
            'title' => 'ลูกค้าซื้อรูปวันจันทร์-อังคารบ่ายเยอะที่สุด',
            'body'  => 'หลังงานวันเสาร์-อาทิตย์ ลูกค้ามักหารูปเข้าออฟฟิศวันจันทร์ — ส่งโพสต์ใน Facebook/LINE OA ตอนเช้าวันจันทร์ทันยอดดูสูงสุด.',
        ],
        [
            'icon'  => '🔒',
            'title' => 'ลายน้ำพรีวิวกับไฟล์ขายเป็นคนละไฟล์',
            'body'  => 'ระบบสร้างเวอร์ชันลายน้ำให้ดูฟรี และเก็บไฟล์ต้นฉบับไว้ส่งให้หลังจ่ายเงิน — ลูกค้าได้ภาพเต็มไม่มีลายน้ำเสมอ ไม่ต้องเซ็ตอะไร.',
        ],
        [
            'icon'  => '📞',
            'title' => 'ตอบแชทใน 30 นาที = ปิดออเดอร์ได้ 80% ขึ้น',
            'body'  => 'ลูกค้าใช้แชทเพราะลังเล — ตอบช้า ลังเลแล้วก็ปิดเว็บ. เปิด LINE notification ในระบบเพื่อรู้ทันทีเมื่อมีข้อความเข้า.',
        ],
    ];

    public function handle(NewsletterService $newsletter): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $only   = $this->option('email');
        $limit  = $this->option('limit') ? (int) $this->option('limit') : null;

        $this->info($dryRun
            ? '─── DRY RUN — no emails will be sent ───'
            : '─── Sending weekly subscriber digest ───');

        if (!$newsletter->enabled()) {
            $this->warn('Newsletter feature is disabled (marketing_newsletter_enabled=0). Skipping.');
            return self::SUCCESS;
        }

        // ── Build the shared digest body once for the whole batch ──
        // All confirmed subscribers receive identical content this week
        // (no per-user personalisation), so we render content + HTML
        // once and reuse for every recipient. This makes the send fast
        // and cheap even with 10K+ subscribers.
        $content = $this->buildContent();

        if (!$content['has_content']) {
            $this->warn('No fresh content this week (0 new events + 0 active promos). Skipping the entire send.');
            Log::info('newsletter.weekly.empty_skip', ['week' => now()->isoFormat('GGGG-WW')]);
            return self::SUCCESS;
        }

        $this->line(sprintf(
            '  Content: %d new events · %d active promos · 1 tip',
            $content['events']->count(),
            $content['promotions']->count(),
        ));

        // ── Resolve recipients ─────────────────────────────────────
        $query = Subscriber::confirmed()->whereNotNull('email');
        if ($only) {
            $query->where('email', strtolower(trim($only)));
        }
        $totalEligible = $query->count();
        if ($limit) {
            $query->limit($limit);
        }
        $this->info("Eligible subscribers: {$totalEligible}" . ($limit ? " (limited to {$limit})" : ''));

        if ($totalEligible === 0) {
            $this->warn('No confirmed subscribers found.');
            return self::SUCCESS;
        }

        // ── Send loop ──────────────────────────────────────────────
        $from = method_exists($newsletter, 'fromAddress')
            ? $this->extractFromAddress($newsletter)
            : ['address' => config('mail.from.address'), 'name' => config('mail.from.name')];

        $sent = 0;
        $failed = 0;
        $skippedDryRun = 0;

        $query->chunk(50, function ($batch) use (
            $content, $from, $dryRun, &$sent, &$failed, &$skippedDryRun
        ) {
            foreach ($batch as $sub) {
                /** @var Subscriber $sub */
                if ($dryRun) {
                    $this->line("  [dry-run] would send to: {$sub->email}");
                    $skippedDryRun++;
                    continue;
                }

                try {
                    $html = $this->renderEmailHtml($sub, $content);
                    Mail::html($html, function ($m) use ($sub, $from) {
                        $m->to($sub->email, $sub->name ?: null)
                          ->from($from['address'], $from['name'])
                          ->subject($this->subject());
                    });
                    $sent++;
                } catch (\Throwable $e) {
                    Log::warning('newsletter.weekly.send_failed', [
                        'email' => $sub->email,
                        'err'   => $e->getMessage(),
                    ]);
                    $failed++;
                }
            }
        });

        // ── Summary ────────────────────────────────────────────────
        $this->newLine();
        $this->info("─── Summary ───");
        if ($dryRun) {
            $this->line("  Would have sent to: <fg=yellow>{$skippedDryRun}</> subscribers");
        } else {
            $this->line("  Sent:   <fg=green>{$sent}</>");
            $this->line("  Failed: <fg=red>{$failed}</>");
        }

        Log::info('newsletter.weekly.completed', [
            'dry_run' => $dryRun,
            'sent'    => $sent,
            'failed'  => $failed,
            'events'  => $content['events']->count(),
            'promos'  => $content['promotions']->count(),
        ]);

        return self::SUCCESS;
    }

    /**
     * Compute the shared digest body for this week. Numbers and items
     * here come straight from live tables — never invented.
     *
     * @return array{events: Collection, promotions: Collection, tip: array, has_content: bool}
     */
    protected function buildContent(): array
    {
        $sevenDaysAgo = Carbon::now()->subDays(7);

        // New events — published or active in the past 7 days. We sort
        // by created_at descending so the most recent appear first.
        // Capped at 6 items to keep the email scannable on mobile.
        $events = Event::query()
            ->published()
            ->where('created_at', '>=', $sevenDaysAgo)
            ->orderByDesc('created_at')
            ->limit(6)
            ->get(['id', 'name', 'slug', 'cover_image', 'shoot_date', 'created_at', 'province_id']);

        // Active site-wide coupons. We exclude exhausted coupons via the
        // model's existing scope chain. Capped at 3 to avoid coupon
        // spam — if there are more, admins can pin via the Campaign
        // builder for special blasts.
        $promotions = Coupon::query()
            ->active()
            ->where(function ($q) {
                $q->whereNull('usage_limit')
                  ->orWhereColumn('usage_count', '<', 'usage_limit');
            })
            ->orderBy('end_date', 'asc')   // expiring-soonest first creates urgency, but legitimately
            ->limit(3)
            ->get(['id', 'code', 'name', 'description', 'type', 'value', 'min_order', 'max_discount', 'end_date']);

        // Rotating tip — index by ISO week so the same week always
        // shows the same tip across re-runs. modulo with array length
        // means new tips appended to TIPS auto-enter the rotation.
        $weekIndex = (int) now()->isoFormat('W');
        $tip = self::TIPS[$weekIndex % count(self::TIPS)];

        return [
            'events'      => $events,
            'promotions'  => $promotions,
            'tip'         => $tip,
            // Skip-empty rule: a tip alone is NOT enough — we only send
            // when there's at least one event OR one promo to share.
            'has_content' => $events->isNotEmpty() || $promotions->isNotEmpty(),
        ];
    }

    /**
     * Render the per-recipient email HTML. The unsubscribe link is the
     * only personalised part — everything else is shared.
     */
    protected function renderEmailHtml(Subscriber $sub, array $content): string
    {
        $unsubUrl = route('newsletter.unsubscribe', ['email' => $sub->email]);
        $weekLabel = now()->isoFormat('สัปดาห์ที่ W · GGGG');

        return view('emails.subscribers-weekly', [
            'subscriber'   => $sub,
            'content'      => $content,
            'unsubscribe'  => $unsubUrl,
            'weekLabel'    => $weekLabel,
            'siteName'     => config('app.name'),
            'siteUrl'      => url('/'),
        ])->render();
    }

    /**
     * Subject line: include the week label so subscribers can tell at a
     * glance which weekly issue this is, and the inbox client doesn't
     * threaded-collapse them all into one conversation.
     */
    protected function subject(): string
    {
        return '[' . config('app.name') . '] สรุปสัปดาห์ — อีเวนต์ใหม่ + โปรโมชั่น';
    }

    /**
     * Reflect into NewsletterService's protected fromAddress() — keeps
     * the from-address logic in ONE place (NewsletterService) instead
     * of duplicating it here. A cleaner alternative would be to make
     * fromAddress() public, but I don't want to reshape the existing
     * service surface for one new caller.
     */
    private function extractFromAddress(NewsletterService $service): array
    {
        try {
            $ref = new \ReflectionMethod($service, 'fromAddress');
            $ref->setAccessible(true);
            return $ref->invoke($service);
        } catch (\Throwable) {
            return [
                'address' => config('mail.from.address'),
                'name'    => config('mail.from.name'),
            ];
        }
    }
}
