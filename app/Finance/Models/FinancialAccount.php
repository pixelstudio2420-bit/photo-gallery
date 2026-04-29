<?php

namespace App\Finance\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialAccount extends Model
{
    public const TYPE_ASSET     = 'asset';
    public const TYPE_LIABILITY = 'liability';
    public const TYPE_EQUITY    = 'equity';
    public const TYPE_REVENUE   = 'revenue';
    public const TYPE_EXPENSE   = 'expense';

    protected $table = 'financial_accounts';

    protected $fillable = [
        'account_code', 'account_type', 'name',
        'currency', 'owner_type', 'owner_id',
        'is_active', 'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata'  => 'array',
        'owner_id'  => 'int',
    ];

    /**
     * Normal balance side. Asset/Expense accounts have a debit normal
     * balance; Liability/Equity/Revenue accounts have a credit normal
     * balance. Used by LedgerService::balance() to compute the signed
     * balance the way humans expect to see it.
     */
    public function normalBalanceDirection(): string
    {
        return match ($this->account_type) {
            self::TYPE_ASSET, self::TYPE_EXPENSE => 'DR',
            default                              => 'CR',
        };
    }
}
