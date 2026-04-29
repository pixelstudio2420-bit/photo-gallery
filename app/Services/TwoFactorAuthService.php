<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class TwoFactorAuthService
{
    /**
     * Base32 alphabet (RFC 4648)
     */
    private const BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generate a random Base32 secret of given length (in bytes before encoding).
     */
    public function generateSecret(int $length = 20): string
    {
        $randomBytes = random_bytes($length);
        return $this->base32Encode($randomBytes);
    }

    /**
     * Build the otpauth:// URI for use in QR codes.
     */
    public function getQRCodeUrl(string $email, string $secret, string $issuer = null): string
    {
        $issuer = $issuer ?? config('app.name', 'PhotoGallery');
        $label  = rawurlencode($issuer . ':' . $email);

        return 'otpauth://totp/' . $label
            . '?secret=' . rawurlencode($secret)
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1'
            . '&digits=6'
            . '&period=30';
    }

    /**
     * Return the URL of the QR code image (via api.qrserver.com).
     */
    public function getQRCodeImageUrl(string $data, int $size = 200): string
    {
        return 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size
            . '&data=' . urlencode($data);
    }

    /**
     * Verify a 6-digit TOTP code within ±$window time-steps.
     */
    public function verifyCode(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\s+/', '', $code);
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $timeSlice = $this->getTimeSlice();

        for ($i = -$window; $i <= $window; $i++) {
            $expected = $this->generateTOTP($secret, $timeSlice + $i);
            if (hash_equals($expected, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Save (or update) the 2FA secret for an admin and mark as enabled.
     */
    public function enable(int $adminId, string $secret): void
    {
        $existing = DB::table('admin_2fa')->where('admin_id', $adminId)->first();

        if ($existing) {
            DB::table('admin_2fa')->where('admin_id', $adminId)->update([
                'secret_key' => $secret,
                'is_enabled' => 1,
                'updated_at' => now(),
            ]);
        } else {
            DB::table('admin_2fa')->insert([
                'admin_id'   => $adminId,
                'secret_key' => $secret,
                'is_enabled' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Disable 2FA for an admin (removes the record).
     */
    public function disable(int $adminId): void
    {
        DB::table('admin_2fa')->where('admin_id', $adminId)->delete();
    }

    /**
     * Check whether 2FA is currently enabled for an admin.
     */
    public function isEnabled(int $adminId): bool
    {
        $row = DB::table('admin_2fa')
            ->where('admin_id', $adminId)
            ->where('is_enabled', 1)
            ->first();

        return $row !== null;
    }

    /**
     * Retrieve the stored secret for an admin (null if not set).
     */
    public function getSecret(int $adminId): ?string
    {
        $row = DB::table('admin_2fa')->where('admin_id', $adminId)->first();
        return $row ? $row->secret_key : null;
    }

    /**
     * Generate an array of random backup codes (8-char hex strings).
     */
    public function generateBackupCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(4))); // 8 hex chars
        }
        return $codes;
    }

    /**
     * Save bcrypt-hashed backup codes for an admin.
     */
    public function saveBackupCodes(int $adminId, array $codes): void
    {
        $hashed = array_map(fn($c) => ['hash' => bcrypt($c), 'used' => false], $codes);

        DB::table('admin_2fa')->where('admin_id', $adminId)->update([
            'backup_codes' => json_encode($hashed),
            'updated_at'   => now(),
        ]);
    }

    /**
     * Verify a single-use backup code; marks it used if valid.
     */
    public function verifyBackupCode(int $adminId, string $code): bool
    {
        $row = DB::table('admin_2fa')->where('admin_id', $adminId)->first();
        if (!$row || !$row->backup_codes) {
            return false;
        }

        $codes   = json_decode($row->backup_codes, true);
        $updated = false;
        $valid   = false;

        foreach ($codes as &$entry) {
            if (!$entry['used'] && password_verify(strtoupper(trim($code)), $entry['hash'])) {
                $entry['used'] = true;
                $valid         = true;
                $updated       = true;
                break;
            }
        }
        unset($entry);

        if ($updated) {
            DB::table('admin_2fa')->where('admin_id', $adminId)->update([
                'backup_codes' => json_encode($codes),
                'updated_at'   => now(),
            ]);
        }

        return $valid;
    }

    // -------------------------------------------------------------------------
    // Private TOTP internals
    // -------------------------------------------------------------------------

    /**
     * Decode a Base32 string to binary.
     */
    private function base32Decode(string $b32): string
    {
        $b32     = strtoupper(preg_replace('/=+$/', '', $b32));
        $buffer  = 0;
        $bufLen  = 0;
        $output  = '';
        $charset = self::BASE32_CHARS;

        for ($i = 0; $i < strlen($b32); $i++) {
            $pos = strpos($charset, $b32[$i]);
            if ($pos === false) {
                continue;
            }
            $buffer = ($buffer << 5) | $pos;
            $bufLen += 5;

            if ($bufLen >= 8) {
                $bufLen -= 8;
                $output .= chr(($buffer >> $bufLen) & 0xFF);
            }
        }

        return $output;
    }

    /**
     * Encode binary data to Base32.
     */
    private function base32Encode(string $data): string
    {
        $charset = self::BASE32_CHARS;
        $output  = '';
        $buffer  = 0;
        $bufLen  = 0;

        for ($i = 0; $i < strlen($data); $i++) {
            $buffer = ($buffer << 8) | ord($data[$i]);
            $bufLen += 8;

            while ($bufLen >= 5) {
                $bufLen -= 5;
                $output .= $charset[($buffer >> $bufLen) & 0x1F];
            }
        }

        if ($bufLen > 0) {
            $output .= $charset[($buffer << (5 - $bufLen)) & 0x1F];
        }

        return $output;
    }

    /**
     * Generate a 6-digit TOTP code for a given time slice (RFC 6238 / HOTP RFC 4226).
     */
    private function generateTOTP(string $secret, int $timeSlice): string
    {
        $key = $this->base32Decode($secret);

        // Pack time slice as 64-bit big-endian integer
        $time = pack('N*', 0) . pack('N*', $timeSlice);

        $hmac = hash_hmac('sha1', $time, $key, true);

        // Dynamic truncation
        $offset = ord($hmac[19]) & 0x0F;
        $code   = (
            ((ord($hmac[$offset])     & 0x7F) << 24) |
            ((ord($hmac[$offset + 1]) & 0xFF) << 16) |
            ((ord($hmac[$offset + 2]) & 0xFF) <<  8) |
             (ord($hmac[$offset + 3]) & 0xFF)
        ) % 1_000_000;

        return str_pad((string) $code, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Return the current 30-second time slice.
     */
    private function getTimeSlice(): int
    {
        return (int) floor(time() / 30);
    }
}
