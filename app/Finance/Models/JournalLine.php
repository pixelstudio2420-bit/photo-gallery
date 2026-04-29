<?php

namespace App\Finance\Models;

use App\Finance\Money;
use Illuminate\Database\Eloquent\Model;

class JournalLine extends Model
{
    public const DEBIT  = 'DR';
    public const CREDIT = 'CR';

    public $timestamps = false;

    protected $table = 'financial_journal_lines';

    protected $fillable = [
        'journal_entry_id', 'account_id',
        'direction', 'amount_minor', 'currency',
        'created_at',
    ];

    protected $casts = [
        'amount_minor' => 'int',
        'created_at'   => 'datetime',
    ];

    public function money(): Money
    {
        return new Money((int) $this->amount_minor, (string) $this->currency);
    }

    public function isDebit(): bool  { return $this->direction === self::DEBIT; }
    public function isCredit(): bool { return $this->direction === self::CREDIT; }
}
