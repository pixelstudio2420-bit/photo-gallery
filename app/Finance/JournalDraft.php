<?php

namespace App\Finance;

use App\Finance\Exceptions\UnbalancedJournalException;
use App\Finance\Models\FinancialAccount;
use App\Finance\Models\JournalLine;
use InvalidArgumentException;

/**
 * A staged journal entry — caller builds this, hands it to LedgerService::post().
 *
 * Why a draft instead of just calling post() with primitives?
 *   - Lets us validate balance BEFORE the DB transaction starts
 *   - Lets callers compose drafts, e.g. "build the order.paid draft, then add a tax line, then post"
 *   - Keeps the post() signature small + auditable
 */
final class JournalDraft
{
    /** @var array<int, array{account: FinancialAccount, direction: string, amount: Money}> */
    private array $lines = [];

    public function __construct(
        public readonly string $type,
        public readonly string $idempotencyKey,
        public readonly ?string $description = null,
        public readonly ?string $postedBy = null,
        public readonly array $metadata = [],
    ) {
        if ($type === '' || strlen($type) > 48) {
            throw new InvalidArgumentException('JournalDraft.type must be 1..48 chars');
        }
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 128) {
            throw new InvalidArgumentException('JournalDraft.idempotencyKey must be 1..128 chars');
        }
    }

    public function debit(FinancialAccount $account, Money $amount): self
    {
        return $this->addLine($account, JournalLine::DEBIT, $amount);
    }

    public function credit(FinancialAccount $account, Money $amount): self
    {
        return $this->addLine($account, JournalLine::CREDIT, $amount);
    }

    private function addLine(FinancialAccount $account, string $direction, Money $amount): self
    {
        if ($amount->isNegative()) {
            throw new InvalidArgumentException(
                'Journal line amounts must be non-negative. '
                . 'Use direction=CR to record a credit; direction=DR for a debit.'
            );
        }
        if ($amount->isZero()) {
            // Zero-amount lines are harmless but pollute the ledger. Refuse.
            throw new InvalidArgumentException('Journal line amount cannot be zero');
        }
        if ($account->currency !== $amount->currency) {
            throw new InvalidArgumentException(sprintf(
                'Account %s is in %s but line amount is in %s',
                $account->account_code, $account->currency, $amount->currency,
            ));
        }
        $this->lines[] = [
            'account'   => $account,
            'direction' => $direction,
            'amount'    => $amount,
        ];
        return $this;
    }

    /** @return array<int, array{account: FinancialAccount, direction: string, amount: Money}> */
    public function lines(): array
    {
        return $this->lines;
    }

    /**
     * Verify SUM(DR) == SUM(CR) and all lines share a currency.
     * Throws UnbalancedJournalException if not.
     */
    public function assertBalanced(): void
    {
        if (empty($this->lines)) {
            throw new InvalidArgumentException('Journal has no lines');
        }

        $currency = $this->lines[0]['amount']->currency;
        $debits   = Money::zero($currency);
        $credits  = Money::zero($currency);

        foreach ($this->lines as $line) {
            if ($line['amount']->currency !== $currency) {
                // We DON'T support multi-currency journals — every line
                // must share a currency. FX is a separate journal type.
                throw new InvalidArgumentException(sprintf(
                    'Multi-currency journal not supported (mixed %s and %s)',
                    $currency, $line['amount']->currency,
                ));
            }
            if ($line['direction'] === JournalLine::DEBIT) {
                $debits = $debits->plus($line['amount']);
            } else {
                $credits = $credits->plus($line['amount']);
            }
        }

        if (!$debits->equals($credits)) {
            throw UnbalancedJournalException::debitsAndCreditsDiffer($debits, $credits);
        }
    }
}
