<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * AbandonedCart — tracks carts that users have abandoned for recovery campaigns.
 *
 * Lifecycle: pending -> reminded_1 -> reminded_2 -> recovered|expired
 */
class AbandonedCart extends Model
{
    protected $table = 'abandoned_carts';

    protected $fillable = [
        'user_id',
        'email',
        'session_id',
        'items',
        'item_count',
        'estimated_total',
        'last_activity_at',
        'recovery_status',
        'first_reminder_at',
        'second_reminder_at',
        'recovered_at',
        'recovered_order_id',
        'recovery_token',
    ];

    protected $casts = [
        'items'              => 'array',
        'estimated_total'    => 'decimal:2',
        'last_activity_at'   => 'datetime',
        'first_reminder_at'  => 'datetime',
        'second_reminder_at' => 'datetime',
        'recovered_at'       => 'datetime',
    ];

    /* ──────────────────────────── Relations ──────────────────────────── */

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function recoveredOrder()
    {
        return $this->belongsTo(Order::class, 'recovered_order_id');
    }

    /* ──────────────────────────── Scopes ──────────────────────────── */

    public function scopePending($q)
    {
        return $q->where('recovery_status', 'pending');
    }

    /**
     * Carts eligible for the 1st reminder: pending, last activity > 1h ago,
     * and no reminder has been sent yet.
     */
    public function scopeEligibleForReminder1($q)
    {
        return $q->where('recovery_status', 'pending')
            ->where('last_activity_at', '<=', now()->subHour())
            ->whereNull('first_reminder_at');
    }

    /**
     * Carts eligible for the 2nd reminder: already reminded once,
     * and first reminder was sent > 24h ago.
     */
    public function scopeEligibleForReminder2($q)
    {
        return $q->where('recovery_status', 'reminded_1')
            ->whereNotNull('first_reminder_at')
            ->where('first_reminder_at', '<=', now()->subHours(24));
    }

    /**
     * Carts that should be marked expired: reminded twice & over 7 days old.
     */
    public function scopeExpired($q)
    {
        return $q->where('recovery_status', 'reminded_2')
            ->whereNotNull('second_reminder_at')
            ->where('second_reminder_at', '<=', now()->subDays(7));
    }

    /* ──────────────────────────── Static Factory ──────────────────────────── */

    /**
     * Find an existing cart by session_id+email (or user_id) and update it,
     * otherwise create a new one. Returns the persisted record.
     */
    public static function createOrUpdate(array $data): self
    {
        $query = static::query();

        // Prefer user_id match first if supplied, else fall back to session+email
        if (!empty($data['user_id'])) {
            $query->where('user_id', $data['user_id']);
        } elseif (!empty($data['session_id'])) {
            $query->where('session_id', $data['session_id']);
            if (!empty($data['email'])) {
                $query->where('email', $data['email']);
            }
        } elseif (!empty($data['email'])) {
            $query->where('email', $data['email'])->whereNull('user_id');
        } else {
            // Nothing to match on — create fresh
            $cart = new self();
            $cart->fill($data);
            $cart->recovery_token = $cart->generateRecoveryToken();
            $cart->last_activity_at = now();
            $cart->recovery_status = $data['recovery_status'] ?? 'pending';
            $cart->save();
            return $cart;
        }

        // Only match active (not recovered/expired) rows when updating
        $query->whereIn('recovery_status', ['pending', 'reminded_1', 'reminded_2']);

        $cart = $query->first();

        if ($cart) {
            $cart->fill($data);
            $cart->last_activity_at = now();
            if (empty($cart->recovery_token)) {
                $cart->recovery_token = $cart->generateRecoveryToken();
            }
            $cart->save();
            return $cart;
        }

        $cart = new self();
        $cart->fill($data);
        $cart->recovery_token = $cart->generateRecoveryToken();
        $cart->last_activity_at = now();
        $cart->recovery_status = $data['recovery_status'] ?? 'pending';
        $cart->save();

        return $cart;
    }

    /* ──────────────────────────── Instance Methods ──────────────────────────── */

    /**
     * Generate a 64-character recovery token.
     */
    public function generateRecoveryToken(): string
    {
        do {
            $token = Str::random(64);
        } while (static::where('recovery_token', $token)->exists());

        return $token;
    }

    /**
     * Full public URL for the customer to recover their cart.
     */
    public function getRecoveryUrl(): string
    {
        return url('/cart/recover/' . $this->recovery_token);
    }

    /**
     * Mark this cart as recovered by the given order.
     */
    public function markRecovered(int $orderId): void
    {
        $this->update([
            'recovery_status'     => 'recovered',
            'recovered_at'        => now(),
            'recovered_order_id'  => $orderId,
        ]);
    }

    /**
     * Mark this cart as expired — no further recovery attempts.
     */
    public function markExpired(): void
    {
        $this->update([
            'recovery_status' => 'expired',
        ]);
    }
}
