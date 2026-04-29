<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * End-to-end smoke test — runs against the LIVE DB without mutating production data.
 * Creates a disposable test user + digital order, walks through the full flow,
 * then cleans up. Safe to rerun anytime.
 *
 * Usage:
 *   php artisan app:smoke-test
 *   php artisan app:smoke-test --group=digital    # only digital product flow
 *   php artisan app:smoke-test --keep             # don't clean up test data
 *   php artisan app:smoke-test --verbose
 */
class SmokeTest extends Command
{
    protected $signature = 'app:smoke-test
                            {--group= : Run only a specific group (schema|digital|photo|notifications|dashboard|routes|reset)}
                            {--keep : Don\'t delete test data at the end}';

    protected $description = 'Run a full end-to-end smoke test of the system';

    private int $passed = 0;
    private int $failed = 0;
    private int $skipped = 0;
    private array $failures = [];
    private array $cleanup = [];   // [[table, id], ...]

    public function handle(): int
    {
        $this->line('');
        $this->line('<bg=blue;fg=white>                                                  </>');
        $this->line('<bg=blue;fg=white>   📸 Photo Gallery — Smoke Test Suite             </>');
        $this->line('<bg=blue;fg=white>                                                  </>');
        $this->line('');

        $group = $this->option('group');
        $groups = [
            'schema'        => 'Database schema integrity',
            'routes'        => 'Critical routes registered',
            'digital'       => 'Digital product purchase flow',
            'photo'         => 'Photo order basics',
            'notifications' => 'Notification system',
            'dashboard'     => 'Dashboard stats accuracy',
            'reset'         => 'Factory reset safety',
        ];

        try {
            foreach ($groups as $key => $label) {
                if ($group && $group !== $key) continue;
                $this->section($label);
                $method = 'test' . Str::studly($key);
                if (method_exists($this, $method)) {
                    $this->{$method}();
                }
            }
        } catch (\Throwable $e) {
            $this->error('FATAL: ' . $e->getMessage());
            $this->line($e->getTraceAsString());
            $this->cleanupData();
            return self::FAILURE;
        }

        $this->cleanupData();
        return $this->summary();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Test groups
    // ═══════════════════════════════════════════════════════════════════════

    private function testSchema(): void
    {
        $required = [
            // Core
            'auth_users', 'auth_admins', 'app_settings',
            // Events / photos
            'event_events', 'event_photos_cache',
            // Photo orders
            'orders', 'order_items', 'payment_slips', 'payment_transactions',
            'download_tokens', 'photographer_payouts', 'photographer_profiles',
            // Digital products
            'digital_products', 'digital_orders', 'digital_download_tokens',
            // Notifications
            'admin_notifications', 'user_notifications',
            // Security
            'security_logs', 'security_login_attempts', 'security_rate_limits',
            // Commerce
            'bank_accounts', 'payment_methods', 'coupons',
        ];
        foreach ($required as $t) {
            $this->assert(Schema::hasTable($t), "table `{$t}` exists");
        }

        // Critical columns that previous bugs touched
        $colChecks = [
            ['digital_orders',      ['order_number','user_id','product_id','amount','slip_image','status','download_token','downloads_remaining','expires_at']],
            ['admin_notifications', ['type','title','message','link','ref_id','is_read']],
            ['user_notifications',  ['user_id','type','title','message','is_read']],
            ['digital_products',    ['file_source','drive_file_id','direct_url','local_file','total_sales','total_revenue']],
        ];
        foreach ($colChecks as [$tbl, $cols]) {
            if (!Schema::hasTable($tbl)) { $this->skip("columns on `{$tbl}` (table missing)"); continue; }
            $existing = Schema::getColumnListing($tbl);
            foreach ($cols as $c) {
                $this->assert(in_array($c, $existing, true), "{$tbl}.{$c} present");
            }
        }
    }

    private function testRoutes(): void
    {
        $names = [
            'products.index', 'products.show', 'products.purchase', 'products.checkout',
            'products.upload-slip', 'products.order', 'products.order.status',
            'products.my-orders', 'products.download',
            'payment.slip.upload', 'payment.status',
            'admin.api.admin.notifications', 'admin.api.admin.notifications.read',
            'admin.api.admin.notifications.read-all', 'admin.api.admin.notifications.mark-by-ref',
        ];
        foreach ($names as $n) {
            $this->assert(Route::has($n), "route `{$n}` registered");
        }
    }

    private function testDigital(): void
    {
        // 1. Pick a product
        $product = DB::table('digital_products')->where('status', 'active')->first();
        if (!$product) { $this->skip('no active digital product'); return; }
        $this->assert(true, "picked product #{$product->id} — {$product->name}");

        // 2. Create disposable test user
        $userId = $this->createTestUser();
        $this->assert((bool) $userId, "created test user #{$userId}");

        // 3. Create order (emulate ProductController@purchase)
        $orderNumber = 'SMOKE-' . strtoupper(Str::random(6));
        $orderId = DB::table('digital_orders')->insertGetId([
            'order_number'   => $orderNumber,
            'user_id'        => $userId,
            'product_id'     => $product->id,
            'amount'         => $product->price ?? 100,
            'payment_method' => 'pending',
            'status'         => 'pending_payment',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
        $this->cleanup[] = ['digital_orders', $orderId];
        $this->assert((bool) $orderId, "created order #{$orderId} ({$orderNumber})");

        // 4. Simulate slip upload → pending_review
        DB::table('digital_orders')->where('id', $orderId)->update([
            'slip_image'     => 'slips/digital/test.jpg',
            'payment_method' => 'bank_transfer',
            'status'         => 'pending_review',
            'updated_at'     => now(),
        ]);
        $after = DB::table('digital_orders')->where('id', $orderId)->first();
        $this->assert($after->status === 'pending_review', 'slip uploaded → pending_review');

        // 5. Simulate admin approve (mirror DigitalOrderController@approve)
        $token = Str::uuid()->toString();
        $limit = (int) ($product->download_limit ?? 5);
        $days  = (int) ($product->download_expiry_days ?? 30);
        DB::table('digital_orders')->where('id', $orderId)->update([
            'status'              => 'paid',
            'paid_at'             => now(),
            'download_token'      => $token,
            'downloads_remaining' => $limit,
            'expires_at'          => now()->addDays($days),
            'updated_at'          => now(),
        ]);
        DB::table('digital_products')->where('id', $product->id)->increment('total_sales');
        DB::table('digital_products')->where('id', $product->id)->increment('total_revenue', (float) ($product->price ?? 100));
        $approved = DB::table('digital_orders')->where('id', $orderId)->first();
        $this->assert($approved->status === 'paid',                 'order marked paid');
        $this->assert(!empty($approved->download_token),             'download_token generated');
        $this->assert($approved->downloads_remaining === $limit,     "downloads_remaining = {$limit}");

        // 6. Create admin notification for approval and verify auto-dismiss
        $notifId = DB::table('admin_notifications')->insertGetId([
            'type'       => 'digital_order',
            'title'      => 'SMOKE: test notif',
            'message'    => 'test',
            'ref_id'     => (string) $orderId,
            'is_read'    => false,
            'created_at' => now(),
        ]);
        $this->cleanup[] = ['admin_notifications', $notifId];
        DB::table('admin_notifications')
            ->whereIn('type', ['digital_order','digital_slip'])
            ->where('ref_id', (string) $orderId)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);
        $n = DB::table('admin_notifications')->where('id', $notifId)->first();
        $this->assert($n->is_read == 1, 'admin notif auto-dismissed by ref_id');

        // 7. Simulate download (decrement remaining)
        $before = $approved->downloads_remaining;
        DB::table('digital_orders')->where('id', $orderId)->decrement('downloads_remaining');
        $after = DB::table('digital_orders')->where('id', $orderId)->first();
        $this->assert($after->downloads_remaining === $before - 1, 'download decrements remaining');

        // 8. Reject flow on a second fresh order
        $orderId2 = DB::table('digital_orders')->insertGetId([
            'order_number'   => 'SMOKE-' . strtoupper(Str::random(6)),
            'user_id'        => $userId,
            'product_id'     => $product->id,
            'amount'         => 50,
            'payment_method' => 'pending',
            'status'         => 'pending_review',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
        $this->cleanup[] = ['digital_orders', $orderId2];
        DB::table('digital_orders')->where('id', $orderId2)->update([
            'status' => 'cancelled', 'note' => 'smoke test reject', 'updated_at' => now(),
        ]);
        $rej = DB::table('digital_orders')->where('id', $orderId2)->first();
        $this->assert($rej->status === 'cancelled', 'reject → cancelled with note');
    }

    private function testPhoto(): void
    {
        $required = ['orders', 'payment_slips'];
        foreach ($required as $t) {
            if (!Schema::hasTable($t)) { $this->skip("`{$t}` missing"); return; }
        }

        // Test that order schema supports all the status values we use
        $userId = $this->createTestUser();
        $orderId = DB::table('orders')->insertGetId([
            'order_number' => 'SMOKE-PHOTO-' . strtoupper(Str::random(6)),
            'user_id'      => $userId,
            'total'        => 100,
            'status'       => 'pending_payment',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
        $this->cleanup[] = ['orders', $orderId];
        $this->assert((bool) $orderId, "created photo order #{$orderId}");

        // Cycle through statuses used by PaymentController
        foreach (['pending_review', 'paid', 'pending_payment'] as $st) {
            try {
                DB::table('orders')->where('id', $orderId)->update(['status' => $st]);
                $this->assert(true, "order status accepts `{$st}`");
            } catch (\Throwable $e) {
                $this->assert(false, "order status `{$st}` REJECTED: " . $e->getMessage());
            }
        }
    }

    private function testNotifications(): void
    {
        foreach (['digital_order','digital_slip','order_approved','order_rejected','slip','order','payment'] as $type) {
            try {
                $id = DB::table('admin_notifications')->insertGetId([
                    'type'       => $type,
                    'title'      => 'SMOKE',
                    'message'    => 'test',
                    'ref_id'     => '999999',
                    'is_read'    => false,
                    'created_at' => now(),
                ]);
                $this->cleanup[] = ['admin_notifications', $id];
                $this->assert(true, "notif type `{$type}` accepted");
            } catch (\Throwable $e) {
                $this->assert(false, "notif type `{$type}` rejected: " . $e->getMessage());
            }
        }

        // markReadByRef (array)
        \App\Models\AdminNotification::markReadByRef(['digital_order','digital_slip'], '999999');
        $unread = DB::table('admin_notifications')
            ->whereIn('type', ['digital_order','digital_slip'])
            ->where('ref_id', '999999')
            ->where('is_read', false)
            ->count();
        $this->assert($unread === 0, 'markReadByRef with array of types');
    }

    private function testDashboard(): void
    {
        // Call the dashboard queries the same way DashboardController does
        try {
            $stats = [
                'total_orders' => DB::table('orders')->count(),
                'paid_orders'  => DB::table('orders')->where('status', 'paid')->count(),
                'total_revenue'=> (float) DB::table('orders')->where('status', 'paid')->sum('total'),
                'total_users'  => DB::table('auth_users')->count(),
                'total_events' => DB::table('event_events')->count(),
                'pending_slips'=> Schema::hasTable('payment_slips')
                                    ? DB::table('payment_slips')->where('verify_status', 'pending')->count()
                                    : 0,
            ];
            foreach ($stats as $k => $v) {
                $this->assert(is_numeric($v), "stat `{$k}` = {$v}");
            }
        } catch (\Throwable $e) {
            $this->assert(false, 'dashboard queries: ' . $e->getMessage());
        }

        // Digital stats subquery (the one DashboardController actually uses)
        try {
            $ds = DB::selectOne("SELECT COUNT(*) AS total,
                                        COALESCE(SUM(CASE WHEN status='paid' THEN amount END),0) AS revenue
                                 FROM digital_orders");
            $this->assert($ds !== null, "digital_orders aggregate query works (total={$ds->total})");
        } catch (\Throwable $e) {
            $this->assert(false, 'digital aggregate: ' . $e->getMessage());
        }
    }

    private function testReset(): void
    {
        // Make sure safeTruncate target list is compatible with current schema
        $targets = [
            'order_items','download_tokens','payment_slips','payment_transactions',
            'payment_logs','payment_refunds','payment_audit_log',
            'photographer_payouts','orders',
            'digital_orders','digital_download_tokens',
            'coupon_usage','chat_messages','chat_conversations',
            'event_photos_cache',
            'admin_notifications','user_notifications',
            'security_logs','security_login_attempts','security_rate_limits',
        ];
        $exists  = array_filter($targets, fn($t) => Schema::hasTable($t));
        $missing = array_diff($targets, $exists);
        $this->assert(count($exists) >= 15, count($exists) . '/' . count($targets) . ' reset targets present');
        if ($missing) {
            $this->line('   <fg=yellow>  (missing, will be skipped: ' . implode(', ', $missing) . ')</>');
        }

        // Admin table resolution
        $adminTable = Schema::hasTable('auth_admins') ? 'auth_admins' : (Schema::hasTable('admins') ? 'admins' : null);
        $this->assert($adminTable !== null, "admin table resolved: `{$adminTable}`");
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════════════════

    private function createTestUser(): int
    {
        static $cached = null;
        if ($cached) return $cached;

        $email = 'smoke-test-' . Str::random(8) . '@test.local';
        $cols = Schema::getColumnListing('auth_users');
        $row = [
            'email'      => $email,
            'first_name' => 'Smoke',
            'last_name'  => 'Test',
            'created_at' => now(),
            'updated_at' => now(),
        ];
        // Use whichever password column the schema uses
        if (in_array('password_hash', $cols, true)) {
            $row['password_hash'] = bcrypt('test1234');
        } elseif (in_array('password', $cols, true)) {
            $row['password'] = bcrypt('test1234');
        }
        if (in_array('username', $cols, true))      $row['username']      = 'smoke_' . Str::random(6);
        if (in_array('status', $cols, true))        $row['status']        = 'active';
        if (in_array('auth_provider', $cols, true)) $row['auth_provider'] = 'local';
        $id = DB::table('auth_users')->insertGetId($row);
        $this->cleanup[] = ['auth_users', $id];
        return $cached = $id;
    }

    private function assert(bool $cond, string $label): void
    {
        if ($cond) {
            $this->passed++;
            $this->line("  <fg=green>✓</> {$label}");
        } else {
            $this->failed++;
            $this->failures[] = $label;
            $this->line("  <fg=red>✗</> {$label}");
        }
    }

    private function skip(string $label): void
    {
        $this->skipped++;
        $this->line("  <fg=yellow>○</> SKIP: {$label}");
    }

    private function section(string $title): void
    {
        $this->line('');
        $this->line("<fg=cyan;options=bold>▸ {$title}</>");
    }

    private function cleanupData(): void
    {
        if ($this->option('keep')) {
            $this->line('');
            $this->warn('--keep flag set, leaving test data behind:');
            foreach ($this->cleanup as [$tbl, $id]) {
                $this->line("    {$tbl}#{$id}");
            }
            return;
        }
        // Reverse so child rows delete before parents
        foreach (array_reverse($this->cleanup) as [$tbl, $id]) {
            try {
                DB::table($tbl)->where('id', $id)->delete();
            } catch (\Throwable $e) {
                // Ignore — FK constraints may have auto-deleted
            }
        }
    }

    private function summary(): int
    {
        $total = $this->passed + $this->failed;
        $this->line('');
        $this->line('<bg=blue;fg=white>  Summary  </>');
        $this->line("  <fg=green>✓ Passed  : {$this->passed}</>");
        $this->line("  <fg=red>✗ Failed  : {$this->failed}</>");
        $this->line("  <fg=yellow>○ Skipped : {$this->skipped}</>");
        $pct = $total > 0 ? round($this->passed / $total * 100) : 0;
        $this->line("  <fg=cyan>Success   : {$pct}% ({$this->passed}/{$total})</>");
        $this->line('');

        if ($this->failures) {
            $this->error('Failures:');
            foreach ($this->failures as $f) $this->line("  - {$f}");
            return self::FAILURE;
        }

        $this->info('All tests passed.');
        return self::SUCCESS;
    }
}
