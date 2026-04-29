<?php

namespace App\Finance\Exceptions;

use App\Finance\Money;
use RuntimeException;

class UnbalancedJournalException extends RuntimeException
{
    public static function debitsAndCreditsDiffer(Money $debits, Money $credits): self
    {
        return new self(sprintf(
            'Journal is unbalanced: debits=%s vs credits=%s (diff=%s satang). '
            . 'Every journal entry MUST satisfy SUM(DR)==SUM(CR).',
            $debits->toMajorString(),
            $credits->toMajorString(),
            $debits->minor - $credits->minor,
        ));
    }
}
