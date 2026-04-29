<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class HoneypotService
{
    /**
     * Known trap paths that bots commonly probe.
     */
    private array $trapPaths = [
        'wp-admin',
        'wp-login.php',
        'phpmyadmin',
        'pma',
        'adminer',
        '.env',
        '.git/config',
        '.git/HEAD',
        'xmlrpc.php',
        'wp-content',
        'wp-includes',
        'administrator',
        'admin.php',
        'config.php',
        'setup.php',
        'install.php',
        'backup.sql',
        '.htaccess',
        'shell.php',
        'c99.php',
    ];

    /**
     * Render hidden honeypot form fields plus a timing HMAC token.
     */
    public function renderFormFields(): string
    {
        $timestamp = time();
        $token     = $this->generateHpToken($timestamp);

        return sprintf(
            '<div style="display:none !important" aria-hidden="true">'
            . '<input type="text"  name="website"       value="" tabindex="-1" autocomplete="off">'
            . '<input type="email" name="email_confirm" value="" tabindex="-1" autocomplete="off">'
            . '<input type="text"  name="phone_number"  value="" tabindex="-1" autocomplete="off">'
            . '</div>'
            . '<input type="hidden" name="_hp_t" value="%s">',
            e($token)
        );
    }

    /**
     * Verify a form submission against the honeypot fields.
     *
     * Returns true  → legitimate (pass)
     * Returns false → bot detected (fail)
     */
    public function checkFormSubmission(array $data): bool
    {
        // Any honeypot field filled → bot
        if (
            !empty($data['website'])
            || !empty($data['email_confirm'])
            || !empty($data['phone_number'])
        ) {
            return false;
        }

        // Verify timing token
        if (empty($data['_hp_t'])) {
            return false;
        }

        if (!$this->verifyHpToken($data['_hp_t'])) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the given URI matches a known trap path.
     */
    public function checkTrapPath(string $uri): bool
    {
        $uri = ltrim(strtolower($uri), '/');

        foreach ($this->trapPaths as $trap) {
            // Exact match or starts-with match (e.g. wp-admin/...)
            if ($uri === $trap || str_starts_with($uri, $trap . '/') || str_starts_with($uri, $trap . '?')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return the full list of trap path patterns.
     */
    public function getTrapPaths(): array
    {
        return $this->trapPaths;
    }

    /**
     * Log a honeypot trigger to the honeypot_traps table.
     *
     * @param string      $type  e.g. 'form', 'url_trap'
     * @param string      $ip
     * @param string|null $ua    User-Agent
     * @param array|null  $data  Additional context
     */
    public function logTrap(string $type, string $ip, ?string $ua = null, ?array $data = null): void
    {
        try {
            DB::table('honeypot_traps')->insert([
                'type'       => $type,
                'ip'         => $ip,
                'user_agent' => $ua,
                'data'       => $data ? json_encode($data) : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Table may not exist — silently ignore
        }
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Generate an HMAC-based timing token: "<timestamp>.<hmac>"
     */
    private function generateHpToken(int $timestamp): string
    {
        $hmac = hash_hmac('sha256', (string) $timestamp, $this->secret());
        return $timestamp . '.' . $hmac;
    }

    /**
     * Verify the token format, HMAC, and that the form was submitted within
     * a reasonable window (2 seconds minimum, 2 hours maximum).
     */
    private function verifyHpToken(string $token): bool
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return false;
        }

        [$timestamp, $hmac] = $parts;

        if (!ctype_digit($timestamp)) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp, $this->secret());
        if (!hash_equals($expected, $hmac)) {
            return false;
        }

        $elapsed = time() - (int) $timestamp;

        // Too fast → likely a bot; too old → replay / stale session
        if ($elapsed < 2 || $elapsed > 7200) {
            return false;
        }

        return true;
    }

    /**
     * HMAC secret derived from the application key.
     */
    private function secret(): string
    {
        return config('app.key', 'honeypot-secret');
    }
}
