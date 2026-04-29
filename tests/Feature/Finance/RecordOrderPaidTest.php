<?php

namespace Tests\Feature\Finance;

use App\Finance\Calculators\CommissionCalculator;
use App\Finance\Calculators\TaxCalculator;
use App\Finance\ChartOfAccounts;
use App\Finance\LedgerService;
use App\Finance\Money;
use App\Finance\UseCases\RecordOrderPaid;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * End-to-end test of the calculation engine via RecordOrderPaid.
 *
 *   gross_paid_by_customer
 *     ├─ commission revenue (platform fee)
 *     ├─ photographer payable (their share)
 *     └─ vat collected payable (when vat>0)
 */
class RecordOrderPaidTest extends TestCase
{
    private RecordOrderPaid $useCase;
    private LedgerService $ledger;
    private ChartOfAccounts $chart;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootSchema();
        $this->ledger  = new LedgerService();
        $this->chart   = new ChartOfAccounts();
        $this->useCase = new RecordOrderPaid(
            $this->ledger,
            $this->chart,
            new CommissionCalculator(),
            new TaxCalculator(),
        );
    }

    private function bootSchema(): void
    {
        // Same schema as LedgerServiceTest — kept inline so the file
        // is self-contained for the prune-broken-DB workaround.
        foreach (['financial_journal_lines', 'financial_balances', 'financial_journal_entries', 'financial_accounts'] as $tbl) {
            if (Schema::hasTable($tbl)) DB::table($tbl)->truncate();
        }
        if (!Schema::hasTable('financial_accounts')) {
            Schema::create('financial_accounts', function ($t) {
                $t->bigIncrements('id'); $t->string('account_code', 64)->unique(); $t->string('account_type', 16);
                $t->string('name', 128); $t->string('currency', 3)->default('THB');
                $t->string('owner_type', 32)->nullable(); $t->unsignedBigInteger('owner_id')->nullable();
                $t->boolean('is_active')->default(true); $t->json('metadata')->nullable(); $t->timestamps();
            });
        }
        if (!Schema::hasTable('financial_journal_entries')) {
            Schema::create('financial_journal_entries', function ($t) {
                $t->bigIncrements('id'); $t->string('journal_uuid', 36)->unique(); $t->string('type', 48);
                $t->text('description')->nullable(); $t->string('idempotency_key', 128)->unique();
                $t->json('metadata')->nullable(); $t->timestamp('posted_at'); $t->string('posted_by', 64)->nullable();
                $t->unsignedBigInteger('reversed_by_id')->nullable(); $t->timestamps();
            });
        }
        if (!Schema::hasTable('financial_journal_lines')) {
            Schema::create('financial_journal_lines', function ($t) {
                $t->bigIncrements('id'); $t->unsignedBigInteger('journal_entry_id'); $t->unsignedBigInteger('account_id');
                $t->string('direction', 2); $t->unsignedBigInteger('amount_minor'); $t->string('currency', 3); $t->timestamp('created_at');
            });
        }
        if (!Schema::hasTable('financial_balances')) {
            Schema::create('financial_balances', function ($t) {
                $t->unsignedBigInteger('account_id')->primary(); $t->bigInteger('balance_minor')->default(0);
                $t->string('currency', 3); $t->timestamp('updated_at');
            });
        }
    }

    public function test_paid_order_with_vat_and_commission_balances_to_the_satang(): void
    {
        // ฿1,070 paid (VAT-inclusive), 7% VAT, 20% platform commission, photographer #42
        $this->useCase->record(
            orderId:             'ord-1',
            grossPaid:           Money::thb(107_000),
            platformFeeBps:      2000,
            vatBps:              700,
            photographerUserId:  42,
            gatewayCode:         ChartOfAccounts::OMISE_RECEIVABLE,
        );

        // Expected math:
        //   inclusive: gross=107000, net=intdiv(107000*10000+(10700/2),10700)=intdiv(1070000000+5350,10700)
        //                 = intdiv(1070005350, 10700) = 100000 (exact)
        //   tax = 7000
        //   commission = 100000 * 2000/10000 = 20000
        //   photographer = 100000 - 20000 = 80000
        $this->assertBalanceMatches(ChartOfAccounts::OMISE_RECEIVABLE,    107_000);
        $this->assertBalanceMatches(ChartOfAccounts::COMMISSION_REVENUE,   20_000);
        $this->assertBalanceMatches(ChartOfAccounts::VAT_COLLECTED_PAYABLE, 7_000);

        // Photographer payable
        $photographerPayable = $this->chart->photographerPayable(42);
        $this->assertSame(80_000, $this->ledger->balance($photographerPayable)->minor);

        // CRITICAL: ledger balance — sum of DR == sum of CR
        $totalDr = (int) DB::table('financial_journal_lines')->where('direction', 'DR')->sum('amount_minor');
        $totalCr = (int) DB::table('financial_journal_lines')->where('direction', 'CR')->sum('amount_minor');
        $this->assertSame($totalDr, $totalCr, 'Ledger must balance: sum(DR) == sum(CR)');
    }

    public function test_paid_order_without_vat_omits_vat_line(): void
    {
        $this->useCase->record(
            orderId:             'ord-no-vat',
            grossPaid:           Money::thb(100_000),
            platformFeeBps:      2000,
            vatBps:              0,                // no VAT
            photographerUserId:  77,
            gatewayCode:         ChartOfAccounts::STRIPE_RECEIVABLE,
        );

        $this->assertSame(0, $this->ledger->balance(
            $this->chart->platform(ChartOfAccounts::VAT_COLLECTED_PAYABLE)
        )->minor, 'VAT account must not be touched when vatBps=0');

        $this->assertSame(20_000, $this->ledger->balance(
            $this->chart->platform(ChartOfAccounts::COMMISSION_REVENUE)
        )->minor);

        $this->assertSame(80_000, $this->ledger->balance(
            $this->chart->photographerPayable(77)
        )->minor);
    }

    public function test_webhook_retry_does_not_double_post(): void
    {
        // First call posts. Second call (e.g. webhook retry) returns the
        // existing journal silently — net effect on the ledger is one post.
        for ($i = 0; $i < 3; $i++) {
            $this->useCase->record(
                orderId:             'ord-retry',
                grossPaid:           Money::thb(50_000),
                platformFeeBps:      2000,
                vatBps:              700,
                photographerUserId:  99,
                gatewayCode:         ChartOfAccounts::OMISE_RECEIVABLE,
            );
        }

        $this->assertSame(50_000, $this->ledger->balance(
            $this->chart->platform(ChartOfAccounts::OMISE_RECEIVABLE)
        )->minor, 'Three webhook retries must result in ONE posted entry');

        $this->assertSame(1, DB::table('financial_journal_entries')->count());
    }

    public function test_awkward_rounding_amount_still_balances(): void
    {
        // ฿123.45 with 7% VAT, 20% commission — every step might round.
        // But sum(DR) == sum(CR) MUST still hold.
        $this->useCase->record(
            orderId:             'ord-awk',
            grossPaid:           Money::thb(12_345),
            platformFeeBps:      2000,
            vatBps:              700,
            photographerUserId:  42,
            gatewayCode:         ChartOfAccounts::OMISE_RECEIVABLE,
        );

        $totalDr = (int) DB::table('financial_journal_lines')->where('direction', 'DR')->sum('amount_minor');
        $totalCr = (int) DB::table('financial_journal_lines')->where('direction', 'CR')->sum('amount_minor');
        $this->assertSame($totalDr, $totalCr);
        $this->assertSame(12_345, $totalDr, 'Total ledger movement must equal gross paid');
    }

    private function assertBalanceMatches(string $code, int $expectedMinor): void
    {
        $account = $this->chart->platform($code);
        $this->assertSame($expectedMinor, $this->ledger->balance($account)->minor,
            "Balance mismatch on {$code}: expected {$expectedMinor} satang");
    }
}
