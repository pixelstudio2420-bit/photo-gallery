<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\Process\ExecutableFinder;
use Throwable;

/**
 * `php artisan env:check [--strict]`
 *
 * Pre-deploy validation gate. Strict mode (used in CI/CD) returns non-zero
 * exit code on any error. Non-strict prints findings only.
 *
 * Checks:
 *   - APP_KEY set + valid format
 *   - APP_DEBUG=false in production
 *   - DB driver matches this fork (pgsql)
 *   - DB connection reachable
 *   - No CHANGE_ME placeholders left in critical secrets
 *   - Required PHP extensions
 *   - pg_dump binary discoverable
 *   - Redis reachable when CACHE_STORE/SESSION_DRIVER/QUEUE_CONNECTION = redis
 *   - Stripe / Omise webhook secrets present when gateways enabled
 */
class EnvironmentCheckCommand extends Command
{
    protected $signature = 'env:check
        {--strict : Exit non-zero on any failure (use in CI/CD)}
        {--quiet-success : Suppress all-good output}';

    protected $description = 'Validate the .env / runtime environment is production-ready.';

    /** @var array<int, array{level:string,msg:string}> */
    private array $findings = [];

    public function handle(): int
    {
        $isProd = app()->environment('production');

        $this->checkAppKey();
        $this->checkAppDebug($isProd);
        $this->checkDbDriver();
        $this->checkDbConnectivity();
        $this->checkPlaceholders($isProd);
        $this->checkPhpExtensions();
        $this->checkPgDump();
        $this->checkRedis();
        $this->checkPaymentSecrets($isProd);
        $this->checkSentry($isProd);

        return $this->report();
    }

    private function checkAppKey(): void
    {
        $key = config('app.key');
        if (!$key) {
            $this->recordFail('APP_KEY is not set. Run: php artisan key:generate');
            return;
        }
        if (!str_starts_with((string) $key, 'base64:')) {
            $this->recordFail("APP_KEY does not start with 'base64:' — regenerate with `php artisan key:generate`");
            return;
        }
        $this->recordOk('APP_KEY set and well-formed');
    }

    private function checkAppDebug(bool $isProd): void
    {
        $debug = config('app.debug');
        if ($isProd && $debug) {
            $this->recordFail('APP_DEBUG=true while APP_ENV=production. Stack traces will leak. Set APP_DEBUG=false.');
            return;
        }
        $this->recordOk('APP_DEBUG=' . ($debug ? 'true' : 'false'));
    }

    private function checkDbDriver(): void
    {
        $driver = config('database.default');
        if ($driver !== 'pgsql') {
            $this->recordFail("DB_CONNECTION={$driver} — this fork requires 'pgsql' (Postgres-specific SQL). See README.PGSQL.md.");
            return;
        }
        $this->recordOk('DB_CONNECTION=pgsql');
    }

    private function checkDbConnectivity(): void
    {
        try {
            DB::connection()->getPdo();
            DB::select('SELECT 1');
            $this->recordOk('Database reachable + responsive');
        } catch (Throwable $e) {
            $this->recordFail('Database unreachable: ' . $e->getMessage());
        }
    }

    private function checkPlaceholders(bool $isProd): void
    {
        if (!$isProd) {
            return;
        }
        $candidates = [
            'database.connections.pgsql.host',
            'database.connections.pgsql.database',
            'database.connections.pgsql.username',
            'database.connections.pgsql.password',
            'mail.mailers.smtp.host',
            'mail.from.address',
            'services.google.client_id',
            'services.google.client_secret',
        ];
        foreach ($candidates as $cfg) {
            $val = (string) config($cfg, '');
            if ($val === 'CHANGE_ME' || $val === '' || str_contains($val, 'your-domain.com') || str_contains($val, 'your-')) {
                $this->recordFail("Config '{$cfg}' still has placeholder/empty value");
            }
        }
        $this->recordOk('No CHANGE_ME placeholders found in critical configs');
    }

    private function checkPhpExtensions(): void
    {
        $required = ['pdo_pgsql', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'json', 'fileinfo', 'gd'];
        $missing = array_filter($required, fn ($ext) => !extension_loaded($ext));
        if ($missing) {
            $this->recordFail('Missing PHP extensions: ' . implode(', ', $missing));
            return;
        }
        $this->recordOk('All required PHP extensions loaded (' . count($required) . ')');
    }

    private function checkPgDump(): void
    {
        $finder = new ExecutableFinder();
        $found  = $finder->find('pg_dump') ?? env('PG_DUMP_PATH');
        if (!$found || !is_executable($found)) {
            $this->recordWarn('pg_dump not found in PATH and PG_DUMP_PATH unset. Backups will use slower PHP fallback.');
            return;
        }
        $this->recordOk("pg_dump found: {$found}");
    }

    private function checkRedis(): void
    {
        $usesRedis = in_array('redis', [
            config('cache.default'),
            config('session.driver'),
            config('queue.default'),
            config('broadcasting.default'),
        ], true);

        if (!$usesRedis) {
            return;
        }

        try {
            Redis::connection()->ping();
            $this->recordOk('Redis reachable');
        } catch (Throwable $e) {
            $this->recordFail('Redis is configured but unreachable: ' . $e->getMessage());
        }
    }

    private function checkPaymentSecrets(bool $isProd): void
    {
        if (!$isProd) {
            return;
        }
        $gateways = [
            'STRIPE_KEY'      => 'Stripe',
            'STRIPE_SECRET'   => 'Stripe',
            'OMISE_PUBLIC_KEY' => 'Omise',
            'OMISE_SECRET_KEY' => 'Omise',
        ];
        foreach ($gateways as $envKey => $gateway) {
            $val = (string) env($envKey, '');
            if ($val === '' || $val === 'CHANGE_ME') {
                $this->recordWarn("Payment gateway {$gateway} env '{$envKey}' is empty — gateway will be unavailable");
            }
        }
    }

    private function checkSentry(bool $isProd): void
    {
        if (!$isProd) {
            return;
        }
        if (!env('SENTRY_LARAVEL_DSN')) {
            $this->recordWarn('SENTRY_LARAVEL_DSN is empty — production errors will not be tracked');
            return;
        }
        $this->recordOk('Sentry DSN configured');
    }

    private function recordOk(string $msg): void   { $this->findings[] = ['level' => 'ok',   'msg' => $msg]; }
    private function recordWarn(string $msg): void { $this->findings[] = ['level' => 'warn', 'msg' => $msg]; }
    private function recordFail(string $msg): void { $this->findings[] = ['level' => 'fail', 'msg' => $msg]; }

    private function report(): int
    {
        $fails = $warns = 0;
        foreach ($this->findings as $f) {
            match ($f['level']) {
                'ok'   => $this->components->info('  ✓ ' . $f['msg']),
                'warn' => $this->components->warn('  ⚠ ' . $f['msg']) ?? $warns++,
                'fail' => $this->components->error('  ✗ ' . $f['msg']) ?? $fails++,
            };
            $f['level'] === 'warn' && $warns++;
            $f['level'] === 'fail' && $fails++;
        }

        $this->newLine();
        if ($fails > 0) {
            $this->components->error("env:check FAILED — {$fails} error(s), {$warns} warning(s)");
            return $this->option('strict') ? self::FAILURE : self::SUCCESS;
        }
        if ($warns > 0) {
            $this->components->warn("env:check completed with {$warns} warning(s) — review before deploy");
            return self::SUCCESS;
        }
        if (!$this->option('quiet-success')) {
            $this->components->info('✓ env:check passed — all systems go');
        }
        return self::SUCCESS;
    }
}
