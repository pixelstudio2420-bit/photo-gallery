<?php

namespace Tests\Feature\Payout;

use App\Services\Payout\PromptPayValidator;
use Tests\TestCase;

/**
 * Format + checksum guarantees for PromptPay identifiers.
 *
 * The validator is the gate for what gets stored on
 * photographer_profiles.promptpay_number. If a malformed value sneaks
 * past, the disbursement engine eventually fails or — worse — sends
 * money to the wrong recipient. So we test:
 *
 *   • Mobile (10-digit starting 0) → normalises to 66XXXXXXXXX.
 *   • NID (13-digit) → checksum-validated; transpositions caught.
 *   • Junk (letters, wrong length, missing leading 0) → rejected.
 *   • Decoration (dashes, spaces, +66 prefix) → tolerated, normalised.
 */
class PromptPayValidatorTest extends TestCase
{
    private PromptPayValidator $v;

    protected function setUp(): void
    {
        parent::setUp();
        $this->v = new PromptPayValidator();
    }

    /* ───────────────── Mobile ───────────────── */

    public function test_valid_mobile_normalises_to_66_prefix(): void
    {
        $r = $this->v->validate('0812345678');
        $this->assertTrue($r['valid']);
        $this->assertSame('mobile', $r['type']);
        $this->assertSame('66812345678', $r['normalised']);
    }

    public function test_mobile_with_dashes_normalises(): void
    {
        $r = $this->v->validate('081-234-5678');
        $this->assertTrue($r['valid']);
        $this->assertSame('66812345678', $r['normalised']);
    }

    public function test_mobile_with_spaces_normalises(): void
    {
        $r = $this->v->validate(' 081 234 5678 ');
        $this->assertTrue($r['valid']);
        $this->assertSame('66812345678', $r['normalised']);
    }

    public function test_mobile_with_plus_66_prefix_normalised(): void
    {
        // Paste from bank app: "+66 81 234 5678" → digits-only is
        // "66812345678" (11 chars starting with 66) — already in the
        // PromptPay canonical form, accept verbatim.
        $r = $this->v->validate('+66 81 234 5678');
        $this->assertTrue($r['valid']);
        $this->assertSame('66812345678', $r['normalised']);
    }

    public function test_mobile_too_short_rejected(): void
    {
        $r = $this->v->validate('081234567');
        $this->assertFalse($r['valid']);
    }

    public function test_mobile_missing_leading_zero_rejected(): void
    {
        $r = $this->v->validate('8123456789');
        $this->assertFalse($r['valid']);
    }

    /* ───────────────── NID ───────────────── */

    /**
     * Generate a valid NID by computing the checksum digit. Used so we
     * don't hard-code a real-person NID into a public test fixture.
     */
    private function makeValidNid(string $first12): string
    {
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $first12[$i] * (13 - $i);
        }
        $check = (11 - ($sum % 11)) % 10;
        return $first12 . $check;
    }

    public function test_valid_nid_passes_checksum(): void
    {
        $nid = $this->makeValidNid('110123456789');
        $r = $this->v->validate($nid);
        $this->assertTrue($r['valid']);
        $this->assertSame('nid', $r['type']);
        $this->assertSame($nid, $r['normalised']);
    }

    public function test_nid_with_dashes_normalises(): void
    {
        $nid = $this->makeValidNid('110123456789');
        // Standard Thai NID display format: 1-1234-56789-01-2
        $decorated = $nid[0] . '-' . substr($nid, 1, 4) . '-' . substr($nid, 5, 5) . '-'
                   . substr($nid, 10, 2) . '-' . $nid[12];
        $r = $this->v->validate($decorated);
        $this->assertTrue($r['valid']);
        $this->assertSame($nid, $r['normalised']);
    }

    public function test_nid_failed_checksum_rejected(): void
    {
        $nid = $this->makeValidNid('110123456789');
        // Flip the last digit — checksum now wrong
        $broken = substr($nid, 0, 12) . (((int) $nid[12] + 1) % 10);
        $r = $this->v->validate($broken);
        $this->assertFalse($r['valid']);
        $this->assertContains('nid_checksum_failed', $r['errors']);
    }

    public function test_nid_transposition_caught_by_checksum(): void
    {
        $nid = $this->makeValidNid('110123456789');
        // Swap two adjacent digits — almost always invalidates checksum
        $bad = $nid[0] . $nid[2] . $nid[1] . substr($nid, 3);
        $r = $this->v->validate($bad);
        $this->assertFalse($r['valid'],
            'Adjacent-digit transposition (the most common typo) must be caught.');
    }

    /* ───────────────── Junk inputs ───────────────── */

    public function test_empty_string_rejected(): void
    {
        $this->assertFalse($this->v->validate('')['valid']);
        $this->assertFalse($this->v->validate(null)['valid']);
        $this->assertFalse($this->v->validate('   ')['valid']);
    }

    public function test_letters_rejected(): void
    {
        $this->assertFalse($this->v->validate('not-a-number')['valid']);
    }

    public function test_wrong_length_rejected(): void
    {
        $this->assertFalse($this->v->validate('12345')['valid']);
        $this->assertFalse($this->v->validate('12345678901234567')['valid']);
    }

    /* ───────────────── isValid convenience ───────────────── */

    public function test_is_valid_convenience(): void
    {
        $this->assertTrue($this->v->isValid('0812345678'));
        $this->assertFalse($this->v->isValid('garbage'));
    }
}
