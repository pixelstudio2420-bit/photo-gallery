<?php

namespace App\Services\Deployment;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PDO;

/**
 * Pre-flight tests for deployment settings.
 *
 * Every test method takes the proposed values directly (NOT from .env) so the
 * admin can verify "would this DB connection work?" BEFORE saving and breaking
 * the live site. Each method returns a uniform shape:
 *
 *   ['ok' => bool, 'message' => string, 'details' => array (optional)]
 */
class DeploymentTesterService
{
    // -------------------------------------------------------------------------
    // Database (MySQL / MariaDB)
    // -------------------------------------------------------------------------

    public function testDatabase(
        string $host,
        int|string $port,
        string $database,
        string $username,
        string $password,
        string $charset = 'utf8mb4',
    ): array {
        try {
            // Use the active app default connection's driver to decide which DSN
            // to build — keeps the probe flexible across MySQL & Postgres deployments.
            $driver = DB::connection()->getDriverName();
            if ($driver === 'pgsql') {
                $dsn = sprintf(
                    'pgsql:host=%s;port=%s;dbname=%s',
                    $host,
                    (string) $port,
                    $database,
                );
                $pdo = new PDO($dsn, $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 5,
                ]);
                $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
                $tables  = (int) $pdo->query("SELECT COUNT(*) FROM pg_tables WHERE schemaname = 'public'")
                    ->fetchColumn();
                return [
                    'ok'      => true,
                    'message' => "เชื่อมต่อสำเร็จ — PostgreSQL {$version}",
                    'details' => [
                        'version'    => $version,
                        'tables'     => $tables,
                        'database'   => $database,
                    ],
                ];
            }

            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $host,
                (string) $port,
                $database,
                $charset,
            );
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);
            $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
            $tables  = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')
                ->fetchColumn();

            return [
                'ok'      => true,
                'message' => "เชื่อมต่อสำเร็จ — MySQL/MariaDB {$version}",
                'details' => [
                    'version'    => $version,
                    'tables'     => $tables,
                    'database'   => $database,
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'ok'      => false,
                'message' => 'เชื่อมต่อไม่ได้: ' . $this->shortenError($e->getMessage()),
                'details' => ['error' => $e->getMessage()],
            ];
        }
    }

    // -------------------------------------------------------------------------
    // SMTP / Mail
    // -------------------------------------------------------------------------

    /**
     * Send a test email with the supplied SMTP creds. Uses runtime config
     * override so the live config is not affected.
     */
    public function testMail(
        string $to,
        string $host,
        int|string $port,
        ?string $username,
        ?string $password,
        ?string $encryption,
        string $fromAddr = 'no-reply@example.com',
        string $fromName = 'Deployment Test',
    ): array {
        try {
            // Override config at runtime for this request only.
            config([
                'mail.default'                       => 'smtp',
                'mail.mailers.smtp.transport'        => 'smtp',
                'mail.mailers.smtp.host'             => $host,
                'mail.mailers.smtp.port'             => (int) $port,
                'mail.mailers.smtp.username'         => $username,
                'mail.mailers.smtp.password'         => $password,
                'mail.mailers.smtp.encryption'       => $encryption === 'null' ? null : $encryption,
                'mail.mailers.smtp.timeout'          => 10,
                'mail.from.address'                  => $fromAddr,
                'mail.from.name'                     => $fromName,
            ]);

            // Force a fresh mailer instance with the new config.
            app('mail.manager')->forgetMailers();

            Mail::raw(
                "Deployment SMTP Test\nTime: " . now()->toDateTimeString(),
                function ($m) use ($to) {
                    $m->to($to)->subject('🔧 Deployment SMTP Test');
                }
            );

            return [
                'ok'      => true,
                'message' => "ส่งอีเมลทดสอบไปยัง {$to} สำเร็จ — เช็คใน inbox",
            ];
        } catch (\Throwable $e) {
            return [
                'ok'      => false,
                'message' => 'ส่งไม่สำเร็จ: ' . $this->shortenError($e->getMessage()),
                'details' => ['error' => $e->getMessage()],
            ];
        }
    }

    // -------------------------------------------------------------------------
    // File Storage (S3 / R2 / local)
    // -------------------------------------------------------------------------

    /**
     * Test that we can write, read, and delete a small file on the configured
     * storage disk. Uses the live disk config — admin should save first if
     * they want to test newly-entered creds.
     */
    public function testStorage(string $disk = 's3'): array
    {
        try {
            $key = 'deployment-test/' . now()->format('Ymd_His') . '_' . uniqid() . '.txt';
            $body = 'deployment test ' . now()->toIso8601String();

            $storage = Storage::disk($disk);
            $storage->put($key, $body);
            $exists = $storage->exists($key);
            $read   = $storage->get($key);
            $storage->delete($key);

            $ok = $exists && $read === $body;
            return [
                'ok'      => $ok,
                'message' => $ok
                    ? "เขียน + อ่าน + ลบไฟล์บน disk \"{$disk}\" สำเร็จ"
                    : "ทดสอบไม่ผ่าน — ตรวจสอบ permissions / bucket / endpoint",
                'details' => ['disk' => $disk, 'driver' => config("filesystems.disks.{$disk}.driver", '?')],
            ];
        } catch (\Throwable $e) {
            return [
                'ok'      => false,
                'message' => 'ทดสอบ storage ไม่ผ่าน: ' . $this->shortenError($e->getMessage()),
                'details' => ['error' => $e->getMessage()],
            ];
        }
    }

    // -------------------------------------------------------------------------
    // Cache
    // -------------------------------------------------------------------------

    public function testCache(): array
    {
        try {
            $key = 'deployment.test.' . uniqid();
            $val = (string) now()->timestamp;
            Cache::put($key, $val, 5);
            $back = Cache::get($key);
            Cache::forget($key);
            $ok = $back === $val;
            return [
                'ok'      => $ok,
                'message' => $ok ? 'Cache อ่านเขียนได้ปกติ' : 'Cache เขียนแล้วอ่านไม่ได้',
                'details' => ['driver' => config('cache.default')],
            ];
        } catch (\Throwable $e) {
            return [
                'ok'      => false,
                'message' => 'Cache ใช้ไม่ได้: ' . $this->shortenError($e->getMessage()),
                'details' => ['error' => $e->getMessage()],
            ];
        }
    }

    // -------------------------------------------------------------------------
    // System health (overall snapshot)
    // -------------------------------------------------------------------------

    public function health(): array
    {
        return [
            'php' => [
                'version'   => PHP_VERSION,
                'meets_min' => version_compare(PHP_VERSION, '8.2.0', '>='),
            ],
            'extensions' => $this->checkExtensions([
                'pdo', 'pdo_mysql', 'mbstring', 'tokenizer', 'json',
                'curl', 'fileinfo', 'openssl', 'gd', 'bcmath', 'xml', 'zip',
            ]),
            'permissions' => [
                'storage_writable'   => is_writable(storage_path()),
                'bootstrap_writable' => is_writable(base_path('bootstrap/cache')),
                'env_writable'       => is_file(base_path('.env'))
                    ? is_writable(base_path('.env'))
                    : is_writable(base_path()),
            ],
            'limits' => [
                'memory_limit'         => ini_get('memory_limit'),
                'upload_max_filesize'  => ini_get('upload_max_filesize'),
                'post_max_size'        => ini_get('post_max_size'),
                'max_execution_time'   => ini_get('max_execution_time'),
            ],
            'database' => $this->databaseStatus(),
            'cache'    => $this->cacheLiveStatus(),
            'storage'  => $this->storageLiveStatus(),
        ];
    }

    private function databaseStatus(): array
    {
        try {
            DB::connection()->getPdo();
            $driver = DB::connection()->getDriverName();
            $version = DB::select('SELECT VERSION() as v')[0]->v ?? '?';
            if ($driver === 'pgsql') {
                $tables = count(DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'"));
            } else {
                $tables = count(DB::select('SHOW TABLES'));
            }
            return [
                'ok'       => true,
                'driver'   => $driver,
                'version'  => $version,
                'tables'   => $tables,
                'database' => DB::connection()->getDatabaseName(),
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $this->shortenError($e->getMessage())];
        }
    }

    private function cacheLiveStatus(): array
    {
        try {
            $key = 'health.' . uniqid();
            Cache::put($key, '1', 5);
            $val = Cache::get($key);
            Cache::forget($key);
            return ['ok' => $val === '1', 'driver' => config('cache.default')];
        } catch (\Throwable $e) {
            return ['ok' => false, 'driver' => config('cache.default'), 'error' => $this->shortenError($e->getMessage())];
        }
    }

    private function storageLiveStatus(): array
    {
        $disk = config('filesystems.default', 'local');
        try {
            $key = 'health/' . uniqid() . '.txt';
            Storage::disk($disk)->put($key, '1');
            $exists = Storage::disk($disk)->exists($key);
            Storage::disk($disk)->delete($key);
            return ['ok' => (bool) $exists, 'disk' => $disk];
        } catch (\Throwable $e) {
            return ['ok' => false, 'disk' => $disk, 'error' => $this->shortenError($e->getMessage())];
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function checkExtensions(array $list): array
    {
        $result = [];
        foreach ($list as $ext) {
            $result[$ext] = extension_loaded($ext);
        }
        return $result;
    }

    /** Trim very long error messages so the UI doesn't break with a 5-line stack trace blob. */
    private function shortenError(string $msg, int $max = 240): string
    {
        $msg = preg_replace('/\s+/', ' ', trim($msg));
        return strlen($msg) > $max ? substr($msg, 0, $max - 3) . '...' : $msg;
    }
}
