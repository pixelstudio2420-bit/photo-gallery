<?php

namespace Tests\Feature\Auth;

use App\Models\Admin;
use App\Services\TwoFactorAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Direct tests for TwoFactorAuthService.
 *
 * Why this exists
 * ---------------
 * The Round-12 perf audit found 2FA = 273 LOC, 11 public methods,
 * **0 direct test coverage**. The only existing reference uses 2FA as
 * a setup-shim for unrelated admin tests. That left the TOTP math
 * (RFC 6238), Base32 round-trip, and backup-code single-use semantics
 * completely unverified.
 *
 * Without these tests:
 *   • A regression in time-window math could let yesterday's code work
 *     today (or break a code that should still be valid).
 *   • Backup codes could be reused (security failure) without anyone
 *     noticing until an audit.
 *   • Base32 encoding bugs would break QR-code provisioning silently.
 *
 * Each test pins a property the service guarantees, derived from the
 * RFC + the documented behavior in the controller layer.
 */
class TwoFactorAuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private TwoFactorAuthService $svc;
    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(TwoFactorAuthService::class);
        $this->admin = Admin::create([
            'email'         => 'tfa-' . uniqid() . '@example.com',
            'password_hash' => Hash::make('p'),
            'first_name'    => 'T',
            'last_name'     => 'A',
            'role'          => 'admin',
            'is_active'     => true,
        ]);
    }

    /* ───────────── secret generation ───────────── */

    public function test_generated_secret_is_base32_of_expected_length(): void
    {
        $secret = $this->svc->generateSecret();

        // 20 random bytes encoded as Base32 = 32 characters (no padding).
        $this->assertSame(32, strlen($secret));
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret,
            'Base32 alphabet only — RFC 4648');
    }

    public function test_generated_secrets_are_unique(): void
    {
        $a = $this->svc->generateSecret();
        $b = $this->svc->generateSecret();
        $this->assertNotSame($a, $b, 'CSPRNG must produce different secrets');
    }

    /* ───────────── QR URL ───────────── */

    public function test_qr_url_is_otpauth_with_required_params(): void
    {
        $url = $this->svc->getQRCodeUrl('user@example.com', 'JBSWY3DPEHPK3PXP', 'TestIssuer');

        $this->assertStringStartsWith('otpauth://totp/', $url);
        $this->assertStringContainsString('secret=JBSWY3DPEHPK3PXP', $url);
        $this->assertStringContainsString('issuer=TestIssuer', $url);
        $this->assertStringContainsString('digits=6', $url);
        $this->assertStringContainsString('period=30', $url);
        $this->assertStringContainsString('algorithm=SHA1', $url);
    }

    /* ───────────── verifyCode (TOTP) ───────────── */

    public function test_verify_accepts_currently_valid_code(): void
    {
        $secret = $this->svc->generateSecret();
        $code   = $this->totpFor($secret, time());
        $this->assertTrue($this->svc->verifyCode($secret, $code),
            'a code generated for now() must verify');
    }

    public function test_verify_rejects_clearly_wrong_code(): void
    {
        $secret = $this->svc->generateSecret();
        $this->assertFalse($this->svc->verifyCode($secret, '000000'));
        $this->assertFalse($this->svc->verifyCode($secret, '123456'));
    }

    public function test_verify_rejects_non_six_digit_input(): void
    {
        $secret = $this->svc->generateSecret();
        $this->assertFalse($this->svc->verifyCode($secret, ''));
        $this->assertFalse($this->svc->verifyCode($secret, '12345'));      // too short
        $this->assertFalse($this->svc->verifyCode($secret, '1234567'));    // too long
        $this->assertFalse($this->svc->verifyCode($secret, 'abcdef'));     // letters
        $this->assertFalse($this->svc->verifyCode($secret, '12 34 56'));   // spaces stripped → 6 digits but stripped fn happens INSIDE; but pattern check passes
    }

    public function test_verify_accepts_previous_window_code_within_drift(): void
    {
        // Within ±1 step (default) → 30s before should still verify.
        // This guards against client clock drift killing legitimate logins.
        $secret = $this->svc->generateSecret();
        $past   = $this->totpFor($secret, time() - 30);
        $this->assertTrue($this->svc->verifyCode($secret, $past),
            'previous 30s window must still pass with default ±1 drift');
    }

    public function test_verify_rejects_far_past_code(): void
    {
        // Code 5 minutes old (10 windows) — way outside drift.
        $secret = $this->svc->generateSecret();
        $stale  = $this->totpFor($secret, time() - 300);
        $this->assertFalse($this->svc->verifyCode($secret, $stale),
            'a 5-minute-old code must not verify');
    }

    /* ───────────── enable / disable / isEnabled ───────────── */

    public function test_enable_then_isEnabled_round_trips(): void
    {
        $secret = $this->svc->generateSecret();

        $this->assertFalse($this->svc->isEnabled($this->admin->id),
            'fresh admin has no 2FA');

        $this->svc->enable($this->admin->id, $secret);

        $this->assertTrue($this->svc->isEnabled($this->admin->id));
        $this->assertSame($secret, $this->svc->getSecret($this->admin->id));
    }

    public function test_enable_is_idempotent_on_repeat(): void
    {
        $s1 = $this->svc->generateSecret();
        $s2 = $this->svc->generateSecret();

        $this->svc->enable($this->admin->id, $s1);
        $this->svc->enable($this->admin->id, $s2);

        // Latest enable() wins.
        $this->assertSame($s2, $this->svc->getSecret($this->admin->id));

        // Should not have created two rows.
        $rows = DB::table('admin_2fa')->where('admin_id', $this->admin->id)->count();
        $this->assertSame(1, $rows, 'unique(admin_id) prevents duplicate rows');
    }

    public function test_disable_clears_isEnabled(): void
    {
        $secret = $this->svc->generateSecret();
        $this->svc->enable($this->admin->id, $secret);
        $this->svc->disable($this->admin->id);

        $this->assertFalse($this->svc->isEnabled($this->admin->id));
    }

    /* ───────────── backup codes ───────────── */

    public function test_generate_backup_codes_returns_eight_unique_uppercase_hex(): void
    {
        $codes = $this->svc->generateBackupCodes();

        $this->assertCount(8, $codes);
        $this->assertCount(8, array_unique($codes), 'no duplicates');

        foreach ($codes as $c) {
            $this->assertMatchesRegularExpression('/^[A-F0-9]{8}$/', $c);
        }
    }

    public function test_save_and_verify_backup_code_works(): void
    {
        $secret = $this->svc->generateSecret();
        $this->svc->enable($this->admin->id, $secret);

        $codes = $this->svc->generateBackupCodes();
        $this->svc->saveBackupCodes($this->admin->id, $codes);

        $this->assertTrue($this->svc->verifyBackupCode($this->admin->id, $codes[0]),
            'a freshly saved code must verify');
    }

    public function test_backup_code_is_single_use(): void
    {
        $secret = $this->svc->generateSecret();
        $this->svc->enable($this->admin->id, $secret);

        $codes = $this->svc->generateBackupCodes();
        $this->svc->saveBackupCodes($this->admin->id, $codes);

        $this->assertTrue($this->svc->verifyBackupCode($this->admin->id, $codes[3]));
        $this->assertFalse($this->svc->verifyBackupCode($this->admin->id, $codes[3]),
            'replayed code must be rejected — security-critical');
    }

    public function test_backup_code_is_case_insensitive_on_input(): void
    {
        $secret = $this->svc->generateSecret();
        $this->svc->enable($this->admin->id, $secret);

        $codes = $this->svc->generateBackupCodes();
        $this->svc->saveBackupCodes($this->admin->id, $codes);

        // Service normalizes to uppercase before hashing/comparing.
        $this->assertTrue($this->svc->verifyBackupCode($this->admin->id, strtolower($codes[0])));
    }

    public function test_unknown_admin_backup_verify_returns_false(): void
    {
        $this->assertFalse($this->svc->verifyBackupCode(999_999, 'AAAAAAAA'));
    }

    /* ───────────── helpers ───────────── */

    /**
     * Generate a TOTP code matching the service's algorithm by reflecting
     * into the private generateTOTP() method. Lets us test the public
     * verifyCode() against codes we know are valid for arbitrary
     * timestamps (past/future/now).
     */
    private function totpFor(string $secret, int $unixTs): string
    {
        $ref = new \ReflectionClass(TwoFactorAuthService::class);
        $gen = $ref->getMethod('generateTOTP');
        $gen->setAccessible(true);
        return $gen->invoke($this->svc, $secret, (int) floor($unixTs / 30));
    }
}
