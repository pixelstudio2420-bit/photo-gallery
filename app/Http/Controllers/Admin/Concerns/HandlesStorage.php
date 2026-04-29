<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\AppSetting;
use App\Services\StorageManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * STORAGE ORCHESTRATION + BACKUP
 *
 * Extracted from SettingsController. Method signatures and route names
 * unchanged — trait is `use`d by the parent controller.
 *
 * Routes touched:
 *   • admin.settings.storage          — storage()
 *   • admin.settings.storage.update   — updateStorage()
 *   • admin.settings.storage.probe    — probeStorage()
 *   • admin.settings.backup           — backup()
 *   • admin.settings.backup.database  — backupDatabase()
 *   • admin.settings.backup.files     — backupFiles()
 *   • admin.settings.backup.full      — backupFull()
 *   • admin.settings.backup.download  — backupDownload()
 *   • admin.settings.backup.delete    — backupDelete()
 */
trait HandlesStorage
{
    /**
     * GET /admin/settings/storage
     * Render the storage orchestration page.
     */
    public function storage()
    {
        $all = AppSetting::getAll();

        $settings = [
            'storage_multi_driver_enabled' => $all['storage_multi_driver_enabled'] ?? '0',
            'storage_drive_enabled'        => $all['storage_drive_enabled']        ?? '1',
            'storage_s3_enabled'           => $all['storage_s3_enabled']           ?? '1',
            'r2_enabled'                   => $all['r2_enabled']                   ?? '0',

            'storage_primary_driver'       => $all['storage_primary_driver']       ?? 'auto',
            'storage_upload_driver'        => $all['storage_upload_driver']        ?? 'auto',
            'storage_zip_disk'             => $all['storage_zip_disk']             ?? 'auto',

            'storage_mirror_enabled'       => $all['storage_mirror_enabled']       ?? '0',
            'storage_mirror_targets'       => $all['storage_mirror_targets']       ?? '[]',

            'storage_use_signed_urls'      => $all['storage_use_signed_urls']      ?? '1',
            'storage_signed_url_ttl'       => $all['storage_signed_url_ttl']       ?? '3600',
            'storage_download_mode'        => $all['storage_download_mode']        ?? 'redirect',
            'storage_drive_read_fallback'  => $all['storage_drive_read_fallback']  ?? '1',
            'storage_zip_retention_hours'  => $all['storage_zip_retention_hours']  ?? '168',
        ];

        $mirrorTargets = json_decode($settings['storage_mirror_targets'], true) ?: [];

        $manager = app(StorageManager::class);
        $health  = $manager->health();

        // Stats — how many photos currently on each disk
        $diskDistribution = DB::table('event_photos')
            ->select('storage_disk', DB::raw('COUNT(*) as count'))
            ->groupBy('storage_disk')
            ->pluck('count', 'storage_disk')
            ->toArray();

        // Use whereJsonLength for cross-driver compatibility:
        // MySQL: JSON_LENGTH(storage_mirrors) > 0
        // Postgres: json_array_length(storage_mirrors) > 0
        // The naive `!= '[]'` string-comparison breaks on PG (no json/text op).
        $mirroredCount = DB::table('event_photos')
            ->whereNotNull('storage_mirrors')
            ->whereJsonLength('storage_mirrors', '>', 0)
            ->count();

        $cloudPhotosCount = DB::table('event_photos')
            ->whereIn('storage_disk', ['r2', 's3'])
            ->count();

        $totalPhotos = DB::table('event_photos')->count();

        $stats = [
            'total'              => $totalPhotos,
            'on_cloud'           => $cloudPhotosCount,
            'mirrored'           => $mirroredCount,
            'disk_distribution'  => $diskDistribution,
            'primary_resolved'   => $manager->primaryDriver(),
            'upload_resolved'    => $manager->uploadDriver(),
            'zip_resolved'       => $manager->zipDisk(),
            'available_drivers'  => $manager->availableDrivers(),
            'mirror_targets_now' => $manager->mirrorTargets(),
        ];

        return view('admin.settings.storage', compact('settings', 'mirrorTargets', 'health', 'stats'));
    }

    /**
     * POST /admin/settings/storage
     */
    public function updateStorage(Request $request)
    {
        $validated = $request->validate([
            'storage_multi_driver_enabled' => 'nullable|boolean',
            'storage_drive_enabled'        => 'nullable|boolean',
            'storage_s3_enabled'           => 'nullable|boolean',
            'r2_enabled'                   => 'nullable|boolean',

            'storage_primary_driver'       => ['required', Rule::in(['auto', 'r2', 's3', 'drive', 'public'])],
            'storage_upload_driver'        => ['required', Rule::in(['auto', 'r2', 's3', 'drive', 'public'])],
            'storage_zip_disk'             => ['required', Rule::in(['auto', 'r2', 's3', 'public'])],

            'storage_mirror_enabled'       => 'nullable|boolean',
            'storage_mirror_targets'       => 'nullable|array',
            'storage_mirror_targets.*'     => ['string', Rule::in(['r2', 's3', 'drive', 'public'])],

            'storage_use_signed_urls'      => 'nullable|boolean',
            'storage_signed_url_ttl'       => 'required|integer|min:60|max:604800',
            'storage_download_mode'        => ['required', Rule::in(['redirect', 'proxy', 'auto'])],
            'storage_drive_read_fallback'  => 'nullable|boolean',
            'storage_zip_retention_hours'  => 'required|integer|min:1|max:8760',
        ]);

        $booleanKeys = [
            'storage_multi_driver_enabled',
            'storage_drive_enabled',
            'storage_s3_enabled',
            'r2_enabled',
            'storage_mirror_enabled',
            'storage_use_signed_urls',
            'storage_drive_read_fallback',
        ];

        $items = [];

        // Normalise booleans to "0"/"1" since AppSetting is string-typed
        foreach ($booleanKeys as $bk) {
            $items[$bk] = $request->boolean($bk) ? '1' : '0';
        }

        $items['storage_primary_driver']      = $validated['storage_primary_driver'];
        $items['storage_upload_driver']       = $validated['storage_upload_driver'];
        $items['storage_zip_disk']            = $validated['storage_zip_disk'];

        $targets = array_values(array_unique($request->input('storage_mirror_targets', [])));
        $items['storage_mirror_targets']      = json_encode($targets);

        $items['storage_signed_url_ttl']      = (string) $validated['storage_signed_url_ttl'];
        $items['storage_download_mode']       = $validated['storage_download_mode'];
        $items['storage_zip_retention_hours'] = (string) $validated['storage_zip_retention_hours'];

        // Bulk-write with a single cache flush at the end.
        AppSetting::setMany($items);

        return back()->with('success', 'บันทึกการตั้งค่า Storage เรียบร้อย');
    }

    /**
     * POST /admin/settings/storage/probe
     * Live connection test for every driver — returns JSON.
     */
    public function probeStorage(Request $request)
    {
        $manager = app(StorageManager::class);

        return response()->json([
            'drivers'  => $manager->health(),
            'resolved' => [
                'primary' => $manager->primaryDriver(),
                'upload'  => $manager->uploadDriver(),
                'zip'     => $manager->zipDisk(),
                'mirrors' => $manager->mirrorTargets(),
            ],
        ]);
    }

    /**
     * GET /admin/settings/storage/test
     *
     * Render a page with per-driver "Run Test" buttons that exercise
     * PUT → GET → DELETE against a small throwaway object. The resulting
     * output is the *raw* error surface from the underlying SDK — which is
     * how we recover useful signals like `AccessDenied` / `NoSuchBucket`
     * that the normal upload path currently swallows behind a generic
     * "Upload failed" message.
     */
    public function storageTest()
    {
        $manager = app(StorageManager::class);

        // Build a redacted config summary for each driver so the operator can
        // eyeball what the server *actually* sees right now (env vs. AppSetting
        // overrides can differ). Never expose secrets — just their presence
        // and the last 4 characters for quick verification.
        $summary = [
            'r2'     => $this->summariseR2Config(),
            's3'     => $this->summariseS3Config(),
            'drive'  => $this->summariseDriveConfig(),
            'public' => $this->summarisePublicConfig(),
        ];

        $enabled = [
            'r2'     => $manager->driverIsEnabled('r2'),
            's3'     => $manager->driverIsEnabled('s3'),
            'drive'  => $manager->driverIsEnabled('drive'),
            'public' => $manager->driverIsEnabled('public'),
        ];

        return view('admin.settings.storage-test', compact('summary', 'enabled'));
    }

    /**
     * POST /admin/settings/storage/test/run
     *
     * Run a PUT → GET → DELETE sequence against ONE driver and return the
     * full operation trail (timings, raw error messages, AWS error codes).
     *
     * Expected body:
     *   driver = r2 | s3 | drive | public
     */
    public function runStorageTest(Request $request)
    {
        $validated = $request->validate([
            'driver' => ['required', Rule::in(['r2', 's3', 'drive', 'public'])],
        ]);

        $driver = $validated['driver'];
        $manager = app(StorageManager::class);

        if (!$manager->driverIsEnabled($driver)) {
            return response()->json([
                'driver'      => $driver,
                'ok'          => false,
                'enabled'     => false,
                'operations'  => [],
                'error'       => "Driver [{$driver}] ยังไม่ได้เปิดใช้งาน — ตรวจสอบสวิตช์ + credential ในหน้า Storage",
            ]);
        }

        // Dispatch to driver-specific runner
        $result = match ($driver) {
            'r2'     => $this->testR2Driver(),
            's3'     => $this->testS3Driver(),
            'drive'  => $this->testDriveDriver(),
            'public' => $this->testPublicDriver(),
        };

        return response()->json($result + ['driver' => $driver, 'enabled' => true]);
    }

    // ─── Config summaries (redacted) ──────────────────────────────────

    /**
     * Redacted R2 config — mirrors what the live Storage disk would use.
     */
    protected function summariseR2Config(): array
    {
        return [
            'enabled'       => AppSetting::get('r2_enabled', '0') === '1',
            'bucket'        => (string) config('filesystems.disks.r2.bucket'),
            'endpoint'      => (string) config('filesystems.disks.r2.endpoint'),
            'public_url'    => (string) (AppSetting::get('r2_public_url', '') ?: config('filesystems.disks.r2.url', '')),
            'custom_domain' => (string) AppSetting::get('r2_custom_domain', ''),
            'key_preview'   => $this->maskSecret((string) config('filesystems.disks.r2.key')),
            'secret_preview'=> $this->maskSecret((string) config('filesystems.disks.r2.secret')),
            'key_present'   => !empty(config('filesystems.disks.r2.key')),
            'secret_present'=> !empty(config('filesystems.disks.r2.secret')),
        ];
    }

    protected function summariseS3Config(): array
    {
        return [
            'enabled'        => AppSetting::get('storage_s3_enabled', '1') === '1',
            'bucket'         => (string) config('filesystems.disks.s3.bucket'),
            'region'         => (string) config('filesystems.disks.s3.region'),
            'url'            => (string) config('filesystems.disks.s3.url'),
            'key_preview'    => $this->maskSecret((string) config('filesystems.disks.s3.key')),
            'secret_preview' => $this->maskSecret((string) config('filesystems.disks.s3.secret')),
            'key_present'    => !empty(config('filesystems.disks.s3.key')),
            'secret_present' => !empty(config('filesystems.disks.s3.secret')),
        ];
    }

    protected function summariseDriveConfig(): array
    {
        return [
            'enabled'         => AppSetting::get('storage_drive_enabled', '1') === '1',
            'folder_id'       => (string) AppSetting::get('drive_folder_id', ''),
            'client_id_preview' => $this->maskSecret((string) AppSetting::get('drive_client_id', '')),
            'client_id_present' => !empty(AppSetting::get('drive_client_id', '')),
            'refresh_token_present' => !empty(AppSetting::get('drive_refresh_token', '')),
        ];
    }

    protected function summarisePublicConfig(): array
    {
        return [
            'enabled' => true,
            'root'    => (string) config('filesystems.disks.public.root'),
            'url'     => (string) config('filesystems.disks.public.url'),
        ];
    }

    /**
     * Show only the last 4 characters of a secret (or "(empty)") — never log it in full.
     */
    protected function maskSecret(string $value): string
    {
        if ($value === '') return '(empty)';
        if (strlen($value) <= 4) return str_repeat('•', strlen($value));
        return str_repeat('•', max(3, strlen($value) - 4)) . substr($value, -4);
    }

    // ─── Per-driver test runners ──────────────────────────────────────

    /**
     * Run PUT → GET → DELETE against R2 via the Laravel Storage disk.
     *
     * Each step captures:
     *   ok      — did it succeed?
     *   ms      — wall-clock time (rounded)
     *   detail  — short success note
     *   error   — structured error (class, message, aws_code, status, trace)
     */
    protected function testR2Driver(): array
    {
        $testKey = '_storage-test/ping-' . now()->format('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.txt';
        $content = "storage-test @ " . now()->toIso8601String() . "\n";
        $ops     = [];

        // 1) Config sanity check (before we even touch the network)
        $ops[] = $this->wrapOp('config', function () {
            $missing = [];
            foreach (['bucket','key','secret','endpoint'] as $k) {
                if (empty(config("filesystems.disks.r2.{$k}"))) $missing[] = $k;
            }
            if ($missing) {
                throw new \RuntimeException('Missing config: ' . implode(', ', $missing));
            }
            return 'config present: bucket=' . config('filesystems.disks.r2.bucket');
        });

        // 2) LIST — cheapest read op; failures here usually mean bucket/cred issue
        $ops[] = $this->wrapOp('list', function () {
            $files = Storage::disk('r2')->files('', false);
            return 'list ok (' . count($files) . ' items at root)';
        });

        // 3) PUT — write a tiny object
        $ops[] = $this->wrapOp('put', function () use ($testKey, $content) {
            $ok = Storage::disk('r2')->put($testKey, $content);
            if (!$ok) {
                throw new \RuntimeException('put() returned false (disk throws=false swallows real error — check laravel.log)');
            }
            // Verify round-trip
            if (!Storage::disk('r2')->exists($testKey)) {
                throw new \RuntimeException('put() said OK but exists() returned false — likely silent AccessDenied');
            }
            return "wrote {$testKey} (" . strlen($content) . ' bytes)';
        });

        // 4) GET — read it back
        $ops[] = $this->wrapOp('get', function () use ($testKey, $content) {
            $body = Storage::disk('r2')->get($testKey);
            if ($body !== $content) {
                throw new \RuntimeException('GET returned mismatched content (' . strlen((string) $body) . ' vs ' . strlen($content) . ' bytes)');
            }
            return 'round-trip OK (' . strlen($body) . ' bytes matched)';
        });

        // 5) DELETE — clean up
        $ops[] = $this->wrapOp('delete', function () use ($testKey) {
            $ok = Storage::disk('r2')->delete($testKey);
            if (!$ok && Storage::disk('r2')->exists($testKey)) {
                throw new \RuntimeException('delete() failed and object still exists');
            }
            return 'deleted';
        });

        return $this->buildTestResult($ops);
    }

    /**
     * Same flow for S3.
     */
    protected function testS3Driver(): array
    {
        $testKey = '_storage-test/ping-' . now()->format('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.txt';
        $content = "storage-test @ " . now()->toIso8601String() . "\n";
        $ops     = [];

        $ops[] = $this->wrapOp('config', function () {
            $missing = [];
            foreach (['bucket','key','secret'] as $k) {
                if (empty(config("filesystems.disks.s3.{$k}"))) $missing[] = $k;
            }
            if ($missing) {
                throw new \RuntimeException('Missing config: ' . implode(', ', $missing));
            }
            return 'config present: bucket=' . config('filesystems.disks.s3.bucket') . ' · region=' . (config('filesystems.disks.s3.region') ?: '(none)');
        });

        $ops[] = $this->wrapOp('list', function () {
            $files = Storage::disk('s3')->files('', false);
            return 'list ok (' . count($files) . ' items at root)';
        });

        $ops[] = $this->wrapOp('put', function () use ($testKey, $content) {
            $ok = Storage::disk('s3')->put($testKey, $content);
            if (!$ok) {
                throw new \RuntimeException('put() returned false — check laravel.log');
            }
            if (!Storage::disk('s3')->exists($testKey)) {
                throw new \RuntimeException('put() said OK but exists() returned false — likely silent AccessDenied');
            }
            return "wrote {$testKey} (" . strlen($content) . ' bytes)';
        });

        $ops[] = $this->wrapOp('get', function () use ($testKey, $content) {
            $body = Storage::disk('s3')->get($testKey);
            if ($body !== $content) {
                throw new \RuntimeException('GET returned mismatched content');
            }
            return 'round-trip OK (' . strlen($body) . ' bytes)';
        });

        $ops[] = $this->wrapOp('delete', function () use ($testKey) {
            $ok = Storage::disk('s3')->delete($testKey);
            if (!$ok && Storage::disk('s3')->exists($testKey)) {
                throw new \RuntimeException('delete() failed and object still exists');
            }
            return 'deleted';
        });

        return $this->buildTestResult($ops);
    }

    /**
     * Drive test — validates OAuth + folder access without mutating state.
     */
    protected function testDriveDriver(): array
    {
        $ops = [];

        $ops[] = $this->wrapOp('config', function () {
            $missing = [];
            if (empty(AppSetting::get('drive_client_id', '')))     $missing[] = 'client_id';
            if (empty(AppSetting::get('drive_client_secret', ''))) $missing[] = 'client_secret';
            if (empty(AppSetting::get('drive_refresh_token', ''))) $missing[] = 'refresh_token';
            if (empty(AppSetting::get('drive_folder_id', '')))     $missing[] = 'folder_id';
            if ($missing) {
                throw new \RuntimeException('Missing setting: ' . implode(', ', $missing));
            }
            return 'config present · folder=' . AppSetting::get('drive_folder_id', '');
        });

        // Load GoogleDriveService — this triggers OAuth/refresh
        $ops[] = $this->wrapOp('oauth', function () {
            if (!class_exists(\App\Services\GoogleDriveService::class)) {
                throw new \RuntimeException('GoogleDriveService class not found');
            }
            $svc = app(\App\Services\GoogleDriveService::class);
            return 'service booted · token refreshed';
        });

        // Folder list — confirms actual Drive API reachability + permission
        $ops[] = $this->wrapOp('list', function () {
            $svc = app(\App\Services\GoogleDriveService::class);
            if (method_exists($svc, 'listFolderContents')) {
                $items = $svc->listFolderContents(AppSetting::get('drive_folder_id', ''), 3);
                $count = is_countable($items) ? count($items) : 0;
                return "folder list ok ({$count} sample items)";
            }
            if (method_exists($svc, 'testConnection')) {
                $ok = $svc->testConnection();
                return $ok ? 'testConnection() ok' : 'testConnection() failed';
            }
            return 'no list/test method available on service — boot check only';
        });

        return $this->buildTestResult($ops);
    }

    /**
     * Local disk test — very fast, mostly a smoke test.
     */
    protected function testPublicDriver(): array
    {
        $testKey = '_storage-test/ping-' . now()->format('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.txt';
        $content = "storage-test @ " . now()->toIso8601String() . "\n";
        $ops     = [];

        $ops[] = $this->wrapOp('config', function () {
            $root = config('filesystems.disks.public.root');
            if (empty($root)) {
                throw new \RuntimeException('public disk root is not set');
            }
            return 'root=' . $root;
        });

        $ops[] = $this->wrapOp('put', function () use ($testKey, $content) {
            $ok = Storage::disk('public')->put($testKey, $content);
            if (!$ok) throw new \RuntimeException('put() failed — check directory permissions');
            return "wrote {$testKey}";
        });

        $ops[] = $this->wrapOp('get', function () use ($testKey, $content) {
            $body = Storage::disk('public')->get($testKey);
            if ($body !== $content) throw new \RuntimeException('content mismatch');
            return 'round-trip ok';
        });

        $ops[] = $this->wrapOp('delete', function () use ($testKey) {
            $ok = Storage::disk('public')->delete($testKey);
            if (!$ok) throw new \RuntimeException('delete failed');
            return 'deleted';
        });

        return $this->buildTestResult($ops);
    }

    /**
     * Execute a single step with timing + rich error capture.
     *
     * Peeling the `previous` chain is important for AWS-backed drivers —
     * the outer Flysystem `UnableToWriteFile` typically wraps an
     * `AwsException` that carries the actual `AwsErrorCode` ("AccessDenied",
     * "NoSuchBucket", …) and HTTP status. Without unwrapping we lose the
     * one signal that tells the operator *why* the credentials are wrong.
     */
    protected function wrapOp(string $step, \Closure $fn): array
    {
        $start = microtime(true);
        try {
            $detail = (string) $fn();
            return [
                'step'   => $step,
                'ok'     => true,
                'ms'     => (int) round((microtime(true) - $start) * 1000),
                'detail' => $detail,
                'error'  => null,
            ];
        } catch (\Throwable $e) {
            return [
                'step'   => $step,
                'ok'     => false,
                'ms'     => (int) round((microtime(true) - $start) * 1000),
                'detail' => '',
                'error'  => $this->describeException($e),
            ];
        }
    }

    /**
     * Pretty-print an exception with the AWS-specific fields extracted and
     * the `previous` chain walked so we keep the *actual* cause, not just
     * the outer Flysystem wrapper.
     */
    protected function describeException(\Throwable $e): array
    {
        $chain = [];
        $cur = $e;
        $guard = 0;
        while ($cur && $guard++ < 6) {
            $item = [
                'class'   => get_class($cur),
                'message' => $cur->getMessage(),
                'file'    => basename($cur->getFile()) . ':' . $cur->getLine(),
            ];

            // AWS-specific extraction — these are the fields that actually
            // diagnose R2/S3 failures. `AwsErrorCode` is the gold nugget:
            // "AccessDenied", "NoSuchBucket", "SignatureDoesNotMatch", etc.
            if ($cur instanceof \Aws\Exception\AwsException) {
                $item['aws_code']    = $cur->getAwsErrorCode() ?? '';
                $item['aws_status']  = $cur->getStatusCode() ?? 0;
                $item['aws_type']    = $cur->getAwsErrorType() ?? '';
                $item['aws_request'] = $cur->getAwsRequestId() ?? '';
            }

            $chain[] = $item;
            $cur = $cur->getPrevious();
        }

        return [
            'class'   => get_class($e),
            'message' => $e->getMessage(),
            'chain'   => $chain,
            'hint'    => $this->hintForException($e),
        ];
    }

    /**
     * Translate common AWS/R2 errors into a plain-English action hint.
     * The SDK's own message is often cryptic ("Access Denied") — we know
     * the usual root cause for *this* app is an API-token permission scope.
     */
    protected function hintForException(\Throwable $e): string
    {
        // Walk chain to find the real AWS exception if wrapped
        $awsCode = '';
        $cur = $e;
        $guard = 0;
        while ($cur && $guard++ < 6) {
            if ($cur instanceof \Aws\Exception\AwsException) {
                $awsCode = (string) ($cur->getAwsErrorCode() ?? '');
                if ($awsCode !== '') break;
            }
            $cur = $cur->getPrevious();
        }

        $msg = strtolower($e->getMessage());

        return match (true) {
            $awsCode === 'AccessDenied' || str_contains($msg, 'access denied')
                => 'R2/S3 API token ขาดสิทธิ์เขียน — แก้ที่ Cloudflare Dashboard → R2 → Manage API Tokens → ตั้งเป็น "Object Read & Write" และผูกกับ bucket นี้',
            $awsCode === 'NoSuchBucket' || str_contains($msg, 'nosuchbucket') || str_contains($msg, 'no such bucket')
                => 'Bucket name ผิด หรือ token scope คนละ bucket — ตรวจ bucket ใน Storage settings ให้ตรงกับ Cloudflare',
            $awsCode === 'SignatureDoesNotMatch' || str_contains($msg, 'signaturedoesnotmatch')
                => 'Access Key / Secret Key ไม่ถูกต้อง — สร้าง API token ใหม่แล้วอัปเดตในหน้า Storage',
            $awsCode === 'InvalidAccessKeyId'
                => 'Access Key ไม่มีอยู่จริงในระบบ R2/S3 — อาจถูกลบไปแล้ว, สร้างใหม่',
            str_contains($msg, 'could not resolve host') || str_contains($msg, 'curl error 6')
                => 'DNS resolve ไม่ได้ — ตรวจ endpoint URL ใน R2 settings (ต้องขึ้นต้น https:// และตรงกับ account id)',
            str_contains($msg, 'timed out') || str_contains($msg, 'timeout')
                => 'Network timeout — เช็ค firewall/proxy หรือทดสอบ endpoint ด้วย curl จากเครื่อง server',
            str_contains($msg, 'invalid argument') && str_contains($msg, 'endpoint')
                => 'Endpoint format ผิด — ต้องเป็น https://<account-id>.r2.cloudflarestorage.com สำหรับ R2',
            str_contains($msg, 'invalid_grant')
                => 'Google Drive refresh token หมดอายุหรือถูก revoke — ทำ OAuth ใหม่',
            default => '',
        };
    }

    /**
     * Aggregate per-step results into the final response envelope.
     */
    protected function buildTestResult(array $ops): array
    {
        $allOk = count($ops) > 0 && !collect($ops)->contains(fn ($o) => !$o['ok']);
        $totalMs = array_sum(array_column($ops, 'ms'));

        return [
            'ok'         => $allOk,
            'total_ms'   => $totalMs,
            'operations' => $ops,
            'timestamp'  => now()->toDateTimeString(),
        ];
    }

    /**
     * Backup management page — lists on-disk SQL backups.
     */
    public function backup()
    {
        // Use raw filesystem read instead of Storage::disk('local')->files()
        // because:
        //   - Laravel 12 changed the `local` disk root to `storage/app/private/`
        //   - But backup binaries (pg_dump / mysqldump) write directly to
        //     `storage/app/backups/` via storage_path() (no Storage facade)
        //   - Reading via Storage::disk('local') would look in the wrong dir
        // Single source of truth: storage_path('app/backups/').
        $backupDir = storage_path('app/backups');
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0755, true);
        }

        $backupFiles = [];
        foreach ((array) glob($backupDir . '/*') as $fullPath) {
            if (!is_file($fullPath)) continue;
            $backupFiles[] = [
                'name'     => basename($fullPath),
                'path'     => 'backups/' . basename($fullPath),
                'size'     => filesize($fullPath) ?: 0,
                'modified' => \Carbon\Carbon::createFromTimestamp(filemtime($fullPath) ?: time()),
            ];
        }

        // Sort newest first
        usort($backupFiles, fn($a, $b) => $b['modified']->timestamp - $a['modified']->timestamp);

        return view('admin.settings.backup', compact('backupFiles'));
    }

    /**
     * Trigger a database snapshot into storage/app/backups/.
     *
     * Postgres path:
     *   1. If pg_dump binary is available → use it (full schema+data dump).
     *   2. Otherwise → fall back to PHP/PDO data-only dumper.
     *
     * For production-grade backups, prefer running `pg_dump` from a cron
     * job on the database host — that way you get WAL-consistent dumps
     * without going through PHP's process layer.
     */
    public function backupDatabase()
    {
        Storage::disk('local')->makeDirectory('backups');

        $host     = config('database.connections.pgsql.host', '127.0.0.1');
        $port     = (string) config('database.connections.pgsql.port', '5432');
        $database = config('database.connections.pgsql.database', 'jabphap');
        $username = config('database.connections.pgsql.username', 'postgres');
        $password = (string) config('database.connections.pgsql.password', '');

        $timestamp  = now()->format('Y-m-d_H-i-s');
        $filename   = "backup_{$database}_{$timestamp}.sql";
        $backupPath = storage_path("app/backups/{$filename}");

        // ── Resolve pg_dump binary ────────────────────────────────────────
        $binary = $this->resolvePgDumpBinary();

        if ($binary) {
            $result = $this->runPgDump($binary, $host, $port, $database, $username, $password, $backupPath);

            if ($result['ok']) {
                return back()->with(
                    'success',
                    "✓ Database backup created: {$filename} (" . $this->formatBytes($result['size']) . ') [pg_dump]'
                );
            }

            Log::error('Database backup failed', [
                'exit_code' => $result['exit_code'],
                'stderr'    => $result['stderr'],
                'binary'    => $binary,
            ]);

            // pg_dump failed → fall through to PHP-native dumper
            Log::warning('pg_dump failed, falling back to PHP dumper', [
                'stderr' => $result['stderr'],
            ]);
        }

        // ── PHP-native fallback (uses existing PDO connection) ────────────
        try {
            $size = $this->dumpDatabaseViaPdo($backupPath);

            if ($size === 0) {
                @unlink($backupPath);
                return back()->with('error', 'Database backup failed: PHP fallback ก็ export ไฟล์ว่าง');
            }

            $note = $binary
                ? ' [PHP fallback — pg_dump รันไม่สำเร็จ ดู logs]'
                : ' [PHP fallback — ไม่พบไฟล์ pg_dump · data-only dump · restore ผ่าน psql หลัง migrate]';

            return back()->with(
                'success',
                "✓ Database backup created: {$filename} (" . $this->formatBytes($size) . ')' . $note
            );
        } catch (\Throwable $e) {
            if (file_exists($backupPath)) {
                @unlink($backupPath);
            }

            Log::error('Database backup PHP fallback failed', [
                'message' => $e->getMessage(),
            ]);

            return back()->with(
                'error',
                'Database backup failed (PHP fallback): ' . $e->getMessage()
            );
        }
    }

    /**
     * Run mysqldump (with Windows Winsock workarounds).
     *
     * Returns: ['ok' => bool, 'size' => int, 'exit_code' => int, 'stderr' => string]
     */
    protected function runMysqldump(
        string $binary,
        string $host,
        string $port,
        string $database,
        string $username,
        string $password,
        string $backupPath
    ): array {
        $args = [
            $binary,
            '--protocol=tcp',              // force TCP explicitly (avoid auto-detect weirdness on Windows)
            '--host=' . $host,
            '--port=' . $port,
            '--user=' . $username,
            '--single-transaction',
            '--routines',
            '--triggers',
            '--skip-lock-tables',
            '--default-character-set=utf8mb4',
            '--no-tablespaces',
            $database,
        ];

        $fh = @fopen($backupPath, 'wb');
        if (!$fh) {
            return ['ok' => false, 'size' => 0, 'exit_code' => -1, 'stderr' => "ไม่สามารถสร้างไฟล์ที่ {$backupPath}"];
        }

        try {
            $process = new Process($args);
            $process->setTimeout(600);

            // Minimal env to avoid Apache child processes inheriting LSPs that break Winsock.
            // On Windows, SystemRoot is REQUIRED for DLL loading; PATH needed for linked libs.
            $env = ['MYSQL_PWD' => $password];
            if (stripos(PHP_OS_FAMILY, 'Windows') === 0) {
                $env['SystemRoot']   = getenv('SystemRoot') ?: 'C:\\Windows';
                $env['PATH']         = getenv('PATH')       ?: '';
                $env['TEMP']         = getenv('TEMP')       ?: sys_get_temp_dir();
                $env['USERPROFILE']  = getenv('USERPROFILE') ?: '';
                $env['COMPUTERNAME'] = getenv('COMPUTERNAME') ?: '';
            }
            $process->setEnv($env);

            $stderr = '';
            $process->run(function ($type, $buffer) use ($fh, &$stderr) {
                if ($type === Process::OUT) {
                    fwrite($fh, $buffer);
                } else {
                    $stderr .= $buffer;
                }
            });
            fclose($fh);

            if (!$process->isSuccessful()) {
                if (file_exists($backupPath)) {
                    @unlink($backupPath);
                }
                return [
                    'ok'        => false,
                    'size'      => 0,
                    'exit_code' => $process->getExitCode() ?? -1,
                    'stderr'    => trim($stderr) ?: 'no error output',
                ];
            }

            $size = file_exists($backupPath) ? filesize($backupPath) : 0;
            if ($size === 0) {
                @unlink($backupPath);
                return ['ok' => false, 'size' => 0, 'exit_code' => 0, 'stderr' => 'empty output'];
            }

            return ['ok' => true, 'size' => $size, 'exit_code' => 0, 'stderr' => ''];
        } catch (\Throwable $e) {
            if (is_resource($fh)) {
                @fclose($fh);
            }
            if (file_exists($backupPath)) {
                @unlink($backupPath);
            }
            return ['ok' => false, 'size' => 0, 'exit_code' => -1, 'stderr' => $e->getMessage()];
        }
    }

    /**
     * Detect Winsock/socket init errors that hint at Windows service context issues.
     * Kept for legacy callers — pg_dump doesn't typically hit this on Windows.
     */
    protected function isSocketInitError(string $stderr): bool
    {
        $stderr = strtolower($stderr);
        return str_contains($stderr, '10106')
            || str_contains($stderr, "can't create tcp/ip socket")
            || str_contains($stderr, 'cant create tcp/ip socket')
            || str_contains($stderr, 'wsastartup')
            || str_contains($stderr, 'winsock');
    }

    /**
     * Run pg_dump with platform-aware env handling.
     * Postgres takes the password via the PGPASSWORD env var (same idea
     * as MYSQL_PWD on the MySQL side — keeps it off the command line).
     *
     * Returns: ['ok' => bool, 'size' => int, 'exit_code' => int, 'stderr' => string]
     */
    protected function runPgDump(
        string $binary,
        string $host,
        string $port,
        string $database,
        string $username,
        string $password,
        string $backupPath
    ): array {
        $args = [
            $binary,
            '--host=' . $host,
            '--port=' . $port,
            '--username=' . $username,
            '--no-owner',
            '--no-privileges',
            '--clean',
            '--if-exists',
            '--encoding=UTF8',
            $database,
        ];

        $fh = @fopen($backupPath, 'wb');
        if (!$fh) {
            return ['ok' => false, 'size' => 0, 'exit_code' => -1, 'stderr' => "ไม่สามารถสร้างไฟล์ที่ {$backupPath}"];
        }

        try {
            $process = new Process($args);
            $process->setTimeout(600);

            $env = ['PGPASSWORD' => $password];
            if (stripos(PHP_OS_FAMILY, 'Windows') === 0) {
                $env['SystemRoot']   = getenv('SystemRoot') ?: 'C:\\Windows';
                $env['PATH']         = getenv('PATH')       ?: '';
                $env['TEMP']         = getenv('TEMP')       ?: sys_get_temp_dir();
                $env['USERPROFILE']  = getenv('USERPROFILE') ?: '';
                $env['COMPUTERNAME'] = getenv('COMPUTERNAME') ?: '';
            }
            $process->setEnv($env);

            $stderr = '';
            $process->run(function ($type, $buffer) use ($fh, &$stderr) {
                if ($type === Process::OUT) {
                    fwrite($fh, $buffer);
                } else {
                    $stderr .= $buffer;
                }
            });
            fclose($fh);

            if (!$process->isSuccessful()) {
                if (file_exists($backupPath)) {
                    @unlink($backupPath);
                }
                return [
                    'ok'        => false,
                    'size'      => 0,
                    'exit_code' => $process->getExitCode() ?? -1,
                    'stderr'    => trim($stderr) ?: 'no error output',
                ];
            }

            $size = file_exists($backupPath) ? filesize($backupPath) : 0;
            if ($size === 0) {
                @unlink($backupPath);
                return ['ok' => false, 'size' => 0, 'exit_code' => 0, 'stderr' => 'empty output'];
            }

            return ['ok' => true, 'size' => $size, 'exit_code' => 0, 'stderr' => ''];
        } catch (\Throwable $e) {
            if (is_resource($fh)) {
                @fclose($fh);
            }
            if (file_exists($backupPath)) {
                @unlink($backupPath);
            }
            return ['ok' => false, 'size' => 0, 'exit_code' => -1, 'stderr' => $e->getMessage()];
        }
    }

    /**
     * Locate pg_dump binary on Windows / Linux / macOS.
     * Priority: AppSetting → env → PATH → common install dirs.
     */
    protected function resolvePgDumpBinary(): ?string
    {
        $override = AppSetting::get('pg_dump_path', '');
        if ($override && is_file($override) && is_executable($override)) {
            return $override;
        }
        $envPath = env('PG_DUMP_PATH');
        if ($envPath && is_file($envPath) && is_executable($envPath)) {
            return $envPath;
        }

        $finder = new ExecutableFinder();
        $found  = $finder->find('pg_dump');
        if ($found) {
            return $found;
        }

        foreach ($this->pgDumpCommonPaths() as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }
        return null;
    }

    /**
     * Likely install paths of pg_dump by OS.
     */
    protected function pgDumpCommonPaths(): array
    {
        $isWindows = stripos(PHP_OS_FAMILY, 'Windows') === 0;

        if ($isWindows) {
            $paths = [];
            // Standard Postgres installer (matches major versions 12-17)
            foreach (range(17, 12) as $major) {
                $paths[] = "C:\\Program Files\\PostgreSQL\\{$major}\\bin\\pg_dump.exe";
                $paths[] = "C:\\Program Files (x86)\\PostgreSQL\\{$major}\\bin\\pg_dump.exe";
            }
            return $paths;
        }

        // Unix-style — Homebrew, apt, common Linux paths
        return [
            '/usr/bin/pg_dump',
            '/usr/local/bin/pg_dump',
            '/opt/homebrew/bin/pg_dump',
            '/opt/homebrew/opt/postgresql@16/bin/pg_dump',
            '/opt/homebrew/opt/postgresql@15/bin/pg_dump',
            '/usr/lib/postgresql/16/bin/pg_dump',
            '/usr/lib/postgresql/15/bin/pg_dump',
            '/usr/lib/postgresql/14/bin/pg_dump',
        ];
    }

    /**
     * PHP-native database dumper using the app's existing PDO connection.
     * Slower than mysqldump but doesn't require spawning a subprocess or
     * initializing Winsock — works when mysqldump hits 10106.
     *
     * Produces an SQL file with INSERT statements compatible with
     * `psql -f file.sql` for restore.
     *
     * On Postgres we cannot easily reconstruct full DDL via SQL queries
     * (no equivalent of MySQL's `SHOW CREATE TABLE`). Instead this method
     * dumps **data only**; restore expects the schema to already exist
     * (e.g. via `php artisan migrate`). For full schema+data backup,
     * admin should run `pg_dump` from CLI.
     *
     * @return int Bytes written
     */
    protected function dumpDatabaseViaPdo(string $backupPath): int
    {
        $pdo = DB::connection()->getPdo();
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $fh = @fopen($backupPath, 'wb');
        if (!$fh) {
            throw new \RuntimeException("ไม่สามารถเขียนไฟล์ที่ {$backupPath}");
        }

        try {
            $db = DB::connection()->getDatabaseName();

            fwrite($fh, "-- Postgres data-only backup of \"{$db}\" at " . now()->toDateTimeString() . "\n");
            fwrite($fh, "-- Generated by PDO fallback (pg_dump unavailable).\n");
            fwrite($fh, "-- Restore: psql -d {$db} -f <this-file>  (after running migrations)\n");
            fwrite($fh, "-- Tip: for full schema + data, prefer `pg_dump --no-owner --clean`.\n\n");
            fwrite($fh, "SET session_replication_role = replica;  -- defer FK constraint checks\n\n");

            // Enumerate base tables in the public schema (skip views, sequences,
            // partitions, etc.). information_schema is the portable way to do this.
            $tables = [];
            $stmt = $pdo->query("
                SELECT table_name
                  FROM information_schema.tables
                 WHERE table_schema = 'public'
                   AND table_type   = 'BASE TABLE'
                 ORDER BY table_name
            ");
            foreach ($stmt as $row) {
                $tables[] = $row['table_name'];
            }

            foreach ($tables as $table) {
                $quoted = '"' . str_replace('"', '""', $table) . '"';

                fwrite($fh, "-- ─── Table: {$table} ───\n");
                fwrite($fh, "TRUNCATE TABLE {$quoted} RESTART IDENTITY CASCADE;\n");

                // Stream rows in chunks to avoid loading entire table into memory
                $rowStmt = $pdo->query("SELECT * FROM {$quoted}");
                $columnCount = $rowStmt->columnCount();
                $columns = [];
                for ($i = 0; $i < $columnCount; $i++) {
                    $meta = $rowStmt->getColumnMeta($i);
                    $columns[] = '"' . str_replace('"', '""', $meta['name']) . '"';
                }
                $colList = implode(',', $columns);

                $batchSize = 100;
                $batch = [];
                $rowCount = 0;

                while ($row = $rowStmt->fetch(\PDO::FETCH_NUM)) {
                    $values = [];
                    foreach ($row as $val) {
                        if ($val === null) {
                            $values[] = 'NULL';
                        } elseif (is_int($val) || is_float($val)) {
                            $values[] = $val;
                        } elseif (is_bool($val)) {
                            $values[] = $val ? 'TRUE' : 'FALSE';
                        } else {
                            $values[] = $pdo->quote($val);
                        }
                    }
                    $batch[] = '(' . implode(',', $values) . ')';
                    $rowCount++;

                    if (count($batch) >= $batchSize) {
                        fwrite($fh, "INSERT INTO {$quoted} ({$colList}) VALUES\n" . implode(",\n", $batch) . ";\n");
                        $batch = [];
                    }
                }

                if (!empty($batch)) {
                    fwrite($fh, "INSERT INTO {$quoted} ({$colList}) VALUES\n" . implode(",\n", $batch) . ";\n");
                }

                fwrite($fh, "-- {$rowCount} rows\n\n");
            }

            fwrite($fh, "SET session_replication_role = DEFAULT;  -- re-enable FK checks\n");
            fwrite($fh, "-- Backup complete — " . count($tables) . " table(s)\n");

            fclose($fh);
            return filesize($backupPath);
        } catch (\Throwable $e) {
            if (is_resource($fh)) {
                @fclose($fh);
            }
            throw $e;
        }
    }

    /**
     * Find mysqldump on any OS.
     * Priority:
     *   1. AppSetting('mysqldump_path') — admin override
     *   2. env MYSQLDUMP_PATH
     *   3. Symfony ExecutableFinder (searches PATH)
     *   4. Common install locations per OS (XAMPP/MAMP/WAMP/Homebrew/apt)
     */
    protected function resolveMysqldumpBinary(): ?string
    {
        // 1. Admin override via AppSetting
        $override = AppSetting::get('mysqldump_path', '');
        if ($override && is_file($override) && is_executable($override)) {
            return $override;
        }

        // 2. .env override
        $envPath = env('MYSQLDUMP_PATH');
        if ($envPath && is_file($envPath) && is_executable($envPath)) {
            return $envPath;
        }

        // 3. System PATH (ExecutableFinder handles .exe suffix on Windows)
        $finder = new ExecutableFinder();
        $found  = $finder->find('mysqldump');
        if ($found) {
            return $found;
        }

        // 4. Common install locations
        $candidates = $this->mysqldumpCommonPaths();
        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Likely install paths of mysqldump by OS.
     */
    protected function mysqldumpCommonPaths(): array
    {
        $isWindows = stripos(PHP_OS_FAMILY, 'Windows') === 0;

        if ($isWindows) {
            $paths = [
                'C:\\xampp\\mysql\\bin\\mysqldump.exe',
                'C:\\wamp64\\bin\\mysql\\mysql8.0.31\\bin\\mysqldump.exe',
                'C:\\wamp\\bin\\mysql\\mysql5.7.36\\bin\\mysqldump.exe',
                'C:\\laragon\\bin\\mysql\\mysql-8.0.30-winx64\\bin\\mysqldump.exe',
                'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
                'C:\\Program Files\\MySQL\\MySQL Server 5.7\\bin\\mysqldump.exe',
                'C:\\Program Files\\MariaDB 10.11\\bin\\mysqldump.exe',
                'C:\\Program Files\\MariaDB 10.6\\bin\\mysqldump.exe',
            ];
            // Glob-style discovery for versioned WAMP/Laragon folders
            foreach (['C:/wamp64/bin/mysql', 'C:/wamp/bin/mysql', 'C:/laragon/bin/mysql'] as $baseDir) {
                if (is_dir($baseDir)) {
                    foreach (glob($baseDir . '/*/bin/mysqldump.exe') ?: [] as $candidate) {
                        $paths[] = $candidate;
                    }
                }
            }
            return $paths;
        }

        // Linux / macOS
        return [
            '/usr/bin/mysqldump',
            '/usr/local/bin/mysqldump',
            '/usr/local/mysql/bin/mysqldump',
            '/opt/homebrew/bin/mysqldump',
            '/Applications/MAMP/Library/bin/mysqldump',
            '/Applications/XAMPP/xamppfiles/bin/mysqldump',
            '/opt/lampp/bin/mysqldump',
        ];
    }

    /**
     * User-friendly "couldn't find mysqldump" message with actionable tips.
     */
    protected function mysqldumpNotFoundMessage(): string
    {
        $isWindows = stripos(PHP_OS_FAMILY, 'Windows') === 0;
        $hint = $isWindows
            ? 'XAMPP: C:\\xampp\\mysql\\bin\\mysqldump.exe'
            : 'Linux: /usr/bin/mysqldump (apt install mysql-client)';

        return "ไม่พบไฟล์ mysqldump ในเครื่องนี้ — กรุณาติดตั้ง MySQL client หรือตั้งค่า path ใน AppSetting 'mysqldump_path' / env 'MYSQLDUMP_PATH'. ตัวอย่าง: {$hint}";
    }

    /**
     * Human-readable bytes (1234 → "1.2 KB").
     */
    protected function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }
        if ($bytes < 1024 * 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 1) . ' MB';
        }
        return number_format($bytes / (1024 * 1024 * 1024), 2) . ' GB';
    }

    /**
     * POST /admin/settings/backup/files
     * Create a ZIP archive of the project source (code + storage/app) excluding vendor/, node_modules/, logs, etc.
     */
    public function backupFiles()
    {
        @set_time_limit(900);
        @ini_set('memory_limit', '512M');

        Storage::disk('local')->makeDirectory('backups');

        $timestamp  = now()->format('Y-m-d_H-i-s');
        $filename   = "backup_files_{$timestamp}.zip";
        $backupPath = storage_path("app/backups/{$filename}");

        try {
            $stats = $this->createProjectZip($backupPath);

            return back()->with(
                'success',
                "✓ Files backup created: {$filename} (" . $this->formatBytes($stats['size']) . ', '
                . number_format($stats['files']) . ' files)'
            );
        } catch (\Throwable $e) {
            if (file_exists($backupPath)) {
                @unlink($backupPath);
            }
            Log::error('Files backup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->with('error', 'Files backup failed: ' . $e->getMessage());
        }
    }

    /**
     * POST /admin/settings/backup/full
     * Database dump + project source combined in one ZIP (database.sql at the root).
     */
    public function backupFull()
    {
        @set_time_limit(1800);
        @ini_set('memory_limit', '512M');

        Storage::disk('local')->makeDirectory('backups');

        // Use the active connection's database name (driver-aware).
        $cfgKey     = \DB::connection()->getDriverName() === 'pgsql' ? 'pgsql' : 'mysql';
        $database   = config("database.connections.{$cfgKey}.database", 'jabphap');
        $timestamp  = now()->format('Y-m-d_H-i-s');
        $filename   = "backup_full_{$timestamp}.zip";
        $backupPath = storage_path("app/backups/{$filename}");
        $tmpSqlPath = storage_path("app/backups/.tmp_db_{$timestamp}.sql");

        try {
            // 1. Dump database to a temp .sql
            $dbBytes = $this->dumpDatabaseOrFail($tmpSqlPath);

            // 2. Build ZIP with database.sql at the root + full project files
            $stats = $this->createProjectZip($backupPath, [
                'additional_files' => [
                    'database.sql' => $tmpSqlPath,
                ],
            ]);

            @unlink($tmpSqlPath);

            return back()->with(
                'success',
                "✓ Full backup created: {$filename} (" . $this->formatBytes($stats['size']) . ', '
                . number_format($stats['files']) . ' files + DB ' . $this->formatBytes($dbBytes) . ')'
            );
        } catch (\Throwable $e) {
            if (file_exists($backupPath)) {
                @unlink($backupPath);
            }
            if (file_exists($tmpSqlPath)) {
                @unlink($tmpSqlPath);
            }
            Log::error('Full backup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->with('error', 'Full backup failed: ' . $e->getMessage());
        }
    }

    /**
     * GET /admin/settings/backup/download/{filename}
     * Stream a backup file to the browser. Path-traversal protected.
     */
    public function backupDownload(string $filename)
    {
        $path = $this->resolveBackupFileOr404($filename);

        // Force download — use streamed response for big files
        return response()->download($path, $filename, [
            'Content-Type'        => $this->guessBackupMime($filename),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * DELETE /admin/settings/backup/{filename}
     * Remove a backup file (path-traversal protected).
     */
    public function backupDelete(string $filename)
    {
        try {
            $path = $this->resolveBackupFileOr404($filename);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return back()->with('error', 'ไม่พบไฟล์สำรอง');
        }

        if (@unlink($path)) {
            return back()->with('success', "ลบไฟล์ {$filename} เรียบร้อย");
        }

        return back()->with('error', "ไม่สามารถลบไฟล์ {$filename} ได้");
    }

    /**
     * Validate that $filename resolves to a real file inside storage/app/backups/.
     * Rejects any path traversal attempts and symlink escapes.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException (404/400/403)
     */
    protected function resolveBackupFileOr404(string $filename): string
    {
        // Reject obviously malicious names before we touch the filesystem
        if ($filename === '' || $filename === '.' || $filename === '..') {
            abort(400, 'Invalid filename');
        }
        if (str_contains($filename, '/') || str_contains($filename, '\\')
            || str_contains($filename, '..') || str_contains($filename, "\0")) {
            abort(400, 'Invalid filename');
        }

        $backupsDir = storage_path('app/backups');
        $target     = $backupsDir . DIRECTORY_SEPARATOR . $filename;

        $realTarget = realpath($target);
        $realBase   = realpath($backupsDir);

        if (!$realTarget || !$realBase || !is_file($realTarget)) {
            abort(404, 'Backup not found');
        }
        // Ensure real resolved path still lives inside the backups directory
        if (strncmp($realTarget, $realBase . DIRECTORY_SEPARATOR, strlen($realBase) + 1) !== 0) {
            abort(403, 'Path escapes backups directory');
        }

        return $realTarget;
    }

    /**
     * Rough MIME guess for backup artifacts.
     */
    protected function guessBackupMime(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return match ($ext) {
            'zip'           => 'application/zip',
            'sql'           => 'application/sql',
            'gz', 'tgz'     => 'application/gzip',
            default         => 'application/octet-stream',
        };
    }

    /**
     * Dump the database to $path using mysqldump (preferred) or PHP/PDO fallback.
     * Shared helper for backupFull() — throws on failure.
     *
     * @return int bytes written
     * @throws \RuntimeException
     */
    protected function dumpDatabaseOrFail(string $path): int
    {
        // Driver-aware: pgsql → pg_dump, mysql/mariadb → mysqldump.
        // Falls back to PDO dumper (driver-aware itself) if the binary is
        // missing or fails for a transient reason.
        $driver   = \DB::connection()->getDriverName();
        $cfgKey   = $driver === 'pgsql' ? 'pgsql' : 'mysql';
        $host     = config("database.connections.{$cfgKey}.host", '127.0.0.1');
        $port     = (string) config("database.connections.{$cfgKey}.port", $driver === 'pgsql' ? '5432' : '3306');
        $database = config("database.connections.{$cfgKey}.database", 'jabphap');
        $username = config("database.connections.{$cfgKey}.username", $driver === 'pgsql' ? 'postgres' : 'root');
        $password = (string) config("database.connections.{$cfgKey}.password", '');

        if ($driver === 'pgsql') {
            $binary = $this->resolvePgDumpBinary();
            if ($binary) {
                $result = $this->runPgDump($binary, $host, $port, $database, $username, $password, $path);
                if ($result['ok']) {
                    return $result['size'];
                }
                Log::warning('pg_dump failed during full backup, using PHP fallback', [
                    'stderr' => $result['stderr'],
                ]);
            }
        } else {
            $binary = $this->resolveMysqldumpBinary();
            if ($binary) {
                $result = $this->runMysqldump($binary, $host, $port, $database, $username, $password, $path);
                if ($result['ok']) {
                    return $result['size'];
                }
                // Only fall back for socket init issues — surface other errors
                if (!$this->isSocketInitError($result['stderr'])) {
                    throw new \RuntimeException(
                        "mysqldump exit {$result['exit_code']}: " . trim($result['stderr'])
                    );
                }
                Log::warning('mysqldump hit socket issue during full backup, using PHP fallback', [
                    'stderr' => $result['stderr'],
                ]);
            }
        }

        $size = $this->dumpDatabaseViaPdo($path);
        if ($size === 0) {
            throw new \RuntimeException('Database dump was empty');
        }
        return $size;
    }

    /**
     * Create a ZIP archive of the project root, excluding heavy/regenerable/recursive dirs.
     *
     * $options['additional_files']   — ['name-in-zip' => '/absolute/path']  (optional)
     *
     * @return array{size:int,files:int}
     */
    protected function createProjectZip(string $zipPath, array $options = []): array
    {
        $baseDir = rtrim(str_replace('\\', '/', base_path()), '/');

        // Excluded paths are relative to base_path() (forward-slash, no trailing slash)
        $excludes = [
            'vendor',
            'node_modules',
            '.git',
            '.github',
            '.idea',
            '.vscode',
            'storage/logs',
            'storage/framework/cache',
            'storage/framework/views',
            'storage/framework/sessions',
            'storage/framework/testing',
            'storage/app/backups',
            'storage/debugbar',
            'bootstrap/cache',
            'public/hot',
            'public/storage',       // symlink — recreate via `php artisan storage:link`
            'public/build/hot',
            'tests/_output',
        ];

        // Excluded file names (anywhere in the tree)
        $excludeFilePatterns = [
            '.DS_Store',
            'Thumbs.db',
            '.phpunit.result.cache',
            '.phpunit.cache',
        ];

        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('PHP ZipArchive extension is not enabled. Enable zip extension in php.ini.');
        }

        $zip = new \ZipArchive();
        $opened = $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        if ($opened !== true) {
            throw new \RuntimeException("Cannot create ZIP at {$zipPath} (ZipArchive error code {$opened})");
        }

        $fileCount = 0;

        try {
            $directoryIter = new \RecursiveDirectoryIterator(
                $baseDir,
                \RecursiveDirectoryIterator::SKIP_DOTS
            );

            $filterIter = new \RecursiveCallbackFilterIterator(
                $directoryIter,
                function ($current) use ($baseDir, $excludes, $excludeFilePatterns) {
                    $path = str_replace('\\', '/', $current->getPathname());
                    $rel  = ltrim(substr($path, strlen($baseDir)), '/');

                    if ($rel === '') {
                        return true; // base dir itself
                    }

                    // Directory-prefix excludes (stops recursion into them)
                    foreach ($excludes as $ex) {
                        if ($rel === $ex || str_starts_with($rel, $ex . '/')) {
                            return false;
                        }
                    }

                    // File-name excludes
                    if (!$current->isDir()) {
                        $name = $current->getFilename();
                        foreach ($excludeFilePatterns as $badName) {
                            if ($name === $badName) {
                                return false;
                            }
                        }
                    }

                    return true;
                }
            );

            $iter = new \RecursiveIteratorIterator(
                $filterIter,
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iter as $file) {
                /** @var \SplFileInfo $file */
                if ($file->isDir()) {
                    continue;
                }
                // Skip symlinks to avoid cycles / cross-filesystem surprises
                if ($file->isLink()) {
                    continue;
                }
                if (!$file->isReadable()) {
                    continue;
                }

                $absolute = str_replace('\\', '/', $file->getPathname());
                $rel      = ltrim(substr($absolute, strlen($baseDir)), '/');

                if ($rel === '' || $rel === basename($zipPath)) {
                    continue;
                }

                // Extra guard: never include the backup file we're currently writing
                if (str_starts_with($rel, 'storage/app/backups/')) {
                    continue;
                }

                if ($zip->addFile($absolute, $rel)) {
                    $fileCount++;
                }
            }

            // Additional files (e.g. database.sql for full backup)
            foreach ($options['additional_files'] ?? [] as $inZipName => $srcPath) {
                if (is_file($srcPath) && $zip->addFile($srcPath, $inZipName)) {
                    $fileCount++;
                }
            }

            if (!$zip->close()) {
                throw new \RuntimeException('ZipArchive::close() failed — disk full or permissions?');
            }
        } catch (\Throwable $e) {
            // Best-effort close so the on-disk file isn't left half-open
            if ($zip->status === \ZipArchive::ER_OK) {
                @$zip->close();
            }
            if (file_exists($zipPath)) {
                @unlink($zipPath);
            }
            throw $e;
        }

        return [
            'size'  => file_exists($zipPath) ? filesize($zipPath) : 0,
            'files' => $fileCount,
        ];
    }
}
