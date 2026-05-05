<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Photographer-initiated withdrawal request.
 *
 * State machine:
 *   pending  → admin queue (default on create)
 *   approved → admin acknowledged + intends to pay
 *   paid     → admin transferred + recorded slip (terminal)
 *   rejected → admin refused with rejection_reason (terminal)
 *   cancelled→ photographer cancelled before approval (terminal)
 *
 * See migration header for the full coexistence story with the
 * existing PhotographerDisbursement (auto cron-driven) flow.
 */
class WithdrawalRequest extends Model
{
    public const STATUS_PENDING   = 'pending';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_PAID      = 'paid';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    public const METHOD_BANK      = 'bank_transfer';
    public const METHOD_PROMPTPAY = 'promptpay';
    public const METHOD_OTHER     = 'other';

    protected $fillable = [
        'photographer_id',
        'amount_thb',
        'fee_thb',
        'net_thb',
        'method',
        'method_details',
        'status',
        'photographer_note',
        'admin_note',
        'rejection_reason',
        'payment_slip_url',
        'payment_reference',
        'reviewed_by_admin_id',
        'reviewed_at',
        'paid_at',
    ];

    protected $casts = [
        'amount_thb'     => 'decimal:2',
        'fee_thb'        => 'decimal:2',
        'net_thb'        => 'decimal:2',
        'method_details' => 'array',
        'reviewed_at'    => 'datetime',
        'paid_at'        => 'datetime',
    ];

    /* ──────────────── Relations ──────────────── */

    public function photographer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'photographer_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_admin_id');
    }

    /* ──────────────── Scopes ──────────────── */

    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_APPROVED);
    }

    public function scopePaid(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_PAID);
    }

    /** Active = not yet in a terminal state. Pending OR approved. */
    public function scopeActive(Builder $q): Builder
    {
        return $q->whereIn('status', [self::STATUS_PENDING, self::STATUS_APPROVED]);
    }

    /* ──────────────── Helpers ──────────────── */

    public function isPending(): bool   { return $this->status === self::STATUS_PENDING; }
    public function isApproved(): bool  { return $this->status === self::STATUS_APPROVED; }
    public function isPaid(): bool      { return $this->status === self::STATUS_PAID; }
    public function isRejected(): bool  { return $this->status === self::STATUS_REJECTED; }
    public function isCancelled(): bool { return $this->status === self::STATUS_CANCELLED; }

    /** Can the admin still act on this request? (i.e. not in a terminal state) */
    public function isActionable(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_APPROVED,
        ], true);
    }

    /** Can the photographer still cancel this themselves? */
    public function isCancellable(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /** Status pill colour token for the admin/photographer UI. */
    public function statusColor(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING   => 'amber',
            self::STATUS_APPROVED  => 'blue',
            self::STATUS_PAID      => 'emerald',
            self::STATUS_REJECTED  => 'rose',
            self::STATUS_CANCELLED => 'gray',
            default                => 'gray',
        };
    }

    /** Thai-language label for the status pill. */
    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING   => 'รอตรวจสอบ',
            self::STATUS_APPROVED  => 'อนุมัติแล้ว · รอโอน',
            self::STATUS_PAID      => 'โอนแล้ว',
            self::STATUS_REJECTED  => 'ปฏิเสธ',
            self::STATUS_CANCELLED => 'ยกเลิก',
            default                => $this->status,
        };
    }

    /** Pretty payment method label. */
    public function methodLabel(): string
    {
        return match ($this->method) {
            self::METHOD_BANK      => 'โอนธนาคาร',
            self::METHOD_PROMPTPAY => 'PromptPay',
            self::METHOD_OTHER     => 'อื่นๆ',
            default                => $this->method,
        };
    }
}
