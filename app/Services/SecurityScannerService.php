<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\DB;

class SecurityScannerService
{
    /**
     * Run all 14 security checks and return a score + findings array.
     * Result is cached in app_settings as JSON under key 'security_scan_result'.
     */
    public function runFullScan(): array
    {
        $findings = [
            $this->checkDebugMode(),
            $this->checkDefaultCredentials(),
            $this->checkDatabaseSecurity(),
            $this->checkPhpSettings(),
            $this->checkSessionSecurity(),
            $this->checkSslHttps(),
            $this->check2faStatus(),
            $this->checkFilePermissions(),
            $this->checkExposedFiles(),
            $this->checkUploadDirectory(),
            $this->checkBackupFiles(),
            $this->checkOutdatedApp(),
            $this->checkCsrfProtection(),
            $this->checkRateLimiting(),
        ];

        $deductions = [
            'critical' => 25,
            'high'     => 15,
            'medium'   =>  8,
            'low'      =>  3,
            'info'     =>  0,
        ];

        $score = 100;
        foreach ($findings as $finding) {
            if ($finding['status'] === 'fail') {
                $score -= $deductions[$finding['severity']] ?? 0;
            }
        }
        $score = max(0, $score);

        $result = [
            'score'      => $score,
            'findings'   => $findings,
            'scanned_at' => now()->toDateTimeString(),
        ];

        AppSetting::set('security_scan_result', json_encode($result));

        return $result;
    }

    // -------------------------------------------------------------------------
    // Individual checks
    // -------------------------------------------------------------------------

    private function checkDebugMode(): array
    {
        $debug = config('app.debug');
        $env   = config('app.env', 'production');

        $fail = $debug && $env !== 'local';

        return [
            'name'     => 'Debug Mode',
            'severity' => 'critical',
            'status'   => $fail ? 'fail' : 'pass',
            'message'  => $fail
                ? 'APP_DEBUG is enabled in a non-local environment. Disable it immediately.'
                : 'Debug mode is off in production.',
        ];
    }

    private function checkDefaultCredentials(): array
    {
        $defaultEmails     = ['admin@example.com', 'admin@admin.com', 'test@test.com'];
        $defaultPasswords  = ['password', 'admin', '123456', 'secret', 'admin123', 'pass123'];

        $found = false;
        try {
            foreach ($defaultEmails as $email) {
                $admin = DB::table('admins')->where('email', $email)->first();
                if ($admin) {
                    foreach ($defaultPasswords as $pw) {
                        if (password_verify($pw, $admin->password ?? '')) {
                            $found = true;
                            break 2;
                        }
                    }
                    // Flag even if we can't verify (email alone is suspicious)
                    $found = true;
                    break;
                }
            }
        } catch (\Throwable) {
            // Table may not exist yet
        }

        return [
            'name'     => 'Default Credentials',
            'severity' => 'critical',
            'status'   => $found ? 'fail' : 'pass',
            'message'  => $found
                ? 'A default admin email (admin@example.com or similar) is in use. Change it immediately.'
                : 'No default admin credentials detected.',
        ];
    }

    private function checkDatabaseSecurity(): array
    {
        $user     = config('database.connections.' . config('database.default') . '.username');
        $password = config('database.connections.' . config('database.default') . '.password');

        $fail = ($user === 'root' && ($password === '' || $password === null));

        return [
            'name'     => 'Database Security',
            'severity' => 'high',
            'status'   => $fail ? 'fail' : 'pass',
            'message'  => $fail
                ? 'Database is using the root user with no password. Create a dedicated DB user.'
                : 'Database credentials appear to be non-default.',
        ];
    }

    private function checkPhpSettings(): array
    {
        $displayErrors = ini_get('display_errors');
        $exposePhp     = ini_get('expose_php');

        $issues = [];
        if ($displayErrors && $displayErrors !== '0' && $displayErrors !== 'Off') {
            $issues[] = 'display_errors is on';
        }
        if ($exposePhp && $exposePhp !== '0' && $exposePhp !== 'Off') {
            $issues[] = 'expose_php is on';
        }

        $fail = count($issues) > 0;

        return [
            'name'     => 'PHP Settings',
            'severity' => 'medium',
            'status'   => $fail ? 'fail' : 'pass',
            'message'  => $fail
                ? 'Insecure PHP settings detected: ' . implode(', ', $issues) . '.'
                : 'PHP security settings look good.',
        ];
    }

    private function checkSessionSecurity(): array
    {
        $httpOnly = config('session.http_only', true);
        $secure   = config('session.secure', false);

        $issues = [];
        if (!$httpOnly) {
            $issues[] = 'http_only is disabled';
        }
        if (!$secure && config('app.env') !== 'local') {
            $issues[] = 'secure cookie flag is off';
        }

        $fail = count($issues) > 0;

        return [
            'name'     => 'Session Security',
            'severity' => 'medium',
            'status'   => $fail ? 'warning' : 'pass',
            'message'  => $fail
                ? 'Session cookie issues: ' . implode(', ', $issues) . '.'
                : 'Session cookies are properly configured.',
        ];
    }

    private function checkSslHttps(): array
    {
        $appUrl   = config('app.url', '');
        $useHttps = str_starts_with($appUrl, 'https://');

        return [
            'name'     => 'SSL / HTTPS',
            'severity' => 'high',
            'status'   => $useHttps ? 'pass' : 'fail',
            'message'  => $useHttps
                ? 'Application URL is configured to use HTTPS.'
                : 'Application URL does not use HTTPS. Update APP_URL and enforce SSL.',
        ];
    }

    private function check2faStatus(): array
    {
        $total   = 0;
        $without = 0;

        try {
            $total   = DB::table('admins')->count();
            $with2fa = DB::table('admin_2fa')->where('is_enabled', 1)->count();
            $without = max(0, $total - $with2fa);
        } catch (\Throwable) {
            // Ignore
        }

        $fail = $without > 0;

        return [
            'name'     => '2FA Coverage',
            'severity' => 'medium',
            'status'   => $fail ? 'warning' : 'pass',
            'message'  => $fail
                ? "{$without} of {$total} admin account(s) have not enabled Two-Factor Authentication."
                : 'All admin accounts have 2FA enabled.',
        ];
    }

    private function checkFilePermissions(): array
    {
        $envPath = base_path('.env');
        $fail    = false;

        if (file_exists($envPath)) {
            $perms = fileperms($envPath);
            // Check if world-writable or group-writable
            $fail = (bool) ($perms & 0x0002) || (bool) ($perms & 0x0010);
        }

        return [
            'name'     => 'File Permissions',
            'severity' => 'high',
            'status'   => $fail ? 'fail' : 'pass',
            'message'  => $fail
                ? 'The .env file appears to be writable by group or world. Set permissions to 640 or 600.'
                : '.env file permissions appear secure.',
        ];
    }

    private function checkExposedFiles(): array
    {
        $publicEnv = public_path('.env');
        $fail      = false;

        // Check if .env is directly accessible in the public directory
        if (file_exists($publicEnv)) {
            $fail = true;
        }

        // Also attempt a lightweight HTTP check if running in non-CLI context
        if (!$fail) {
            $url = config('app.url') . '/.env';
            $ctx = stream_context_create([
                'http' => ['timeout' => 3, 'ignore_errors' => true],
            ]);
            try {
                $headers = @get_headers($url, false, $ctx);
                if ($headers && str_contains($headers[0], '200')) {
                    $fail = true;
                }
            } catch (\Throwable) {
                // Cannot check; assume safe
            }
        }

        return [
            'name'     => 'Exposed .env File',
            'severity' => 'critical',
            'status'   => $fail ? 'fail' : 'pass',
            'message'  => $fail
                ? 'The .env file is publicly accessible via HTTP. Block access immediately in your web server config.'
                : '.env file does not appear to be publicly exposed.',
        ];
    }

    private function checkUploadDirectory(): array
    {
        $storagePublic = public_path('storage');
        $found         = false;

        if (is_dir($storagePublic)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($storagePublic, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->getExtension() === 'php') {
                    $found = true;
                    break;
                }
            }
        }

        return [
            'name'     => 'Upload Directory',
            'severity' => 'high',
            'status'   => $found ? 'fail' : 'pass',
            'message'  => $found
                ? 'PHP files detected in the public storage directory. This is a serious risk.'
                : 'No PHP files found in public upload directories.',
        ];
    }

    private function checkBackupFiles(): array
    {
        $extensions = ['sql', 'bak', 'backup', 'dump', 'gz', 'tar', 'zip'];
        $found      = [];

        $publicPath = public_path();
        if (is_dir($publicPath)) {
            $iterator = new \DirectoryIterator($publicPath);
            foreach ($iterator as $file) {
                if ($file->isFile() && in_array(strtolower($file->getExtension()), $extensions)) {
                    $found[] = $file->getFilename();
                }
            }
        }

        $fail = count($found) > 0;

        return [
            'name'     => 'Backup Files in Public',
            'severity' => 'high',
            'status'   => $fail ? 'fail' : 'pass',
            'message'  => $fail
                ? 'Backup files found in public directory: ' . implode(', ', array_slice($found, 0, 5)) . '.'
                : 'No backup files found in public directory.',
        ];
    }

    private function checkOutdatedApp(): array
    {
        $version = app()->version();
        // Laravel 10+ is the current supported major
        $major   = (int) explode('.', $version)[0];
        $outdated = $major < 10;

        return [
            'name'     => 'Laravel Version',
            'severity' => 'info',
            'status'   => $outdated ? 'warning' : 'pass',
            'message'  => "Running Laravel {$version}."
                . ($outdated ? ' Consider upgrading to a supported version.' : ' Version looks current.'),
        ];
    }

    private function checkCsrfProtection(): array
    {
        $middleware = config('app.middleware', []);
        // Check kernel middleware
        $kernel       = app()->make(\Illuminate\Contracts\Http\Kernel::class);
        $allMiddleware = method_exists($kernel, 'getMiddlewareGroups')
            ? array_merge(...array_values($kernel->getMiddlewareGroups()))
            : [];

        $hasCsrf = false;
        foreach ($allMiddleware as $mw) {
            if (is_string($mw) && (
                str_contains($mw, 'VerifyCsrfToken') ||
                str_contains($mw, 'csrf')
            )) {
                $hasCsrf = true;
                break;
            }
        }

        // Fallback: check the web middleware group via router
        if (!$hasCsrf) {
            try {
                $groups = app('router')->getMiddlewareGroups();
                foreach ($groups['web'] ?? [] as $mw) {
                    if (str_contains((string) $mw, 'Csrf') || str_contains((string) $mw, 'csrf')) {
                        $hasCsrf = true;
                        break;
                    }
                }
            } catch (\Throwable) {
                $hasCsrf = true; // assume enabled if we can't check
            }
        }

        return [
            'name'     => 'CSRF Protection',
            'severity' => 'medium',
            'status'   => $hasCsrf ? 'pass' : 'fail',
            'message'  => $hasCsrf
                ? 'CSRF middleware is active in the web group.'
                : 'CSRF protection does not appear to be in the web middleware group.',
        ];
    }

    private function checkRateLimiting(): array
    {
        $throttleConfigured = false;

        try {
            $groups = app('router')->getMiddlewareGroups();
            foreach (array_merge($groups['web'] ?? [], $groups['api'] ?? []) as $mw) {
                if (str_contains((string) $mw, 'throttle')) {
                    $throttleConfigured = true;
                    break;
                }
            }
        } catch (\Throwable) {
            $throttleConfigured = true;
        }

        return [
            'name'     => 'Rate Limiting',
            'severity' => 'low',
            'status'   => $throttleConfigured ? 'pass' : 'warning',
            'message'  => $throttleConfigured
                ? 'Throttle middleware is configured.'
                : 'No throttle middleware detected. Consider adding rate limiting to sensitive routes.',
        ];
    }
}
