<?php

namespace App\Services\Deployment;

use Illuminate\Support\Facades\Log;

/**
 * Read & write the application's .env file.
 *
 * Why a dedicated service: settings like DB_HOST, APP_URL, MAIL_PASSWORD must
 * live in .env (Laravel reads them on bootstrap before the database is even
 * available — they can't be stored in `app_settings`). A bad write here can
 * brick the entire site, so every mutation is:
 *
 *   1. Atomic (write to .env.tmp, then rename — never half-written file).
 *   2. Backed up automatically (storage/app/env-backups/.env.YYYYMMDD_HHMMSS).
 *   3. Idempotent for missing keys (append) and existing keys (in-place replace).
 *   4. Quote-safe (values with spaces/#/$ get wrapped in double quotes).
 */
class EnvManagerService
{
    private string $envPath;
    private string $backupDir;

    public function __construct()
    {
        $this->envPath   = base_path('.env');
        $this->backupDir = storage_path('app/env-backups');
    }

    // -------------------------------------------------------------------------
    // Read
    // -------------------------------------------------------------------------

    /** Get a single .env value, or default if not set. */
    public function get(string $key, ?string $default = null): ?string
    {
        return $this->getAll()[$key] ?? $default;
    }

    /**
     * Parse the .env file into an associative array.
     * Comments and blank lines are skipped. Values keep their unquoted form.
     */
    public function getAll(): array
    {
        if (!is_file($this->envPath)) return [];

        $content = (string) @file_get_contents($this->envPath);
        $values = [];

        foreach (preg_split('/\R/', $content) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            if (!preg_match('/^([A-Z_][A-Z0-9_]*)\s*=\s*(.*)$/i', $line, $m)) continue;

            $values[$m[1]] = $this->unquote(trim($m[2]));
        }
        return $values;
    }

    /** Does the .env file exist? */
    public function exists(): bool
    {
        return is_file($this->envPath);
    }

    /** Is the .env file writable by the web server? */
    public function isWritable(): bool
    {
        if (is_file($this->envPath)) return is_writable($this->envPath);
        return is_writable(dirname($this->envPath));
    }

    // -------------------------------------------------------------------------
    // Write (atomic + backup)
    // -------------------------------------------------------------------------

    /**
     * Update one or more keys. Existing keys are replaced in-place; missing
     * keys are appended. A timestamped backup of the previous .env is saved
     * to storage/app/env-backups/ before any change.
     *
     * Returns true on success. On failure, the original file is untouched
     * (atomic via rename — see implementation note).
     */
    public function set(array $updates): bool
    {
        if (empty($updates)) return true;

        // Read current content (or empty for fresh install).
        $current = $this->exists() ? (string) file_get_contents($this->envPath) : "";

        // Backup before mutating.
        if ($this->exists()) {
            $this->backup($current);
        }

        $next = $current;

        foreach ($updates as $key => $value) {
            $key   = strtoupper(trim($key));
            $value = (string) ($value ?? '');
            $line  = $key . '=' . $this->quote($value);

            // Replace existing line (preserve comments + ordering) or append.
            $pattern = '/^' . preg_quote($key, '/') . '\s*=.*$/m';
            if (preg_match($pattern, $next)) {
                $next = preg_replace($pattern, $line, $next);
            } else {
                $next = rtrim($next, "\r\n") . "\n" . $line . "\n";
            }
        }

        return $this->atomicWrite($next);
    }

    /**
     * Write `$content` to .env atomically: tmp file + rename. If anything
     * fails partway, the original file is untouched.
     */
    private function atomicWrite(string $content): bool
    {
        $tmp = $this->envPath . '.' . uniqid('tmp_', true);
        try {
            $bytes = @file_put_contents($tmp, $content, LOCK_EX);
            if ($bytes === false) {
                Log::error('EnvManager: failed to write tmp .env', ['tmp' => $tmp]);
                return false;
            }
            // rename() is atomic on POSIX. On Windows, it's atomic at filesystem level if dest exists.
            if (!@rename($tmp, $this->envPath)) {
                @unlink($tmp);
                Log::error('EnvManager: failed to rename tmp to .env');
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            @unlink($tmp);
            Log::error('EnvManager: exception during atomicWrite', ['err' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Save a backup copy of the current .env. Keeps the last 30 backups
     * and prunes older ones to avoid bloat.
     *
     * Returns the absolute backup path (or empty string on failure).
     */
    public function backup(?string $content = null): string
    {
        if (!is_dir($this->backupDir)) {
            @mkdir($this->backupDir, 0755, true);
        }
        if ($content === null) {
            if (!$this->exists()) return '';
            $content = (string) file_get_contents($this->envPath);
        }
        $name = '.env.' . now()->format('Ymd_His') . '.bak';
        $path = $this->backupDir . DIRECTORY_SEPARATOR . $name;
        if (@file_put_contents($path, $content, LOCK_EX) === false) {
            return '';
        }
        $this->prune(30);
        return $path;
    }

    /** List the most recent backups (newest first). */
    public function listBackups(int $limit = 30): array
    {
        if (!is_dir($this->backupDir)) return [];

        $items = [];
        foreach ((array) @scandir($this->backupDir) as $f) {
            if ($f === '.' || $f === '..' || !str_starts_with($f, '.env.')) continue;
            $abs = $this->backupDir . DIRECTORY_SEPARATOR . $f;
            if (!is_file($abs)) continue;
            $items[] = [
                'name'  => $f,
                'path'  => $abs,
                'size'  => filesize($abs),
                'mtime' => filemtime($abs),
            ];
        }
        usort($items, fn ($a, $b) => $b['mtime'] <=> $a['mtime']);
        return array_slice($items, 0, $limit);
    }

    /** Delete oldest backups beyond the keep-N limit. */
    private function prune(int $keep = 30): void
    {
        $list = $this->listBackups(9999);
        $stale = array_slice($list, $keep);
        foreach ($stale as $item) {
            @unlink($item['path']);
        }
    }

    // -------------------------------------------------------------------------
    // Quote / unquote
    // -------------------------------------------------------------------------

    private function unquote(string $val): string
    {
        if (strlen($val) >= 2 && $val[0] === '"' && $val[strlen($val) - 1] === '"') {
            return stripcslashes(substr($val, 1, -1));
        }
        if (strlen($val) >= 2 && $val[0] === "'" && $val[strlen($val) - 1] === "'") {
            return substr($val, 1, -1);
        }
        return $val;
    }

    private function quote(string $val): string
    {
        // Empty value → no quotes (standard .env convention).
        if ($val === '') return '';

        // Quote when value contains characters that .env parsers treat specially.
        // Always safe to quote — only avoid for clean alnum/_/-/.: which Laravel
        // env() handles fine bare.
        if (preg_match('/[\s#"\'\\\\$=&\(\)\{\}\|<>]/', $val)) {
            // Escape backslash and double-quote for valid quoted form.
            $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $val);
            return '"' . $escaped . '"';
        }
        return $val;
    }
}
