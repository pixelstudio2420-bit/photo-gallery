<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Invoice ledger for subscription periods.
 *
 * Each invoice represents one billing period. Invoice → Order links to
 * the generic orders/transactions system so we reuse all the existing
 * payment UI + webhook handling.
 */
class SubscriptionInvoice extends Model
{
    use HasFactory;

    public const STATUS_PENDING  = 'pending';
    public const STATUS_PAID     = 'paid';
    public const STATUS_FAILED   = 'failed';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_VOIDED   = 'voided';

    protected $fillable = [
        'subscription_id',
        'photographer_id',
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
        return $this->belongsTo(PhotographerSubscription::class, 'subscription_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * Generate a new, unique human-friendly invoice number.
     * Pattern: SUB-YYMMDD-XXXXXX (where X is uppercase hex).
     */
    public static function generateInvoiceNumber(): string
    {
        do {
            $candidate = 'SUB-'.now()->format('ymd').'-'.strtoupper(bin2hex(random_bytes(3)));
        } while (static::where('invoice_number', $candidate)->exists());

        return $candidate;
    }
}
