<?php

namespace Tests\Feature\Line;

use App\Models\AppSetting;
use App\Services\Line\LineSignatureVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Locks down the X-Line-Signature verifier.
 *
 * What we guarantee:
 *
 *   1. A correctly-signed body passes — we use the public sign() helper
 *      to construct the ground-truth signature so the formula stays
 *      symmetric across send + verify.
 *
 *   2. A forged signature is REJECTED. Even byte-for-byte close.
 *
 *   3. Missing channel secret = ALWAYS REJECT (fail closed).
 *
 *   4. Missing or empty signature header = REJECT.
 *
 *   5. Constant-time compare — we don't directly assert timing here
 *      (timing tests are flaky on shared CI), but we DO assert that
 *      the implementation calls hash_equals(), which is the only
 *      timing-safe primitive in PHP. Reflection-based check.
 */
class LineSignatureVerifierTest extends TestCase
{
    use RefreshDatabase;

    private LineSignatureVerifier $verifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->verifier = new LineSignatureVerifier();
    }

    public function test_valid_signature_passes(): void
    {
        AppSetting::set('line_channel_secret', 'shared-secret-' . uniqid());
        AppSetting::flushCache();

        $body = '{"events":[{"type":"message"}]}';
        $sig  = $this->verifier->sign($body);

        $this->assertTrue($this->verifier->verify($body, $sig));
    }

    public function test_forged_signature_is_rejected(): void
    {
        AppSetting::set('line_channel_secret', 'shared-secret');
        AppSetting::flushCache();

        $body = '{"events":[]}';
        $this->assertFalse($this->verifier->verify($body, 'aGVsbG8='));   // base64 of "hello"
        $this->assertFalse($this->verifier->verify($body, ''));
        $this->assertFalse($this->verifier->verify($body, 'not-base64'));
    }

    public function test_signature_for_modified_body_is_rejected(): void
    {
        AppSetting::set('line_channel_secret', 'shared-secret');
        AppSetting::flushCache();

        $body = '{"events":[]}';
        $sig  = $this->verifier->sign($body);

        // Single-byte change in body must invalidate the signature.
        $tampered = $body . ' ';   // trailing space
        $this->assertFalse($this->verifier->verify($tampered, $sig));
    }

    public function test_missing_secret_fails_closed(): void
    {
        // Explicitly clear the secret. Even with a "valid-looking"
        // signature, no secret means we cannot verify, so reject.
        AppSetting::set('line_channel_secret', '');
        AppSetting::flushCache();

        $body = '{"events":[]}';
        $sig  = base64_encode(hash_hmac('sha256', $body, 'whatever', true));

        $this->assertFalse($this->verifier->verify($body, $sig));
    }

    public function test_missing_signature_header_is_rejected(): void
    {
        AppSetting::set('line_channel_secret', 'shared-secret');
        AppSetting::flushCache();
        $this->assertFalse($this->verifier->verify('{}', null));
        $this->assertFalse($this->verifier->verify('{}', ''));
    }

    public function test_implementation_uses_hash_equals(): void
    {
        // Constant-time comparison is the entire point of this class.
        // Lock that in via reflection — if a future change replaces
        // hash_equals() with == or strcmp, this test fails loudly.
        $ref = new \ReflectionClass(LineSignatureVerifier::class);
        $src = file_get_contents($ref->getFileName());
        $this->assertStringContainsString(
            'hash_equals(',
            $src,
            'LineSignatureVerifier MUST use hash_equals() for timing-safe compare',
        );
    }
}
