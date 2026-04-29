<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PaymentSlip extends Model
{
    protected $table = 'payment_slips';
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'transaction_id',
        'slip_path',
        'slip_hash',
        'amount',
        'transfer_date',
        'reference_code',
        'verify_status',
        'verify_score',
        'verified_by',
        'verified_at',
        'reject_reason',
        'note',
        'bank_account_id',
        // Enhanced verification fields
        'fraud_flags',
        'verify_breakdown',
        'slipok_trans_ref',
        'receiver_account',
        'receiver_name',
        'sender_name',
    ];

    protected $casts = [
        'amount'           => 'decimal:2',
        'transfer_date'    => 'datetime',
        'verified_at'      => 'datetime',
        'uploaded_at'      => 'datetime',
        'created_at'       => 'datetime',
        'fraud_flags'      => 'array',
        'verify_breakdown' => 'array',
    ];

    public function order()    { return $this->belongsTo(Order::class, 'order_id'); }
    public function verifier() { return $this->belongsTo(Admin::class, 'verified_by'); }

    /**
     * Public URL for the stored slip image, regardless of which driver it
     * lives on. Slips historically landed on the local `public` disk but
     * newer uploads go to R2 (under users/{user_id}/payment-slips/) — this
     * accessor hides that transition so admin & customer views don't have
     * to care. `resolveUrl()` probes the primary driver first and then
     * sweeps every enabled driver, so mixed-history rows display correctly.
     */
    public function getSlipUrlAttribute(): string
    {
        if (empty($this->slip_path)) return '';
        return app(\App\Services\StorageManager::class)->resolveUrl($this->slip_path);
    }

    /**
     * Delete the underlying slip image when the DB row is removed so admins
     * don't leave thousands of already-verified slips sitting on disk.
     * Slips may now live on any configured driver (public was the only
     * target before R2 rolled out) — so the sweep goes through
     * StorageManager::deleteAsset which walks every enabled driver. Missing
     * keys are silent no-ops, safe for the mixed-history fleet of rows.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $slip) {
            if (empty($slip->slip_path)) return;
            try {
                app(\App\Services\StorageManager::class)->deleteAsset($slip->slip_path);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning(
                    "PaymentSlip#{$slip->id} file delete failed: " . $e->getMessage()
                );
            }
        });
    }
}
