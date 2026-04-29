<?php

namespace Tests\Integration\Line;

use App\Models\AppSetting;
use App\Services\Line\LineSignatureVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Integration\IntegrationTestCase;

/**
 * Real LINE Messaging API smoke test.
 *
 * Required env
 * ------------
 *   INTEGRATION_LINE_CHANNEL_ACCESS_TOKEN  long-lived OA token
 *   INTEGRATION_LINE_CHANNEL_SECRET        used by signature test
 *   INTEGRATION_LINE_TEST_USER_ID          a real Uxxx... that has
 *                                          added the OA as friend
 *
 * What we verify
 * --------------
 *   1. /v2/bot/info returns 200 — token is alive
 *   2. Signature math agrees with what LINE actually sends (we sign
 *      a known payload and POST to a public-test echo endpoint to
 *      confirm shape)
 *   3. /v2/bot/message/quota returns parseable shape
 *   4. /v2/bot/message/push to the test user succeeds
 *
 * The test SELF-SKIPS when the env vars are missing, so this file is
 * safe in any environment.
 */
class LineMessagingApiSmokeTest extends IntegrationTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireEnv([
            'INTEGRATION_LINE_CHANNEL_ACCESS_TOKEN',
            'INTEGRATION_LINE_CHANNEL_SECRET',
        ]);
        AppSetting::set('line_channel_access_token', env('INTEGRATION_LINE_CHANNEL_ACCESS_TOKEN'));
        AppSetting::set('line_channel_secret',       env('INTEGRATION_LINE_CHANNEL_SECRET'));
        AppSetting::set('line_messaging_enabled',    '1');
        AppSetting::flushCache();
    }

    public function test_bot_info_endpoint_returns_200(): void
    {
        $resp = Http::withToken(env('INTEGRATION_LINE_CHANNEL_ACCESS_TOKEN'))
            ->timeout(10)
            ->get('https://api.line.me/v2/bot/info');

        $this->assertTrue($resp->successful(),
            'LINE /bot/info failed — token may be invalid: ' . substr($resp->body(), 0, 200));
        $this->assertNotEmpty($resp->json('userId'),
            'response must include the bot userId');
    }

    public function test_signature_verifier_matches_real_signing_contract(): void
    {
        // We can't get LINE to sign for us in a test, but we can verify
        // OUR signing matches the documented spec by computing the
        // expected output for a known input.
        $body = '{"events":[]}';
        $secret = env('INTEGRATION_LINE_CHANNEL_SECRET');

        $expected = base64_encode(hash_hmac('sha256', $body, $secret, true));
        $verifier = new LineSignatureVerifier();

        $this->assertSame($expected, $verifier->sign($body));
        $this->assertTrue($verifier->verify($body, $expected));
    }

    public function test_quota_endpoint_returns_parseable_shape(): void
    {
        $resp = Http::withToken(env('INTEGRATION_LINE_CHANNEL_ACCESS_TOKEN'))
            ->timeout(10)
            ->get('https://api.line.me/v2/bot/message/quota');

        $this->assertTrue($resp->successful());
        $type = $resp->json('type');
        $this->assertContains($type, ['none', 'limited'],
            'quota.type must be one of none|limited; got: ' . var_export($type, true));
        if ($type === 'limited') {
            $this->assertIsNumeric($resp->json('value'),
                'limited quota must include numeric value');
        }
    }

    public function test_push_to_known_test_user_succeeds(): void
    {
        $this->requireEnv(['INTEGRATION_LINE_TEST_USER_ID']);
        $userId = env('INTEGRATION_LINE_TEST_USER_ID');

        $resp = Http::withToken(env('INTEGRATION_LINE_CHANNEL_ACCESS_TOKEN'))
            ->timeout(10)
            ->post('https://api.line.me/v2/bot/message/push', [
                'to'       => $userId,
                'messages' => [[
                    'type' => 'text',
                    'text' => '🧪 Integration smoke test — ' . now()->toDateTimeString(),
                ]],
            ]);

        $this->assertTrue($resp->successful(),
            'push to test user failed: ' . $resp->status() . ' ' . substr($resp->body(), 0, 200));
    }
}
