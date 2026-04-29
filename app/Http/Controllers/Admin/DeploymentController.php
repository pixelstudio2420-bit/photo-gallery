<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Deployment\DeploymentTesterService;
use App\Services\Deployment\EnvManagerService;
use App\Services\Deployment\InstallMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Server / deployment configuration UI for admins.
 *
 * Surface:
 *   GET  /admin/deployment                       — main page
 *   POST /admin/deployment/save                  — save section (app|database|mail|storage)
 *   POST /admin/deployment/test/database         — test DB credentials BEFORE saving
 *   POST /admin/deployment/test/mail             — send a test email
 *   POST /admin/deployment/test/storage          — read/write/delete on a disk
 *   POST /admin/deployment/test/cache            — set/get/forget on cache driver
 *   POST /admin/deployment/backup                — manual .env backup snapshot
 *
 * Settings flow back to .env (NOT app_settings) because Laravel reads them on
 * bootstrap before the DB is even available. After save, the controller calls
 * `config:clear` so the next request picks up the new values.
 */
class DeploymentController extends Controller
{
    public function __construct(
        private EnvManagerService $env,
        private DeploymentTesterService $tester,
    ) {}

    public function index()
    {
        $envValues = $this->env->getAll();
        $health    = $this->tester->health();
        $writable  = $this->env->isWritable();
        $backups   = $this->env->listBackups(8);

        return view('admin.deployment.index', [
            'env'           => $envValues,
            'health'        => $health,
            'envExists'     => $this->env->exists(),
            'envWritable'   => $writable,
            'backups'       => $backups,
            'urlGroups'     => $this->buildUrlGroups($envValues['APP_URL'] ?? config('app.url')),
            'installActive' => InstallMode::isActive(),
            'installStage'  => InstallMode::stage(),
            'installReason' => InstallMode::reason(),
        ]);
    }

    /**
     * Static deployment guide — readable during install mode (no admin login
     * needed) so operators can reference the guide while bootstrapping the
     * server. Same access rules as the deployment page.
     */
    public function guide()
    {
        return view('admin.deployment.guide', [
            'installActive' => InstallMode::isActive(),
        ]);
    }

    /**
     * Build the list of URLs that admins must paste into external provider
     * dashboards after deployment. Auto-derived from APP_URL so changing the
     * domain instantly updates every URL — no copy/paste mistakes.
     */
    private function buildUrlGroups(string $appUrl): array
    {
        $base = rtrim($appUrl, '/');

        return [
            // ── OAuth (customer-facing) ─────────────────────────────────
            'oauth_public' => [
                'title'       => 'OAuth Login Callbacks (ลูกค้า)',
                'description' => 'ลูกค้ากด "Login ด้วย Google/LINE" แล้ว provider redirect กลับมาที่ URL นี้',
                'items'       => [
                    [
                        'service'     => 'Google Login',
                        'icon'        => 'bi-google',
                        'color'       => '#ef4444',
                        'register_at' => 'https://console.cloud.google.com/apis/credentials',
                        'register_label' => 'Google Cloud Console',
                        'url_label'   => 'Authorized redirect URI',
                        'url'         => $base . '/auth/google/callback',
                    ],
                    [
                        'service'     => 'LINE Login',
                        'icon'        => 'bi-chat-dots-fill',
                        'color'       => '#06C755',
                        'register_at' => 'https://developers.line.biz/console/',
                        'register_label' => 'LINE Developers Console',
                        'url_label'   => 'Callback URL',
                        'url'         => $base . '/auth/line/callback',
                    ],
                    [
                        'service'     => 'Facebook Login',
                        'icon'        => 'bi-facebook',
                        'color'       => '#1877F2',
                        'register_at' => 'https://developers.facebook.com/apps/',
                        'register_label' => 'Meta for Developers',
                        'url_label'   => 'Valid OAuth Redirect URI',
                        'url'         => $base . '/auth/facebook/callback',
                    ],
                ],
            ],

            // ── OAuth (photographer-facing) ─────────────────────────────
            'oauth_photographer' => [
                'title'       => 'OAuth Login Callbacks (ช่างภาพ)',
                'description' => 'ใช้ Google/LINE channel ตัวเดียวกันแต่ paths คนละ URL — เพิ่มในรายการ redirect URIs ของ OAuth app เดียวกัน',
                'items'       => [
                    [
                        'service'     => 'Google (ช่างภาพ)',
                        'icon'        => 'bi-google',
                        'color'       => '#ef4444',
                        'register_at' => 'https://console.cloud.google.com/apis/credentials',
                        'register_label' => 'Google Cloud Console',
                        'url_label'   => 'Authorized redirect URI (เพิ่มอีก 1 URL)',
                        'url'         => $base . '/photographer/auth/google/callback',
                    ],
                    [
                        'service'     => 'LINE (ช่างภาพ)',
                        'icon'        => 'bi-chat-dots-fill',
                        'color'       => '#06C755',
                        'register_at' => 'https://developers.line.biz/console/',
                        'register_label' => 'LINE Developers Console',
                        'url_label'   => 'Callback URL (เพิ่มอีก 1 URL)',
                        'url'         => $base . '/photographer/auth/line/callback',
                    ],
                ],
            ],

            // ── Payment Webhooks ────────────────────────────────────────
            'webhooks_payment' => [
                'title'       => 'Payment Webhooks',
                'description' => 'ผู้ให้บริการชำระเงินส่ง event มา (เช่น "ลูกค้าจ่ายเงินเสร็จ") — ระบบใช้ยืนยัน auto',
                'items'       => [
                    [
                        'service'     => 'Stripe',
                        'icon'        => 'bi-credit-card-2-front-fill',
                        'color'       => '#635BFF',
                        'register_at' => 'https://dashboard.stripe.com/webhooks',
                        'register_label' => 'Stripe Dashboard → Developers → Webhooks',
                        'url_label'   => 'Endpoint URL',
                        'url'         => $base . '/api/webhooks/stripe',
                    ],
                    [
                        'service'     => 'Omise (Charge)',
                        'icon'        => 'bi-credit-card',
                        'color'       => '#1E40FB',
                        'register_at' => 'https://dashboard.omise.co/test/webhooks',
                        'register_label' => 'Omise Dashboard → Webhooks',
                        'url_label'   => 'Webhook URL',
                        'url'         => $base . '/api/webhooks/omise',
                    ],
                    [
                        'service'     => 'Omise (Transfer)',
                        'icon'        => 'bi-bank',
                        'color'       => '#1E40FB',
                        'register_at' => 'https://dashboard.omise.co/test/webhooks',
                        'register_label' => 'Omise Dashboard → Webhooks',
                        'url_label'   => 'Webhook URL (transfer events)',
                        'url'         => $base . '/api/webhooks/omise/transfers',
                    ],
                    [
                        'service'     => 'LINE Pay',
                        'icon'        => 'bi-chat-dots-fill',
                        'color'       => '#06C755',
                        'register_at' => 'https://pay.line.me/portal/',
                        'register_label' => 'LINE Pay Merchant Portal',
                        'url_label'   => 'ConfirmUrl / WebhookUrl',
                        'url'         => $base . '/api/webhooks/linepay',
                    ],
                    [
                        'service'     => 'PayPal',
                        'icon'        => 'bi-paypal',
                        'color'       => '#003087',
                        'register_at' => 'https://developer.paypal.com/dashboard/applications',
                        'register_label' => 'PayPal Developer Dashboard',
                        'url_label'   => 'Webhook URL',
                        'url'         => $base . '/api/webhooks/paypal',
                    ],
                    [
                        'service'     => 'TrueMoney Wallet',
                        'icon'        => 'bi-wallet2',
                        'color'       => '#FF6E00',
                        'register_at' => '#',
                        'register_label' => 'TrueMoney Merchant Portal',
                        'url_label'   => 'Webhook URL',
                        'url'         => $base . '/api/webhooks/truemoney',
                    ],
                    [
                        'service'     => '2C2P',
                        'icon'        => 'bi-credit-card-fill',
                        'color'       => '#1E40FB',
                        'register_at' => 'https://merchantportal.2c2p.com/',
                        'register_label' => '2C2P Merchant Portal',
                        'url_label'   => 'Backend Notification URL',
                        'url'         => $base . '/api/webhooks/2c2p',
                    ],
                    [
                        'service'     => 'SlipOK (PromptPay)',
                        'icon'        => 'bi-qr-code-scan',
                        'color'       => '#10b981',
                        'register_at' => 'https://slipok.com/',
                        'register_label' => 'SlipOK Dashboard',
                        'url_label'   => 'Webhook URL (auto-verify slip)',
                        'url'         => $base . '/api/webhooks/slipok',
                    ],
                ],
            ],

            // ── LINE Messaging webhook ──────────────────────────────────
            'webhooks_line' => [
                'title'       => 'LINE Messaging API Webhook',
                'description' => 'OA รับ message ลูกค้า / event ลงในระบบ — เปิดใน Messaging API channel settings',
                'items'       => [
                    [
                        'service'     => 'LINE OA Webhook',
                        'icon'        => 'bi-chat-quote-fill',
                        'color'       => '#06C755',
                        'register_at' => 'https://developers.line.biz/console/',
                        'register_label' => 'LINE Developers → Messaging API Channel → Webhook settings',
                        'url_label'   => 'Webhook URL (เปิด "Use webhook" + "Verify")',
                        'url'         => $base . '/api/webhooks/line',
                    ],
                ],
            ],

            // ── Other ───────────────────────────────────────────────────
            'webhooks_other' => [
                'title'       => 'Other Service Webhooks',
                'description' => 'ใช้เมื่อเปิดบริการเสริม — ปกติไม่ต้องตั้งถ้าไม่ได้ใช้',
                'items'       => [
                    [
                        'service'     => 'Facebook (Messenger/Page)',
                        'icon'        => 'bi-facebook',
                        'color'       => '#1877F2',
                        'register_at' => 'https://developers.facebook.com/apps/',
                        'register_label' => 'Meta for Developers',
                        'url_label'   => 'Callback URL',
                        'url'         => $base . '/api/webhooks/facebook',
                    ],
                    [
                        'service'     => 'Google Drive Push',
                        'icon'        => 'bi-google',
                        'color'       => '#fbbc04',
                        'register_at' => '#',
                        'register_label' => 'Google Drive API watch()',
                        'url_label'   => 'Notification address',
                        'url'         => $base . '/api/webhooks/google-drive',
                    ],
                ],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Save
    // -------------------------------------------------------------------------

    public function save(Request $request)
    {
        if (!$this->env->isWritable()) {
            return back()->with('error', '.env file ไม่สามารถเขียนได้ — ตรวจสอบ file permissions บนเซิร์ฟเวอร์');
        }

        $section = (string) $request->input('section', '');
        $updates = match ($section) {
            'app'      => $this->validateApp($request),
            'database' => $this->validateDatabase($request),
            'mail'     => $this->validateMail($request),
            'storage'  => $this->validateStorage($request),
            default    => null,
        };

        if ($updates === null) {
            return back()->with('error', 'Section ไม่ถูกต้อง');
        }

        $ok = $this->env->set($updates);
        if (!$ok) {
            return back()->with('error', 'บันทึก .env ไม่สำเร็จ — ตรวจสอบ disk space + permissions');
        }

        // Clear cached config so the new values take effect.
        try {
            Artisan::call('config:clear');
        } catch (\Throwable $e) {
            Log::warning('deployment.config_clear_failed', ['err' => $e->getMessage()]);
        }

        return back()->with('success', 'บันทึก ' . $section . ' สำเร็จ — ระบบจะใช้ค่าใหม่ในคำขอถัดไป (อาจใช้เวลา 1-3 วินาที)');
    }

    private function validateApp(Request $request): array
    {
        $valid = $request->validate([
            'app_name'     => ['required', 'string', 'max:120'],
            'app_url'      => ['required', 'url:http,https', 'max:255'],
            'app_env'      => ['required', 'in:local,staging,production'],
            'app_debug'    => ['nullable'],
            'app_timezone' => ['required', 'string', 'max:50'],
            'app_locale'   => ['required', 'string', 'max:10'],
        ]);

        return [
            'APP_NAME'     => $valid['app_name'],
            'APP_URL'      => rtrim($valid['app_url'], '/'),
            'APP_ENV'      => $valid['app_env'],
            'APP_DEBUG'    => $request->has('app_debug') ? 'true' : 'false',
            'APP_TIMEZONE' => $valid['app_timezone'],
            'APP_LOCALE'   => $valid['app_locale'],
        ];
    }

    private function validateDatabase(Request $request): array
    {
        $valid = $request->validate([
            'db_connection' => ['required', 'in:mysql,mariadb,sqlite,pgsql'],
            'db_host'       => ['required_unless:db_connection,sqlite', 'nullable', 'string', 'max:255'],
            'db_port'       => ['required_unless:db_connection,sqlite', 'nullable', 'integer', 'between:1,65535'],
            'db_database'   => ['required', 'string', 'max:128'],
            'db_username'   => ['required_unless:db_connection,sqlite', 'nullable', 'string', 'max:64'],
            'db_password'   => ['nullable', 'string'],
        ]);

        $update = [
            'DB_CONNECTION' => $valid['db_connection'],
            'DB_HOST'       => $valid['db_host']     ?? '',
            'DB_PORT'       => (string) ($valid['db_port'] ?? ''),
            'DB_DATABASE'   => $valid['db_database'],
            'DB_USERNAME'   => $valid['db_username'] ?? '',
        ];

        // Only update password if explicitly provided (so admin doesn't
        // accidentally blank a working password by leaving the field empty).
        if ($request->filled('db_password')) {
            $update['DB_PASSWORD'] = $valid['db_password'];
        }
        return $update;
    }

    private function validateMail(Request $request): array
    {
        $valid = $request->validate([
            'mail_mailer'      => ['required', 'in:smtp,log,sendmail,mailgun,ses'],
            'mail_host'        => ['required_if:mail_mailer,smtp', 'nullable', 'string', 'max:255'],
            'mail_port'        => ['required_if:mail_mailer,smtp', 'nullable', 'integer', 'between:1,65535'],
            'mail_username'    => ['nullable', 'string', 'max:255'],
            'mail_password'    => ['nullable', 'string'],
            'mail_encryption'  => ['nullable', 'in:tls,ssl,null'],
            'mail_from_address'=> ['required', 'email', 'max:255'],
            'mail_from_name'   => ['required', 'string', 'max:120'],
        ]);

        $update = [
            'MAIL_MAILER'        => $valid['mail_mailer'],
            'MAIL_HOST'          => $valid['mail_host']      ?? '',
            'MAIL_PORT'          => (string) ($valid['mail_port'] ?? ''),
            'MAIL_USERNAME'      => $valid['mail_username']  ?? '',
            'MAIL_ENCRYPTION'    => $valid['mail_encryption'] === 'null' ? '' : ($valid['mail_encryption'] ?? ''),
            'MAIL_FROM_ADDRESS'  => $valid['mail_from_address'],
            'MAIL_FROM_NAME'     => $valid['mail_from_name'],
        ];
        if ($request->filled('mail_password')) {
            $update['MAIL_PASSWORD'] = $valid['mail_password'];
        }
        return $update;
    }

    private function validateStorage(Request $request): array
    {
        $valid = $request->validate([
            'filesystem_disk' => ['required', 'in:local,public,s3'],
            // S3 / R2 fields — only required when picking s3.
            'aws_access_key_id'           => ['required_if:filesystem_disk,s3', 'nullable', 'string', 'max:255'],
            'aws_secret_access_key'       => ['nullable', 'string'],
            'aws_default_region'          => ['required_if:filesystem_disk,s3', 'nullable', 'string', 'max:50'],
            'aws_bucket'                  => ['required_if:filesystem_disk,s3', 'nullable', 'string', 'max:128'],
            'aws_endpoint'                => ['nullable', 'url', 'max:255'],
            'aws_use_path_style_endpoint' => ['nullable'],
            'aws_url'                     => ['nullable', 'url', 'max:255'],
        ]);

        $update = [
            'FILESYSTEM_DISK'      => $valid['filesystem_disk'],
            'AWS_ACCESS_KEY_ID'    => $valid['aws_access_key_id']   ?? '',
            'AWS_DEFAULT_REGION'   => $valid['aws_default_region']  ?? 'auto',
            'AWS_BUCKET'           => $valid['aws_bucket']          ?? '',
            'AWS_ENDPOINT'         => $valid['aws_endpoint']        ?? '',
            'AWS_USE_PATH_STYLE_ENDPOINT' => $request->has('aws_use_path_style_endpoint') ? 'true' : 'false',
            'AWS_URL'              => $valid['aws_url']             ?? '',
        ];
        if ($request->filled('aws_secret_access_key')) {
            $update['AWS_SECRET_ACCESS_KEY'] = $valid['aws_secret_access_key'];
        }
        return $update;
    }

    // -------------------------------------------------------------------------
    // Tests (BEFORE save — admin can verify creds without committing them)
    // -------------------------------------------------------------------------

    public function testDatabase(Request $request)
    {
        $valid = $request->validate([
            'db_host'     => ['required', 'string'],
            'db_port'     => ['required', 'integer'],
            'db_database' => ['required', 'string'],
            'db_username' => ['required', 'string'],
            'db_password' => ['nullable', 'string'],
        ]);

        $result = $this->tester->testDatabase(
            $valid['db_host'],
            (int) $valid['db_port'],
            $valid['db_database'],
            $valid['db_username'],
            (string) ($valid['db_password'] ?? ''),
        );
        return response()->json($result);
    }

    public function testMail(Request $request)
    {
        $valid = $request->validate([
            'to'         => ['required', 'email'],
            'host'       => ['required', 'string'],
            'port'       => ['required', 'integer'],
            'username'   => ['nullable', 'string'],
            'password'   => ['nullable', 'string'],
            'encryption' => ['nullable', 'in:tls,ssl,null'],
            'from_addr'  => ['nullable', 'email'],
            'from_name'  => ['nullable', 'string'],
        ]);

        $result = $this->tester->testMail(
            $valid['to'],
            $valid['host'],
            (int) $valid['port'],
            $valid['username'] ?? null,
            $valid['password'] ?? null,
            $valid['encryption'] ?? null,
            $valid['from_addr'] ?? config('mail.from.address', 'no-reply@example.com'),
            $valid['from_name'] ?? config('mail.from.name', 'Deployment Test'),
        );
        return response()->json($result);
    }

    public function testStorage(Request $request)
    {
        $disk = (string) $request->input('disk', config('filesystems.default', 'local'));
        return response()->json($this->tester->testStorage($disk));
    }

    public function testCache()
    {
        return response()->json($this->tester->testCache());
    }

    // -------------------------------------------------------------------------
    // .env backup
    // -------------------------------------------------------------------------

    public function backup()
    {
        $path = $this->env->backup();
        if ($path === '') {
            return back()->with('error', 'สำรอง .env ไม่สำเร็จ — ตรวจสอบ permissions ของ storage/app/env-backups');
        }
        return back()->with('success', 'สำรองไฟล์ .env สำเร็จ → ' . basename($path));
    }

    // -------------------------------------------------------------------------
    // INSTALL MODE actions — only callable while InstallMode::isActive()
    // -------------------------------------------------------------------------

    /**
     * Generate APP_KEY when missing. Equivalent to `php artisan key:generate`
     * but callable from the web installer so non-technical operators don't
     * need shell access.
     */
    public function generateAppKey()
    {
        if (!InstallMode::isActive() && !auth('admin')->check()) {
            abort(403);
        }

        try {
            // Re-implement key:generate to avoid Artisan call complexity.
            // Laravel uses base64-encoded random 32-byte string (AES-256).
            $key = 'base64:' . base64_encode(random_bytes(32));
            $ok  = $this->env->set(['APP_KEY' => $key]);

            if (!$ok) {
                return back()->with('error', 'เขียน APP_KEY ลง .env ไม่สำเร็จ — ตรวจสอบ file permissions');
            }

            try { Artisan::call('config:clear'); } catch (\Throwable) {}

            return back()->with('success', 'สร้าง APP_KEY สำเร็จ — โหลดหน้าใหม่');
        } catch (\Throwable $e) {
            Log::error('install.key_generate_failed', ['err' => $e->getMessage()]);
            return back()->with('error', 'สร้าง APP_KEY ไม่สำเร็จ: ' . $e->getMessage());
        }
    }

    /**
     * Run pending migrations. Web equivalent of `php artisan migrate --force`.
     * Limited to install mode OR authenticated admin.
     */
    public function runMigrations()
    {
        if (!InstallMode::isActive() && !auth('admin')->check()) {
            abort(403);
        }

        try {
            // Verify DB connection BEFORE attempting to migrate.
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            return back()->with('error', 'DB connection ใช้ไม่ได้ — ตั้งค่า Database tab ก่อน: ' . $e->getMessage());
        }

        try {
            // --force is required because we're in non-CLI environment.
            Artisan::call('migrate', ['--force' => true]);
            $output = Artisan::output();

            // Trim noise + trailing newlines for the flash message.
            $summary = trim(preg_replace('/\s+/', ' ', $output));
            $summary = strlen($summary) > 240 ? substr($summary, 0, 237) . '...' : $summary;

            return back()->with('success', 'รัน migrations สำเร็จ — ' . $summary);
        } catch (\Throwable $e) {
            Log::error('install.migrate_failed', ['err' => $e->getMessage()]);
            return back()->with('error', 'รัน migrations ไม่สำเร็จ: ' . $e->getMessage());
        }
    }

    /**
     * Create the first super-admin user. Only allowed when no admin exists yet.
     * After this, install mode auto-ends and the page will require admin login.
     *
     * This app stores admins in the `auth_admins` table (separate from regular
     * customer/photographer users in `auth_users`).
     */
    public function createFirstAdmin(Request $request)
    {
        // Hard guardrail — refuse if an admin already exists, regardless of
        // mode flag. Prevents privilege escalation if an attacker reaches
        // this endpoint after install.
        if (!Schema::hasTable('auth_admins')) {
            return back()->with('error', 'table "auth_admins" ไม่พบ — รัน migrations ก่อน');
        }

        // Match the same gate as InstallMode — only count active admins with
        // a recognised role. (A leftover row with bad role doesn't block re-install.)
        $existing = DB::table('auth_admins')
            ->where('is_active', 1)
            ->whereIn('role', ['superadmin', 'admin', 'editor'])
            ->count();
        if ($existing > 0) {
            return back()->with('error', 'มี admin user อยู่แล้ว — ไม่สามารถสร้างซ้ำผ่านหน้า install ได้');
        }

        $valid = $request->validate([
            'admin_name'     => ['required', 'string', 'max:120'],
            'admin_email'    => ['required', 'email', 'max:180', 'unique:auth_admins,email'],
            'admin_password' => ['required', 'string', 'min:8', 'max:128', 'confirmed'],
        ]);

        try {
            // Split "First Last" into 2 columns. Be tolerant of single-word names.
            $parts = preg_split('/\s+/', trim($valid['admin_name']), 2);
            $firstName = $parts[0] ?? 'Super';
            $lastName  = $parts[1] ?? 'Admin';

            $row = [
                'email'         => $valid['admin_email'],
                'password_hash' => Hash::make($valid['admin_password']),
                'first_name'    => $firstName,
                'last_name'     => $lastName,
                // ⚠️ Role MUST be 'superadmin' (no underscore) to match
                // App\Models\Admin::ROLE_SUPERADMIN. Using 'super_admin'
                // makes isSuperAdmin() return false and the user falls back
                // to Editor role with no permissions.
                'role'          => \App\Models\Admin::ROLE_SUPERADMIN,
                'permissions'   => null, // superadmin bypasses permission checks
                'is_active'     => 1,
                'created_at'    => now(),
            ];

            DB::table('auth_admins')->insert($row);

            Log::info('install.first_admin_created', ['email' => $valid['admin_email']]);

            return redirect()
                ->route('admin.login')
                ->with('success', 'สร้าง super-admin คนแรกสำเร็จ — login ด้วย ' . $valid['admin_email']);
        } catch (\Throwable $e) {
            Log::error('install.create_admin_failed', ['err' => $e->getMessage()]);
            return back()->with('error', 'สร้าง admin ไม่สำเร็จ: ' . $e->getMessage());
        }
    }
}
