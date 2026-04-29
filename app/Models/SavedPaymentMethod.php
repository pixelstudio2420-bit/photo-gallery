<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * SavedPaymentMethod — tokenized payment methods stored for a user.
 *
 * Sensitive provider tokens are hidden from array/JSON serialization.
 * Each user may have one default per provider/method type.
 */
class SavedPaymentMethod extends Model
{
    protected $table = 'saved_payment_methods';

    protected $fillable = [
        'user_id',
        'provider',
        'provider_customer_id',
        'provider_method_id',
        'method_type',
        'display_name',
        'last4',
        'brand',
        'exp_month',
        'exp_year',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active'  => 'boolean',
    ];

    /**
     * Sensitive provider identifiers — never expose in API/JSON responses.
     */
    protected $hidden = [
        'provider_method_id',
        'provider_customer_id',
    ];

    /* ──────────────────────────── Relations ──────────────────────────── */

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /* ──────────────────────────── Scopes ──────────────────────────── */

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeForUser($q, int $userId)
    {
        return $q->where('user_id', $userId);
    }

    public function scopeDefault($q)
    {
        return $q->where('is_default', true);
    }

    /* ──────────────────────────── Instance Methods ──────────────────────────── */

    /**
     * Promote this method to be the user's default, demoting any others.
     * Uses a transaction so both operations stay consistent.
     */
    public function setAsDefault(): void
    {
        DB::transaction(function () {
            static::where('user_id', $this->user_id)
                ->where('id', '!=', $this->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);

            $this->is_default = true;
            $this->save();
        });
    }

    /**
     * Card brand icon for UI (Bootstrap-icons style names).
     */
    public function getDisplayIconAttribute(): string
    {
        $brand = strtolower((string) $this->brand);

        return match (true) {
            str_contains($brand, 'visa')       => 'bi-credit-card-2-front',
            str_contains($brand, 'master')     => 'bi-credit-card-2-back',
            str_contains($brand, 'amex')
                || str_contains($brand, 'american') => 'bi-credit-card',
            str_contains($brand, 'jcb')        => 'bi-credit-card',
            str_contains($brand, 'unionpay')   => 'bi-credit-card',
            $this->method_type === 'promptpay' => 'bi-qr-code',
            $this->method_type === 'truemoney' => 'bi-wallet2',
            $this->method_type === 'bank'      => 'bi-bank',
            default                            => 'bi-credit-card',
        };
    }
}
