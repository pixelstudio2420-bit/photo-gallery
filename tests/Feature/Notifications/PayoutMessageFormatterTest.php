<?php

namespace Tests\Feature\Notifications;

use App\Models\PhotographerDisbursement;
use App\Models\PhotographerProfile;
use App\Models\User;
use App\Services\Notifications\PayoutMessage;
use App\Services\Notifications\PayoutMessageFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Locks down the canonical wording of payout notifications.
 *
 * The formatter is the SINGLE source of truth — every channel
 * (in-app, LINE, email) builds its message from this output. These
 * tests pin down: same numbers across channels, same headline text,
 * stable currency formatting, correct branch on success vs failure
 * vs name-mismatch, and the flex bubble structure (LINE rich UI).
 *
 * If you find yourself updating these tests because copy changed,
 * make sure you update them in ONE place — the assertions deliberately
 * encode what the user sees.
 */
class PayoutMessageFormatterTest extends TestCase
{
    use RefreshDatabase;

    private PayoutMessageFormatter $f;

    protected function setUp(): void
    {
        parent::setUp();
        $this->f = new PayoutMessageFormatter();
    }

    private function makeDisbursement(array $overrides = []): PhotographerDisbursement
    {
        $user = User::create([
            'first_name'    => 'Pay',
            'last_name'     => 'Tester',
            'email'         => 'payout-' . uniqid() . '@test.local',
            'password_hash' => Hash::make('p'),
            'auth_provider' => 'local',
        ]);
        PhotographerProfile::create([
            'user_id'             => $user->id,
            'photographer_code'   => 'PH-T' . substr(uniqid(), -6),
            'display_name'        => 'Test Studio',
            'commission_rate'     => 80,
            'status'              => 'approved',
            'tier'                => 'pro',
            'bank_name'           => 'SCB',
            'bank_account_number' => '1234567890',
            'bank_account_name'   => 'Test Studio',
        ]);
        $defaults = [
            'photographer_id' => $user->id,
            'amount_thb'      => 1234.56,
            'payout_count'    => 3,
            'provider'        => 'mock',
            'idempotency_key' => 'test-' . uniqid(),
            'provider_txn_id' => 'TXN-ABC123',
            'status'          => PhotographerDisbursement::STATUS_SUCCEEDED,
            'trigger_type'    => PhotographerDisbursement::TRIGGER_SCHEDULE,
            'settled_at'      => now(),
        ];
        return PhotographerDisbursement::create(array_merge($defaults, $overrides));
    }

    /* ───────────────── Currency formatting ───────────────── */

    public function test_money_format_uses_two_decimals_and_nbsp(): void
    {
        $this->assertSame("฿\xC2\xA01,234.56", $this->f->money(1234.56));
        $this->assertSame("฿\xC2\xA00.00",     $this->f->money(0));
        $this->assertSame("฿\xC2\xA01,000.00", $this->f->money(1000));
        $this->assertSame("฿\xC2\xA01,234,567.89", $this->f->money(1234567.89));
    }

    public function test_money_uses_nbsp_so_symbol_does_not_wrap(): void
    {
        $formatted = $this->f->money(500);
        // The character between ฿ and 5 must be U+00A0 (non-breaking),
        // not a regular space — otherwise chat clients can wrap "฿" to a
        // separate line on narrow screens, which looks broken.
        $this->assertStringContainsString("\xC2\xA0", $formatted);
        $this->assertStringNotContainsString('฿ 5', $formatted, 'must use nbsp not regular space');
    }

    /* ───────────────── Success message ───────────────── */

    public function test_success_message_kind_and_headline(): void
    {
        $d = $this->makeDisbursement();
        $m = $this->f->success($d);

        $this->assertSame(PayoutMessage::KIND_SUCCESS, $m->kind);
        $this->assertTrue($m->isSuccess());
        $this->assertFalse($m->isFailure());
        $this->assertStringContainsString('โอนแล้ว', $m->headline);
        $this->assertStringContainsString('✅', $m->headline);
    }

    public function test_success_amount_appears_consistent_across_fields(): void
    {
        $d = $this->makeDisbursement(['amount_thb' => 7777.50]);
        $m = $this->f->success($d);

        $expected = "฿\xC2\xA07,777.50";
        $this->assertSame($expected, $m->amount);
        $this->assertStringContainsString($expected, $m->shortBody);
        $this->assertStringContainsString($expected, $m->body);
        $this->assertStringContainsString($expected, $m->subject);
        // The flex bubble's amount text must match too — that's the LINE
        // rich UI rendering of the same number.
        $headerTexts = array_map(
            fn ($el) => $el['text'] ?? '',
            $m->flexBubble['header']['contents'] ?? [],
        );
        $this->assertContains($expected, $headerTexts,
            'Flex bubble header amount must match the rest of the message.');
    }

    public function test_success_includes_account_last4_in_bullets(): void
    {
        $d = $this->makeDisbursement();
        $m = $this->f->success($d);

        // bank_account_number = '1234567890' → last 4 = '7890'
        $accountBullet = collect($m->bullets)->first(fn ($b) => str_contains($b, 'เข้าบัญชี'));
        $this->assertNotNull($accountBullet);
        $this->assertStringContainsString('SCB', $accountBullet);
        $this->assertStringContainsString('7890', $accountBullet);
    }

    public function test_success_includes_provider_txn_id_when_present(): void
    {
        $d = $this->makeDisbursement(['provider_txn_id' => 'TXN-XYZ-999']);
        $m = $this->f->success($d);

        $refBullet = collect($m->bullets)->first(fn ($b) => str_contains($b, 'อ้างอิง'));
        $this->assertStringContainsString('TXN-XYZ-999', $refBullet);
    }

    public function test_success_omits_txn_id_bullet_when_absent(): void
    {
        $d = $this->makeDisbursement(['provider_txn_id' => null]);
        $m = $this->f->success($d);

        $hasRefBullet = collect($m->bullets)->contains(fn ($b) => str_contains($b, 'อ้างอิง'));
        $this->assertFalse($hasRefBullet,
            'When the provider didn\'t return a txn id, we don\'t fabricate one.');
    }

    public function test_success_cta_points_to_earnings_page(): void
    {
        $d = $this->makeDisbursement();
        $m = $this->f->success($d);

        $this->assertStringEndsWith('/photographer/earnings', $m->cta['url']);
        $this->assertSame('ดูสรุปรายได้', $m->cta['label']);
    }

    /* ───────────────── Failure (generic) ───────────────── */

    public function test_failure_message_kind_and_headline(): void
    {
        $d = $this->makeDisbursement(['status' => PhotographerDisbursement::STATUS_FAILED]);
        $m = $this->f->failure($d, errorMessage: 'gateway timeout');

        $this->assertSame(PayoutMessage::KIND_FAILURE_GENERIC, $m->kind);
        $this->assertFalse($m->isSuccess());
        $this->assertTrue($m->isFailure());
        $this->assertStringContainsString('⚠️', $m->headline);
    }

    public function test_failure_includes_error_message_in_body(): void
    {
        $d = $this->makeDisbursement(['status' => PhotographerDisbursement::STATUS_FAILED]);
        $m = $this->f->failure($d, errorMessage: 'omise: insufficient balance');

        $this->assertStringContainsString('omise: insufficient balance', $m->body);
    }

    /* ───────────────── Failure (name mismatch) ───────────────── */

    public function test_name_mismatch_uses_distinct_kind_and_cta(): void
    {
        $d = $this->makeDisbursement(['status' => PhotographerDisbursement::STATUS_FAILED]);
        $m = $this->f->failure($d, nameMismatch: true);

        $this->assertSame(PayoutMessage::KIND_FAILURE_NAME, $m->kind);
        $this->assertStringContainsString('ชื่อบัญชี', $m->headline);
        $this->assertStringEndsWith('/photographer/profile/setup-bank', $m->cta['url']);
        $this->assertSame('แก้ไขชื่อบัญชี', $m->cta['label']);
    }

    public function test_name_mismatch_does_not_say_will_retry_automatically(): void
    {
        // Critical: photographers must understand they need to ACT here.
        // The generic failure says "we'll retry"; the name-mismatch must
        // NOT say that, otherwise photographers wait passively forever.
        $d = $this->makeDisbursement(['status' => PhotographerDisbursement::STATUS_FAILED]);
        $m = $this->f->failure($d, nameMismatch: true);

        $this->assertStringNotContainsString('ระบบจะลองโอนใหม่อัตโนมัติในรอบถัดไป', $m->body,
            'Name-mismatch must NOT promise auto-retry — photographer has to fix the name.');
        $this->assertStringContainsString('แก้ไขชื่อบัญชี', $m->body);
    }

    /* ───────────────── Cross-channel consistency ───────────────── */

    public function test_subject_contains_amount_and_headline_keyword(): void
    {
        $d = $this->makeDisbursement(['amount_thb' => 500.00]);
        $m = $this->f->success($d);

        $this->assertStringContainsString("฿\xC2\xA0500.00", $m->subject);
        $this->assertStringContainsString('โอน', $m->subject);
    }

    public function test_plain_text_includes_all_key_elements(): void
    {
        $d = $this->makeDisbursement();
        $m = $this->f->success($d);
        $text = $m->plainText();

        $this->assertStringContainsString($m->headline, $text,
            'plainText must surface the headline so LINE-text fallbacks read fully.');
        $this->assertStringContainsString($m->amount, $text);
        // CTA URL must be in the plain-text body so users on rich-message-
        // disabled channels can still click through.
        $this->assertStringContainsString($m->cta['url'], $text);
    }

    public function test_flex_bubble_has_required_blocks(): void
    {
        $d = $this->makeDisbursement();
        $m = $this->f->success($d);
        $bubble = $m->flexBubble;

        $this->assertSame('bubble', $bubble['type']);
        $this->assertArrayHasKey('header', $bubble);
        $this->assertArrayHasKey('body', $bubble);
        $this->assertArrayHasKey('footer', $bubble);
        // Footer must contain the CTA action so LINE renders the button.
        $action = $bubble['footer']['contents'][0]['action'] ?? [];
        $this->assertSame('uri', $action['type']);
        $this->assertSame($m->cta['url'], $action['uri']);
    }

    public function test_failure_flex_uses_warning_color(): void
    {
        $d = $this->makeDisbursement(['status' => PhotographerDisbursement::STATUS_FAILED]);
        $generic = $this->f->failure($d);
        $name    = $this->f->failure($d, nameMismatch: true);

        // Generic: amber. Name-mismatch: red (more urgent — needs action).
        $this->assertSame('#f59e0b', $generic->flexBubble['header']['backgroundColor']);
        $this->assertSame('#dc2626', $name->flexBubble['header']['backgroundColor']);
    }
}
