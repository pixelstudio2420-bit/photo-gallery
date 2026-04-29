<?php

namespace App\Console\Commands;

use App\Jobs\ProcessPayoutJob;
use App\Models\AppSetting;
use App\Models\DownloadToken;
use App\Models\Event;
use App\Models\EventCategory;
use App\Models\EventPhoto;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PhotographerDisbursement;
use App\Models\PhotographerPayout;
use App\Models\PhotographerProfile;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Payout\PayoutEngine;
use App\Services\Payout\PayoutProviderFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * End-to-end integration test for the photographer → payment → payout pipeline.
 *
 * What it does:
 *   0. Environment check (DB up, migrations current, tables exist)
 *   1. Override AppSettings → payout_provider=mock, daily schedule, min=100 THB
 *   2. Create a synthetic photographer User + PhotographerProfile
 *      → verify tier jumps creator → seller as soon as PromptPay is saved
 *   3. Create a synthetic customer User
 *   4. Create Event + 3 EventPhotos
 *   5. Create pending Order → simulate paid → create OrderItems + DownloadTokens
 *      + PhotographerPayout rows (mirrors what AdminPaymentController does)
 *   6. Verify DownloadToken::isValid() is true
 *   7. Run PayoutEngine (manual trigger) → invoke ProcessPayoutJob synchronously
 *      with the MockPayoutProvider → verify disbursement terminal 'succeeded',
 *      payouts flipped to 'paid', success notification row exists
 *
 * What it intentionally does NOT cover (browser-only — see manual checklist):
 *   - OAuth consent flow for Google / LINE signup
 *   - Real Omise sandbox transfer (requires OMISE_SECRET_KEY in .env)
 *   - LINE push delivery (requires LINE bot token + bound user)
 *   - File uploads (multipart forms require a real HTTP roundtrip)
 *   - Stripe / PayPal webhook signature verification
 *
 * Safety:
 *   Every row created is tagged with a UUID marker so --cleanup can drop
 *   exactly what this run made without touching real data. Original AppSetting
 *   values are snapshotted and restored on completion regardless of outcome.
 *
 * Usage:
 *   php artisan app:test-e2e-flow                  # run + auto-cleanup
 *   php artisan app:test-e2e-flow --keep           # leave rows for inspection
 *   php artisan app:test-e2e-flow --cleanup-only   # nuke leftovers from prior runs
 */
class TestE2EFlow extends Command
{
    protected $signature = 'app:test-e2e-flow
                            {--keep : Leave synthetic test data in the DB after the run}
                            {--cleanup-only : Only delete leftovers from previous runs, then exit}';

    protected $description = 'End-to-end test: signup → sell → pay → download → payout (uses MockPayoutProvider)';

    /** Prefix applied to all synthetic rows so cleanup can find them. */
    private const MARKER = 'E2ETEST_';

    /** Per-run unique suffix (stops two concurrent runs from colliding on unique keys). */
    private string $runId;

    private int $passed = 0;
    private int $failed = 0;
    /** @var array<string> */
    private array $failures = [];

    /** Snapshot of AppSetting keys we override, so we can restore them on exit. */
    private array $settingSnapshot = [];

    public function handle(): int
    {
        $this->runId = substr(bin2hex(random_bytes(4)), 0, 8);

        $this->line('');
        $this->line('<fg=cyan>┌──────────────────────────────────────────────────────────┐</>');
        $this->line('<fg=cyan>│  E2E Flow Test — signup → sell → pay → download → payout │</>');
        $this->line('<fg=cyan>└──────────────────────────────────────────────────────────┘</>');
        $this->line('  Marker: <fg=yellow>' . self::MARKER . '*_' . $this->runId . '</>');
        $this->line('  Provider: <fg=yellow>MockPayoutProvider</> (no real money moves)');
        $this->line('');

        if ($this->option('cleanup-only')) {
            return $this->cleanupAll();
        }

        try {
            $this->snapshotSettings();
            $this->overrideSettings();

            // ── Pipeline stages ──
            $env = $this->phase0_environment();
            if (!$env) return $this->finish(false);

            $photographer = $this->phase2_photographer();
            $customer     = $this->phase3_customer();
            if (!$photographer || !$customer) return $this->finish(false);

            $event = $this->phase4_event($photographer);
            if (!$event) return $this->finish(false);

            $photos = $this->phase4b_photos($event, $photographer);
            if (empty($photos)) return $this->finish(false);

            $order = $this->phase5_order_paid($customer, $event, $photos);
            if (!$order) return $this->finish(false);

            $this->phase6_download_tokens($order);

            $disbursement = $this->phase7_payout_pipeline($photographer);
            if ($disbursement) {
                $this->phase8_verify_notifications($photographer->id, $disbursement);
            }
        } finally {
            // ALWAYS restore settings, even on exception.
            $this->restoreSettings();

            if (!$this->option('keep')) {
                $this->cleanupAll(silent: true);
            }
        }

        return $this->finish($this->failed === 0);
    }

    // ─────────────────────────────────────────────────────────────
    //  Phases
    // ─────────────────────────────────────────────────────────────

    private function phase0_environment(): bool
    {
        $this->section('Phase 0 — Environment check');

        $this->check('DB connection responsive', function () {
            DB::select('SELECT 1 AS ok');
            return true;
        });

        $required = [
            'auth_users', 'photographer_profiles', 'event_events', 'event_photos',
            'orders', 'order_items', 'photographer_payouts',
            'photographer_disbursements', 'download_tokens', 'user_notifications',
            'app_settings',
        ];
        foreach ($required as $t) {
            $this->check("table exists: $t", fn () => Schema::hasTable($t));
        }

        $this->check('photographer_profiles.tier column present', function () {
            return Schema::hasColumn('photographer_profiles', 'tier');
        });
        $this->check('photographer_payouts.disbursement_id column present', function () {
            return Schema::hasColumn('photographer_payouts', 'disbursement_id');
        });

        return $this->failed === 0;
    }

    private function phase2_photographer(): ?User
    {
        $this->section('Phase 2 — Photographer signup + tier progression');

        $email = self::MARKER . 'pg_' . $this->runId . '@example.test';

        $user = User::create([
            'username'       => self::MARKER . 'pg_' . $this->runId,
            'first_name'     => 'Test',
            'last_name'      => 'Photographer',
            'email'          => $email,
            'password_hash'  => Hash::make('test-password-' . $this->runId),
            'auth_provider'  => 'local',
            'status'         => 'active',
            'email_verified' => true,
            'email_verified_at' => now(),
        ]);

        $this->check('create photographer User', fn () => $user->exists);

        // Profile starts at creator (no PromptPay yet) — mirrors what the real
        // OAuth signup flow does when a creator-tier account is provisioned.
        $profile = PhotographerProfile::create([
            'user_id'           => $user->id,
            'photographer_code' => 'PG' . strtoupper($this->runId),
            'display_name'      => self::MARKER . 'Test PG',
            'tier'              => PhotographerProfile::TIER_CREATOR,
            'status'            => 'approved',
            'onboarding_stage'  => 'active',
            'commission_rate'   => 80.00,
        ]);

        $this->check('profile starts at tier=creator', function () use ($profile) {
            return $profile->tier === PhotographerProfile::TIER_CREATOR;
        });

        // Add PromptPay + bank name (mimics updateBank()). syncTier should
        // flip creator → seller.
        $profile->update([
            'promptpay_number'   => '0812345678',
            'bank_account_name'  => 'Test Photographer',
        ]);
        $profile->refresh();
        $profile->syncTier();
        $profile->refresh();

        $this->check('after PromptPay save: tier=seller', function () use ($profile) {
            return $profile->tier === PhotographerProfile::TIER_SELLER;
        });

        $this->check('isPromptPayVerified() is FALSE before any transfer', function () use ($profile) {
            // Critical: we NEVER fake verification. It's only true after an
            // ITMX-confirmed transfer lands a name in promptpay_verified_name.
            return $profile->isPromptPayVerified() === false;
        });

        return $user;
    }

    private function phase3_customer(): ?User
    {
        $this->section('Phase 3 — Customer signup');

        $user = User::create([
            'username'       => self::MARKER . 'cu_' . $this->runId,
            'first_name'     => 'Test',
            'last_name'      => 'Customer',
            'email'          => self::MARKER . 'cu_' . $this->runId . '@example.test',
            'password_hash'  => Hash::make('test-password-' . $this->runId),
            'auth_provider'  => 'local',
            'status'         => 'active',
            'email_verified' => true,
            'email_verified_at' => now(),
        ]);

        $this->check('create customer User', fn () => $user->exists);
        return $user;
    }

    private function phase4_event(User $photographer): ?Event
    {
        $this->section('Phase 4 — Event creation');

        // Category is optional per schema, but we'll find or create one so
        // any downstream code that expects it has something to join to.
        $category = null;
        if (Schema::hasTable('event_categories')) {
            $category = EventCategory::first();
            if (!$category) {
                $category = EventCategory::create([
                    'name'   => self::MARKER . 'TestCat',
                    'slug'   => self::MARKER . 'testcat-' . $this->runId,
                    'icon'   => 'bi-camera',
                    'status' => 'active',
                ]);
            }
        }

        $event = Event::create([
            'photographer_id' => $photographer->id,
            'category_id'    => $category?->id,
            'name'           => self::MARKER . 'Event ' . $this->runId,
            'slug'           => Str::slug(self::MARKER . 'event-' . $this->runId),
            'description'    => 'Synthetic E2E test event',
            'price_per_photo'=> 300.00,
            'is_free'        => false,
            'visibility'     => 'public',
            'status'         => 'published',
            'shoot_date'     => now()->subDay()->toDateString(),
        ]);

        $this->check('create Event', fn () => $event->exists);
        $this->check('Event photographer_id matches', fn () => $event->photographer_id === $photographer->id);

        return $event;
    }

    /**
     * @return array<int, EventPhoto>
     */
    private function phase4b_photos(Event $event, User $photographer): array
    {
        $this->section('Phase 4b — EventPhotos');

        $photos = [];
        for ($i = 1; $i <= 3; $i++) {
            $photo = EventPhoto::create([
                'event_id'          => $event->id,
                'uploaded_by'       => $photographer->id,
                'source'            => 'upload',
                'filename'          => self::MARKER . "photo_{$i}_{$this->runId}.jpg",
                'original_filename' => "synthetic_{$i}.jpg",
                'mime_type'         => 'image/jpeg',
                'file_size'         => 1024 * 512,
                'width'             => 2048,
                'height'            => 1365,
                'storage_disk'      => 'public',
                'original_path'     => "events/{$event->id}/originals/synthetic_{$i}.jpg",
                'thumbnail_path'    => "events/{$event->id}/thumbs/synthetic_{$i}.jpg",
                'sort_order'        => $i,
                'status'            => 'active',
                // Skip moderation pipeline — see EventPhoto::booted hook:
                // status != 'pending' short-circuits ModeratePhotoJob dispatch.
                'moderation_status' => 'approved',
            ]);
            $photos[] = $photo;
        }

        $this->check('create 3 EventPhoto rows', fn () => count($photos) === 3);
        $this->check('all photos have status=active', function () use ($photos) {
            foreach ($photos as $p) if ($p->status !== 'active') return false;
            return true;
        });

        return $photos;
    }

    /**
     * @param array<int, EventPhoto> $photos
     */
    private function phase5_order_paid(User $customer, Event $event, array $photos): ?Order
    {
        $this->section('Phase 5 — Order + payment + OrderItems + PhotographerPayout');

        $pricePerPhoto = (float) $event->price_per_photo;
        $selectedPhotos = array_slice($photos, 0, 2); // Customer picks 2 of 3
        $total = $pricePerPhoto * count($selectedPhotos);

        $order = Order::create([
            'user_id'      => $customer->id,
            'event_id'     => $event->id,
            'order_number' => self::MARKER . 'ORD_' . strtoupper($this->runId),
            'total'        => $total,
            'status'       => 'pending_payment',
            'note'         => 'Synthetic E2E test',
        ]);

        $this->check('create Order (pending_payment)', fn () => $order->exists && $order->status === 'pending_payment');

        // OrderItems — one per selected photo, price = event.price_per_photo
        foreach ($selectedPhotos as $p) {
            OrderItem::create([
                'order_id'      => $order->id,
                'photo_id'      => (string) $p->id,
                'thumbnail_url' => 'events/' . $event->id . '/thumbs/synthetic_' . $p->id . '.jpg',
                'price'         => $pricePerPhoto,
            ]);
        }
        $order->refresh();
        $this->check('OrderItems match selected photo count', fn () => $order->items->count() === count($selectedPhotos));

        // Simulate payment — mirrors AdminPaymentController::approveSlip steps:
        //   order.status → paid
        //   photographer_payouts row created with split
        $order->update(['status' => 'paid']);
        $this->check('Order flips to status=paid', fn () => $order->refresh()->status === 'paid');

        $profile = PhotographerProfile::where('user_id', $event->photographer_id)->firstOrFail();
        $photographerRate = (float) $profile->commission_rate;
        $platformRate = 100 - $photographerRate;
        $platformFee = round($total * $platformRate / 100, 2);
        $payoutAmount = round($total - $platformFee, 2);

        $payout = PhotographerPayout::create([
            'photographer_id' => $event->photographer_id,
            'order_id'        => $order->id,
            'gross_amount'    => $total,
            'commission_rate' => $photographerRate,
            'payout_amount'   => $payoutAmount,
            'platform_fee'    => $platformFee,
            'status'          => 'pending',
        ]);

        $this->check('PhotographerPayout created (status=pending)', fn () => $payout->exists && $payout->status === 'pending');
        $this->check(
            "commission split correct (80/20 of ฿{$total})",
            fn () => abs($payoutAmount - ($total * 0.80)) < 0.01
                 && abs($platformFee  - ($total * 0.20)) < 0.01
        );

        return $order;
    }

    private function phase6_download_tokens(Order $order): void
    {
        $this->section('Phase 6 — DownloadTokens');

        // "All photos" token — photo_id=null, used for the ZIP download
        $zipToken = DownloadToken::create([
            'token'          => bin2hex(random_bytes(16)),
            'order_id'       => $order->id,
            'user_id'        => $order->user_id,
            'photo_id'       => null,
            'expires_at'     => now()->addDays(30),
            'max_downloads'  => max($order->items->count() * 2, 10),
            'download_count' => 0,
        ]);

        // Per-item tokens
        $itemTokens = [];
        foreach ($order->items as $item) {
            $itemTokens[] = DownloadToken::create([
                'token'          => bin2hex(random_bytes(16)),
                'order_id'       => $order->id,
                'user_id'        => $order->user_id,
                'photo_id'       => $item->photo_id,
                'expires_at'     => now()->addDays(30),
                'max_downloads'  => 5,
                'download_count' => 0,
            ]);
        }

        $this->check('ZIP download token created', fn () => $zipToken->exists);
        $this->check('per-item tokens == items count', fn () => count($itemTokens) === $order->items->count());
        $this->check('token isValid() is true (not expired, unused)', fn () => $zipToken->isValid());
    }

    private function phase7_payout_pipeline(User $photographer): ?PhotographerDisbursement
    {
        $this->section('Phase 7 — Payout pipeline (engine + job + provider)');

        $engine = app(PayoutEngine::class);

        // Manual trigger fires regardless of schedule/threshold (see
        // PayoutEngine::runCycle) — best fit for a deterministic test.
        $disbursements = $engine->runCycle(PhotographerDisbursement::TRIGGER_MANUAL);

        $this->check(
            'PayoutEngine.runCycle created >=1 disbursement',
            fn () => count($disbursements) >= 1
        );

        // Find the disbursement for OUR synthetic photographer (there may be
        // legit pending rows for other photographers in the DB; we only care
        // about ours).
        $disbursement = null;
        foreach ($disbursements as $d) {
            if ($d->photographer_id === $photographer->id) {
                $disbursement = $d;
                break;
            }
        }

        $this->check('disbursement found for test photographer', fn () => $disbursement !== null);
        if (!$disbursement) return null;

        $this->check('disbursement provider = mock', fn () => $disbursement->provider === 'mock');
        $this->check('disbursement status = pending (pre-job)', fn () => $disbursement->status === 'pending');

        // Execute the job SYNCHRONOUSLY — no queue worker needed for the test.
        // This calls the same handle() method the queue would run, so any
        // success/failure branch in ProcessPayoutJob is exercised here.
        $factory = app(PayoutProviderFactory::class);
        (new ProcessPayoutJob($disbursement->id))->handle($factory);

        $disbursement->refresh();

        $this->check('disbursement status = succeeded (post-job)', fn () => $disbursement->status === 'succeeded');
        $this->check('disbursement settled_at timestamp set', fn () => $disbursement->settled_at !== null);
        $this->check('disbursement provider_txn_id populated', fn () => !empty($disbursement->provider_txn_id));

        // Verify all attached payouts flipped to 'paid'.
        $paidPayouts = PhotographerPayout::where('disbursement_id', $disbursement->id)
            ->where('status', 'paid')
            ->count();
        $totalPayouts = PhotographerPayout::where('disbursement_id', $disbursement->id)->count();

        $this->check(
            "all attached payouts flipped to 'paid' ({$paidPayouts}/{$totalPayouts})",
            fn () => $paidPayouts > 0 && $paidPayouts === $totalPayouts
        );

        // Note: MockPayoutProvider returns raw=['provider'=>'mock', 'note'=>…]
        // without a `bank_account.name` key, so captureVerifiedNameFromResponse()
        // is a no-op for mock runs. Verified name stays null. Documenting here
        // so this doesn't look like a bug — it's the expected boundary
        // between mock and real Omise behaviour.
        $profile = PhotographerProfile::where('user_id', $photographer->id)->first();
        $this->check(
            'verified name stays NULL after mock transfer (expected — mock does not return ITMX name)',
            fn () => $profile && empty($profile->promptpay_verified_name)
        );

        return $disbursement;
    }

    private function phase8_verify_notifications(int $photographerUserId, PhotographerDisbursement $disbursement): void
    {
        $this->section('Phase 8 — Notifications');

        $since = $disbursement->settled_at ?? now()->subMinute();

        $payoutNote = UserNotification::where('user_id', $photographerUserId)
            ->where('type', 'payout')
            ->where('created_at', '>=', $since)
            ->first();

        $this->check(
            'UserNotification type=payout exists for photographer',
            fn () => $payoutNote !== null
        );

        if ($payoutNote) {
            $this->check(
                'payout notification links to photographer earnings page',
                fn () => str_contains((string) $payoutNote->action_url, 'photographer/earnings')
            );
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  AppSetting snapshot / restore
    // ─────────────────────────────────────────────────────────────

    /**
     * Settings we override for the test run. We snapshot whatever's currently
     * stored (or null if absent) so restore() returns the system exactly how
     * we found it — no silent drift if the test is aborted partway through.
     */
    private const OVERRIDE_KEYS = [
        'payout_enabled',
        'payout_provider',
        'payout_min_amount',
        'payout_schedule',
        'payout_trigger_logic',
        'payout_delay_hours',
        'moderation_enabled',
    ];

    private function snapshotSettings(): void
    {
        foreach (self::OVERRIDE_KEYS as $k) {
            $row = AppSetting::where('key', $k)->first();
            $this->settingSnapshot[$k] = $row?->value; // null = was absent
        }
    }

    private function overrideSettings(): void
    {
        AppSetting::setMany([
            'payout_enabled'       => '1',
            'payout_provider'      => 'mock',
            'payout_min_amount'    => '100',          // low bar so test always triggers
            'payout_schedule'      => 'daily',        // always "open"
            'payout_trigger_logic' => 'either',
            'payout_delay_hours'   => '0',            // no cushion — fire immediately
            'moderation_enabled'   => '0',            // skip Rekognition job dispatch
        ]);
    }

    private function restoreSettings(): void
    {
        foreach ($this->settingSnapshot as $k => $v) {
            if ($v === null) {
                AppSetting::where('key', $k)->delete();
            } else {
                AppSetting::set($k, $v);
            }
        }
        AppSetting::flushCache();
    }

    // ─────────────────────────────────────────────────────────────
    //  Cleanup (rows tagged with MARKER)
    // ─────────────────────────────────────────────────────────────

    /**
     * Delete every row this command creates in any run.
     *
     * Order matters: delete children first so FK-cascade and observer hooks
     * don't double-fire. Kept conservative — never touches rows that don't
     * match the MARKER prefix so it's safe to run against production.
     */
    private function cleanupAll(bool $silent = false): int
    {
        if (!$silent) $this->section('Cleanup — remove synthetic rows');

        $stats = [];

        // Discover synthetic users FIRST — we key all other deletes off their ids.
        $userIds = User::where('email', 'ilike', self::MARKER . '%')
            ->orWhere('username', 'ilike', self::MARKER . '%')
            ->pluck('id')
            ->all();

        if (empty($userIds)) {
            if (!$silent) $this->line('  <fg=gray>No synthetic users found — nothing to clean.</>');
            return 0;
        }

        // 1. Disbursements (delete BEFORE payouts to avoid FK null-set thrash)
        $stats['disbursements'] = PhotographerDisbursement::whereIn('photographer_id', $userIds)->delete();

        // 2. Download tokens
        $orderIds = Order::whereIn('user_id', $userIds)->pluck('id')->all();
        if (!empty($orderIds)) {
            $stats['download_tokens'] = DB::table('download_tokens')->whereIn('order_id', $orderIds)->delete();
        }

        // 3. Payouts
        $stats['payouts'] = PhotographerPayout::whereIn('photographer_id', $userIds)->delete();

        // 4. Order items + orders
        if (!empty($orderIds)) {
            $stats['order_items'] = DB::table('order_items')->whereIn('order_id', $orderIds)->delete();
            // Order::delete() fires storage purge hook — use raw DB to skip it
            // (nothing to purge for synthetic orders, and the hook would try
            //  to touch StorageManager which might not be configured in CI).
            $stats['orders'] = DB::table('orders')->whereIn('id', $orderIds)->delete();
        }

        // 5. EventPhotos — use raw DB to skip Rekognition delete hook
        $eventIds = Event::whereIn('photographer_id', $userIds)->pluck('id')->all();
        if (!empty($eventIds)) {
            $stats['event_photos'] = DB::table('event_photos')->whereIn('event_id', $eventIds)->delete();
            $stats['events']       = DB::table('event_events')->whereIn('id', $eventIds)->delete();
        }

        // 6. Notifications
        $stats['notifications'] = UserNotification::whereIn('user_id', $userIds)->delete();

        // 7. Profiles — raw DB (skip avatar-purge observer)
        $stats['profiles'] = DB::table('photographer_profiles')->whereIn('user_id', $userIds)->delete();

        // 8. Synthetic EventCategory rows
        $stats['event_categories'] = DB::table('event_categories')
            ->where('slug', 'ilike', self::MARKER . '%')
            ->delete();

        // 9. Users (raw — skip StorageManager purge)
        $stats['users'] = DB::table('auth_users')->whereIn('id', $userIds)->delete();

        if (!$silent) {
            foreach ($stats as $t => $n) {
                if ($n > 0) $this->line("  <fg=gray>deleted {$n} {$t}</>");
            }
        }

        return 0;
    }

    // ─────────────────────────────────────────────────────────────
    //  Output helpers
    // ─────────────────────────────────────────────────────────────

    private function section(string $title): void
    {
        $this->line('');
        $this->line('<fg=cyan>▶ ' . $title . '</>');
    }

    /**
     * Run a boolean-returning check. Any exception is caught and treated as
     * a failure (so a downstream bug doesn't abort the whole test).
     */
    private function check(string $label, \Closure $probe): void
    {
        try {
            $ok = (bool) $probe();
        } catch (\Throwable $e) {
            $ok = false;
            $label .= ' — <fg=red>' . $e->getMessage() . '</>';
        }

        if ($ok) {
            $this->passed++;
            $this->line("  <fg=green>✔</> {$label}");
        } else {
            $this->failed++;
            $this->failures[] = $label;
            $this->line("  <fg=red>✘</> {$label}");
        }
    }

    private function finish(bool $success): int
    {
        $this->line('');
        $this->line('<fg=cyan>─── Summary ───────────────────────────────────────────────</>');
        $this->line("  Passed: <fg=green>{$this->passed}</>");
        $this->line("  Failed: " . ($this->failed > 0 ? "<fg=red>{$this->failed}</>" : '<fg=green>0</>'));

        if (!empty($this->failures)) {
            $this->line('');
            $this->line('<fg=red>Failures:</>');
            foreach ($this->failures as $f) {
                $this->line('  - ' . $f);
            }
        }

        $this->line('');
        if ($success) {
            $this->line('<bg=green;fg=black> OK </> all phases passed. Safe to run browser-only checks next.');
            return self::SUCCESS;
        }

        $this->line('<bg=red;fg=white> FAIL </> one or more phases failed. See failures above.');
        return self::FAILURE;
    }
}
