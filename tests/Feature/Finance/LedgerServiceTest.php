<?php

namespace Tests\Feature\Finance;

use App\Finance\ChartOfAccounts;
use App\Finance\Exceptions\UnbalancedJournalException;
use App\Finance\JournalDraft;
use App\Finance\LedgerService;
use App\Finance\Money;
use App\Finance\Models\FinancialAccount;
use App\Finance\Models\JournalEntry;
use App\Finance\Models\JournalLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LedgerServiceTest extends TestCase
{
    private ChartOfAccounts $chart;
    private LedgerService $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootSchema();
        $this->chart  = new ChartOfAccounts();
        $this->ledger = new LedgerService();
    }

    private function bootSchema(): void
    {
        if (!Schema::hasTable('financial_accounts')) {
            Schema::create('financial_accounts', function ($t) {
                $t->bigIncrements('id');
                $t->string('account_code', 64)->unique();
                $t->string('account_type', 16);
                $t->string('name', 128);
                $t->string('currency', 3)->default('THB');
                $t->string('owner_type', 32)->nullable();
                $t->unsignedBigInteger('owner_id')->nullable();
                $t->boolean('is_active')->default(true);
                $t->json('metadata')->nullable();
                $t->timestamps();
            });
        } else {
            DB::table('financial_accounts')->truncate();
        }
        if (!Schema::hasTable('financial_journal_entries')) {
            Schema::create('financial_journal_entries', function ($t) {
                $t->bigIncrements('id');
                $t->string('journal_uuid', 36)->unique();
                $t->string('type', 48);
                $t->text('description')->nullable();
                $t->string('idempotency_key', 128)->unique();
                $t->json('metadata')->nullable();
                $t->timestamp('posted_at');
                $t->string('posted_by', 64)->nullable();
                $t->unsignedBigInteger('reversed_by_id')->nullable();
                $t->timestamps();
            });
        } else {
            DB::table('financial_journal_entries')->truncate();
        }
        if (!Schema::hasTable('financial_journal_lines')) {
            Schema::create('financial_journal_lines', function ($t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('journal_entry_id');
                $t->unsignedBigInteger('account_id');
                $t->string('direction', 2);
                $t->unsignedBigInteger('amount_minor');
                $t->string('currency', 3);
                $t->timestamp('created_at');
            });
        } else {
            DB::table('financial_journal_lines')->truncate();
        }
        if (!Schema::hasTable('financial_balances')) {
            Schema::create('financial_balances', function ($t) {
                $t->unsignedBigInteger('account_id')->primary();
                $t->bigInteger('balance_minor')->default(0);
                $t->string('currency', 3);
                $t->timestamp('updated_at');
            });
        } else {
            DB::table('financial_balances')->truncate();
        }
    }

    /* ─────────────────── Balanced post + balance updates ─────────────────── */

    public function test_balanced_post_creates_entry_and_lines_and_balances(): void
    {
        $cash      = $this->chart->platform(ChartOfAccounts::PLATFORM_CASH);
        $revenue   = $this->chart->platform(ChartOfAccounts::COMMISSION_REVENUE);

        $draft = (new JournalDraft('test.simple', 'simple-1'))
            ->debit ($cash,    Money::thb(10_000))
            ->credit($revenue, Money::thb(10_000));

        $entry = $this->ledger->post($draft);

        $this->assertInstanceOf(JournalEntry::class, $entry);
        $this->assertSame(2, $entry->lines->count());

        // Cash is asset → DR 100 baht → balance +100 baht
        $this->assertSame(10_000, $this->ledger->balance($cash)->minor);
        // Revenue is revenue → CR 100 baht → normal-balance positive
        $this->assertSame(10_000, $this->ledger->balance($revenue)->minor);
    }

    public function test_unbalanced_post_throws(): void
    {
        $cash    = $this->chart->platform(ChartOfAccounts::PLATFORM_CASH);
        $revenue = $this->chart->platform(ChartOfAccounts::COMMISSION_REVENUE);

        $draft = (new JournalDraft('test.unbalanced', 'unbal-1'))
            ->debit ($cash,    Money::thb(100))
            ->credit($revenue, Money::thb(99));

        $this->expectException(UnbalancedJournalException::class);
        $this->ledger->post($draft);
    }

    public function test_zero_amount_line_rejected(): void
    {
        $cash = $this->chart->platform(ChartOfAccounts::PLATFORM_CASH);
        $this->expectException(\InvalidArgumentException::class);
        (new JournalDraft('test.zero', 'zero-1'))->debit($cash, Money::zero());
    }

    public function test_multi_currency_in_one_journal_rejected(): void
    {
        $cashThb = $this->chart->platform(ChartOfAccounts::PLATFORM_CASH, 'THB');
        $cashUsd = $this->chart->platform(ChartOfAccounts::PLATFORM_CASH, 'USD');

        $draft = (new JournalDraft('test.fx', 'fx-1'))
            ->debit ($cashThb, Money::thb(100))
            ->credit($cashUsd, new Money(100, 'USD'));

        $this->expectException(\InvalidArgumentException::class);
        $draft->assertBalanced();
    }

    /* ─────────────────── Idempotency ─────────────────── */

    public function test_repeated_post_with_same_idempotency_key_returns_existing(): void
    {
        $cash    = $this->chart->platform(ChartOfAccounts::PLATFORM_CASH);
        $revenue = $this->chart->platform(ChartOfAccounts::COMMISSION_REVENUE);

        $build = fn () => (new JournalDraft('test.idem', 'idem-key-1'))
            ->debit($cash, Money::thb(100))
            ->credit($revenue, Money::thb(100));

        $first  = $this->ledger->post($build());
        $second = $this->ledger->post($build());

        $this->assertSame($first->id, $second->id);
        // Only ONE entry total — no double-posting.
        $this->assertSame(1, JournalEntry::count());
        // Balance reflects ONE post, not two.
        $this->assertSame(100, $this->ledger->balance($cash)->minor);
    }

    /* ─────────────────── Reversal ─────────────────── */

    public function test_reversal_creates_mirror_entry_and_zeros_balance(): void
    {
        $cash    = $this->chart->platform(ChartOfAccounts::PLATFORM_CASH);
        $revenue = $this->chart->platform(ChartOfAccounts::COMMISSION_REVENUE);

        $original = $this->ledger->post(
            (new JournalDraft('test.original', 'orig-1'))
                ->debit($cash, Money::thb(500))
                ->credit($revenue, Money::thb(500))
        );

        $reversal = $this->ledger->reverse($original, 'orig-1.reversed', 'test reversal');

        $this->assertNotSame($original->id, $reversal->id);
        $this->assertSame($reversal->id, $original->fresh()->reversed_by_id);
        // Balance back to zero
        $this->assertSame(0, $this->ledger->balance($cash)->minor);
        $this->assertSame(0, $this->ledger->balance($revenue)->minor);
    }

    public function test_reversal_is_itself_idempotent(): void
    {
        $cash    = $this->chart->platform(ChartOfAccounts::PLATFORM_CASH);
        $revenue = $this->chart->platform(ChartOfAccounts::COMMISSION_REVENUE);

        $orig = $this->ledger->post(
            (new JournalDraft('t', 'orig-2'))
                ->debit($cash,    Money::thb(100))
                ->credit($revenue,Money::thb(100))
        );

        $r1 = $this->ledger->reverse($orig, 'orig-2.reversed');
        $r2 = $this->ledger->reverse($orig, 'orig-2.reversed');

        $this->assertSame($r1->id, $r2->id);  // same idempotency_key
        $this->assertSame(2, JournalEntry::count());  // original + reversal only
    }

    /* ─────────────────── Balance recompute ─────────────────── */

    public function test_recompute_balance_matches_cached_after_many_posts(): void
    {
        $cash    = $this->chart->platform(ChartOfAccounts::PLATFORM_CASH);
        $revenue = $this->chart->platform(ChartOfAccounts::COMMISSION_REVENUE);

        for ($i = 1; $i <= 20; $i++) {
            $this->ledger->post(
                (new JournalDraft('test.many', "many-{$i}"))
                    ->debit($cash, Money::thb($i * 10))
                    ->credit($revenue, Money::thb($i * 10))
            );
        }
        // 1+2+…+20 = 210 ; ×10 = 2100 satang? 210 * 10 = 2100. Yes.
        $expected = 2100;
        $this->assertSame($expected, $this->ledger->balance($cash)->minor);
        $this->assertSame($expected, $this->ledger->recomputeBalance($cash)->minor);
    }
}
