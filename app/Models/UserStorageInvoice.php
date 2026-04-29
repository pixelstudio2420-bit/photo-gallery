<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Invoice ledger for the consumer storage system — one row per
 * billing period per subscription.
 *
 * Invoice → Order links to the shared orders/payment-transactions
 * tables so we reuse checkout, webhook, and transaction logging
 * without a parallel pipeline.
 */
class UserStorageInvoice extends Model
{
    use HasFactory;

    protected $table = 'user_storage_invoices';

    public const STATUS_PENDING  = 'pending';
    public const STATUS_PAID     = 'paid';
    public const STATUS_FAILED   = 'failed';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_VOIDED   = 'voided';

    protected $fillable = [
        'subscription_id',
        'user_id',
        'order_id',
        'invoice_number',
        'period_start',
        'period_end',
        'amount_thb',
        'status',
        'paid_at',
        'failed_at',
        'failure_reason',
        'meta',
    ];

    protected $casts = [
        'period_start' => 'datetime',
        'period_end'   => 'datetime',
        'amount_thb'   => 'decimal:2',
        'paid_at'      => 'datetime',
        'failed_at'    => 'datetime',
        'meta'         => 'array',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(UserStorageSubscription::class, 'subscription_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * Unique invoice number in "STR-YYMMDD-XXXXXX" format (upper hex tail).
     */
    public static function generateInvoiceNumber(): string
    {
        do {
            $candidate = 'STR-'.now()->format('ymd').'-'.strtoupper(bin2hex(random_bytes(3)));
        } while (static::where('invoice_number', $candidate)->exists());

        return $candidate;
    }
}
