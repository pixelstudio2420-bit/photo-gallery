<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ExecutableFinder;

/**
 * `php artisan backup:database`
 *
 * Driver-aware DB snapshot for cron / scheduler:
 *   • pgsql   → pg_dump   (or PHP/PDO data-only fallback)
 *   • mysql   → mysqldump (or PHP/PDO fallback)
 *
 * Auto-retention: deletes snapshots older than --keep-days (default 14).
 *
 * Schedule (see routes/console.php):
 *   Schedule::command('backup:database')->dailyAt('03:00');
 */
class BackupDatabaseCommand extends Command
{
    protected $signature = 'backup:database
        {--keep-days=14 : Delete backups older than this many days (0 = keep forever)}
        {--quiet-success : Suppress success line — useful in cron to avoid noise}';

    protected $description = 'Snapshot the active DB to storage/app/backups/ (pg_dump / mysqldump / PHP fallback). Auto-prunes old files.';

    public function handle(): int
    {
        Storage::disk('local')->makeDirectory('backups');

        $driver  = DB::connection()->getDriverName();
        $cfgKey  = $driver === 'pgsql' ? 'pgsql' : 'mysql';
        $host     = config("database.connections.{$cfgKey}.host", '127.0.0.1');
        $port     = (string) config("database.connections.{$cfgKey}.port", $driver === 'pgsql' ? '5432' : '3306');
        $database = config("database.connections.{$cfgKey}.database", 'jabphap');
        $username = config("database.connections.{$cfgKey}.username", $driver === 'pgsql' ? 'postgres' : 'root');
        $password = (string) config("database.connections.{$cfgKey}.password", '');

        $timestamp  = now()->format('Y-m-d_H-i-s');
        $filename   = "backup_{$database}_{$timestamp}.sql";
        $backupPath = storage_path("app/backups/{$filename}");

        $started = microtime(true);

        try {
            $size = $this->dump($driver, $host, $port, $database, $username, $password, $backupPath);
        } catch (\Throwable $e) {
            $this->error("Backup failed: " . $e->getMessage());
            Log::error('backup:database failed', ['error' => $e->getMessage()]);
            if (file_exists($backupPath)) @unlink($backupPath);
            return self::FAILURE;
        }

        $elapsed = round(microtime(true) - $started, 1);

        if (!$this->option('quiet-success')) {
            $this->info(sprintf(
                '✓ Backup created: %s (%s, %ss)',
                $filename,
                $this->formatBytes($size),
                $elapsed
            ));
        }
        Log::info('backup:database success', ['file' => $filename, 'bytes' => $size, 'elapsed_sec' => $elapsed]);

        // ── Retention ─────────────────────────────────────────────────
        $keepDays = (int) $this->option('keep-days');
        if ($keepDays > 0) {
            $this->prune($keepDays);
        }

        return self::SUCCESS;
    }

    /**
     * Dump using the right binary for the driver, falling back to
     * the PDO dumper if the binary is missing / fails.
     */
    protected function dump(string $driver, string $host, string $port, string $database, string $username, string $password, string $path): int
    {
        if ($driver === 'pgsql') {
            $binary = $this->locate(['pg_dump', 'pg_dump.exe'], [
                'C:\\Program Files\\PostgreSQL\\17\\bin\\pg_dump.exe',
                'C:\\Program Files\\PostgreSQL\\16\\bin\\pg_dump.exe',
                'C:\\PostgresData\\pg16-portable\\pgsql\\bin\\pg_dump.exe',
                '/usr/bin/pg_dump',
                '/usr/local/bin/pg_dump',
                '/opt/homebrew/bin/pg_dump',
            ]);
            if ($binary) {
                $args = [$binary, "--host=$host", "--port=$port", "--username=$username",
                         '--no-owner', '--no-privileges', '--clean', '--if-exists', '--encoding=UTF8',
                         $database];
                $env  = ['PGPASSWORD' => $password] + $this->minWindowsEnv();
                if ($this->runProcess($args, $env, $path)) {
                    return filesize($path) ?: 0;
                }
            }
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            $binary = $this->locate(['mysqldump', 'mysqldump.exe'], [
                'C:\\xampp\\mysql\\bin\\mysqldump.exe',
                'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
                '/usr/bin/mysqldump',
            ]);
            if ($binary) {
                $args = [$binary, "--host=$host", "--port=$port", "--user=$username",
                         '--single-transaction', '--routines', '--triggers',
                         '--default-character-set=utf8mb4', '--no-tablespaces',
                         $database];
                $env  = ['MYSQL_PWD' => $password] + $this->minWindowsEnv();
                if ($this->runProcess($args, $env, $path)) {
                    return filesize($path) ?: 0;
                }
            }
        }

        // Fall back to the controller's PDO dumper (driver-aware itself).
        // We instantiate a throwaway controller-like helper inline since the
        // dumper is currently a protected trait method.
        $size = $this->phpFallbackDump($path);
        if ($size === 0) {
            throw new \RuntimeException('Database dump produced empty output');
        }
        return $size;
    }

    /** Run the binary with stdout redirected to file. Returns true on success. */
    protected function runProcess(array $args, array $env, string $outPath): bool
    {
        $fh = fopen($outPath, 'wb');
        if (!$fh) return false;
        try {
            $process = new Process($args);
            $process->setTimeout(900);
            $process->setEnv($env);
            $stderr = '';
            $process->run(function ($type, $buffer) use ($fh, &$stderr) {
                if ($type === Process::OUT) fwrite($fh, $buffer);
                else $stderr .= $buffer;
            });
            fclose($fh);
            if (!$process->isSuccessful()) {
                Log::warning('backup binary failed', [
                    'cmd'    => $args[0],
                    'stderr' => substr(trim($stderr), 0, 1000),
                    'exit'   => $process->getExitCode(),
                ]);
                @unlink($outPath);
                return false;
            }
            return filesize($outPath) > 0;
        } catch (\Throwable $e) {
            if (is_resource($fh)) @fclose($fh);
            return false;
        }
    }

    protected function locate(array $names, array $candidates): ?string
    {
        $finder = new ExecutableFinder();
        foreach ($names as $name) {
            $found = $finder->find($name);
            if ($found) return $found;
        }
        foreach ($candidates as $path) {
            if (is_file($path)) return $path;
        }
        return null;
    }

    /**
     * Inline PHP-PDO dumper (driver-aware) — same idea as
     * `HandlesStorage::dumpDatabaseViaPdo` but accessible from this command.
     */
    protected function phpFallbackDump(string $path): int
    {
        $pdo = DB::connection()->getPdo();
        $driver = DB::connection()->getDriverName();
        $fh = fopen($path, 'wb');
        if (!$fh) throw new \RuntimeException("Cannot open {$path} for writing");

        try {
            $db = DB::connection()->getDatabaseName();
            fwrite($fh, "-- {$driver} backup of {$db} (PHP fallback) " . now() . "\n");

            if ($driver === 'pgsql') {
                fwrite($fh, "SET session_replication_role = replica;\n\n");
                $tablesQ = "SELECT tablename FROM pg_tables WHERE schemaname='public' ORDER BY tablename";
                $idQuote = '"';
            } else {
                fwrite($fh, "SET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\n\n");
                $tablesQ = "SHOW TABLES";
                $idQuote = '`';
            }

            foreach ($pdo->query($tablesQ) as $row) {
                $table = is_array($row) ? array_values($row)[0] : (is_object($row) ? array_values((array) $row)[0] : $row);
                $q = $idQuote . str_replace($idQuote, $idQuote.$idQuote, $table) . $idQuote;
                fwrite($fh, "-- ── {$table} ──\n");
                $stmt = $pdo->query("SELECT * FROM {$q}");
                $colCount = $stmt->columnCount();
                $cols = [];
                for ($i = 0; $i < $colCount; $i++) {
                    $cols[] = $idQuote . str_replace($idQuote, $idQuote.$idQuote, $stmt->getColumnMeta($i)['name']) . $idQuote;
                }
                $colList = implode(',', $cols);
                $batch = [];
                while ($r = $stmt->fetch(\PDO::FETCH_NUM)) {
                    $vals = [];
                    foreach ($r as $v) {
                        if ($v === null) $vals[] = 'NULL';
                        elseif (is_bool($v)) $vals[] = $v ? 'TRUE' : 'FALSE';
                        elseif (is_int($v) || is_float($v)) $vals[] = $v;
                        else $vals[] = $pdo->quote($v);
                    }
                    $batch[] = '(' . implode(',', $vals) . ')';
                    if (count($batch) >= 100) {
                        fwrite($fh, "INSERT INTO {$q} ({$colList}) VALUES\n" . implode(",\n", $batch) . ";\n");
                        $batch = [];
                    }
                }
                if ($batch) {
                    fwrite($fh, "INSERT INTO {$q} ({$colList}) VALUES\n" . implode(",\n", $batch) . ";\n");
                }
                fwrite($fh, "\n");
            }
            if ($driver === 'pgsql') {
                fwrite($fh, "SET session_replication_role = DEFAULT;\n");
            } else {
                fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
            }
            fclose($fh);
            return filesize($path) ?: 0;
        } catch (\Throwable $e) {
            if (is_resource($fh)) @fclose($fh);
            throw $e;
        }
    }

    /** Delete backup files older than $days. */
    protected function prune(int $days): int
    {
        // Use raw filesystem read — backups live at storage/app/backups/
        // (binaries write directly via storage_path), and Laravel 12's
        // Storage::disk('local') points to storage/app/private/ which is
        // a different directory.
        $cutoff = time() - ($days * 86400);
        $dir    = storage_path('app/backups');
        $deleted = 0;
        foreach ((array) glob($dir . '/backup_*.{sql,zip}', GLOB_BRACE) as $fullPath) {
            if (!is_file($fullPath)) continue;
            $mtime = filemtime($fullPath) ?: 0;
            if ($mtime < $cutoff) {
                @unlink($fullPath);
                $deleted++;
            }
        }
        if ($deleted > 0) {
            $this->line("  ↳ Pruned {$deleted} backup(s) older than {$days} days");
            Log::info('backup:database pruned old snapshots', ['count' => $deleted, 'cutoff_days' => $days]);
        }
        return $deleted;
    }

    protected function minWindowsEnv(): array
    {
        if (stripos(PHP_OS_FAMILY, 'Windows') !== 0) return [];
        return [
            'SystemRoot'  => getenv('SystemRoot') ?: 'C:\\Windows',
            'PATH'        => getenv('PATH')      ?: '',
            'TEMP'        => getenv('TEMP')      ?: sys_get_temp_dir(),
            'USERPROFILE' => getenv('USERPROFILE') ?: '',
        ];
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1024 * 1024) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1024 * 1024 * 1024) return round($bytes / 1024 / 1024, 2) . ' MB';
        return round($bytes / 1024 / 1024 / 1024, 2) . ' GB';
    }
}
