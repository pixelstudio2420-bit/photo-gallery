<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * RefundRequest — customer-submitted refund request with an admin review workflow.
 *
 * Lifecycle: pending -> under_review -> approved|rejected
 *                                    -> processing -> completed
 *                                    -> cancelled (by customer)
 */
class RefundRequest extends Model
{
    protected $table = 'refund_requests';

    protected $fillable = [
        'request_number',
        'order_id',
        'user_id',
        'requested_amount',
        'reason',
        'description',
        'attachments',
        'status',
        'admin_note',
        'rejection_reason',
        'approved_amount',
        'reviewed_by_admin_id',
        'reviewed_at',
        'resolved_at',
        'payment_refund_id',
    ];

    protected $casts = [
        'attachments'      => 'array',
        'requested_amount' => 'decimal:2',
        'approved_amount'  => 'decimal:2',
        'reviewed_at'      => 'datetime',
        'resolved_at'      => 'datetime',
    ];

    /* ──────────────────────────── Constants ──────────────────────────── */

    const REASONS = [
        'wrong_order'      => 'สั่งผิด / ไม่ต้องการแล้ว',
        'duplicate_charge' => 'โดนเรียกเก็บซ้ำ',
        'poor_quality'     => 'คุณภาพไม่ดี',
        'not_as_described' => 'ไม่ตรงกับที่ระบุไว้',
        'never_received'   => 'ไม่ได้รับสินค้า',
        'other'            => 'เหตุผลอื่น',
    ];

    const STATUSES = [
        'pending'       => 'รอพิจารณา',
        'under_review'  => 'กำลังตรวจสอบ',
        'approved'      => 'อนุมัติแล้ว',
        'rejected'      => 'ปฏิเสธ',
        'processing'    => 'กำลังดำเนินการคืนเงิน',
        'completed'     => 'คืนเงินสำเร็จ',
        'cancelled'     => 'ยกเลิกแล้ว',
    ];

    /* ──────────────────────────── Relations ──────────────────────────── */

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewedByAdmin()
    {
        return $this->belongsTo(Admin::class, 'reviewed_by_admin_id');
    }

    public function paymentRefund()
    {
        return $this->belongsTo(PaymentRefund::class, 'payment_refund_id');
    }

    /* ──────────────────────────── Scopes ──────────────────────────── */

    public function scopePending($q)
    {
        return $q->whereIn('status', ['pending', 'under_review']);
    }

    public function scopeApproved($q)
    {
        return $q->where('status', 'approved');
    }

    public function scopeRejected($q)
    {
        return $q->where('status', 'rejected');
    }

    public function scopeForUser($q, int $userId)
    {
        return $q->where('user_id', $userId);
    }

    /* ──────────────────────────── Static Helpers ──────────────────────────── */

    /**
     * Generate a unique request number in the format REF-YYYYMMDD-XXXX.
     */
    public static function generateRequestNumber(): string
    {
        $date = now()->format('Ymd');

        do {
            $sequence = str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            $number   = "REF-{$date}-{$sequence}";
        } while (static::where('request_number', $number)->exists());

        return $number;
    }

    /* ──────────────────────────── Presentation ──────────────────────────── */

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getReasonLabelAttribute(): string
    {
        return self::REASONS[$this->reason] ?? $this->reason;
    }

    /**
     * Tailwind color token for UI badge rendering.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending'      => 'amber',
            'under_review' => 'sky',
            'approved'     => 'emerald',
            'rejected'     => 'red',
            'processing'   => 'indigo',
            'completed'    => 'green',
            'cancelled'    => 'gray',
            default        => 'slate',
        };
    }

    /* ──────────────────────────── Business Rules ──────────────────────────── */

    /**
     * A customer may only cancel their request while it's still under review.
     */
    public function canBeCancelledByUser(): bool
    {
        return in_array($this->status, ['pending', 'under_review'], true);
    }

    /**
     * An admin may review only pending/under_review requests.
     */
    public function canBeReviewed(): bool
    {
        return in_array($this->status, ['pending', 'under_review'], true);
    }
}
