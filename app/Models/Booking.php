<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Booking — customer reservation of a photographer's time for a future shoot.
 *
 * Differs from `Order` (post-shoot photo purchase) and `Event` (gallery/album
 * the photographer manages after the shoot). A booking can spawn an Event
 * later when the photographer creates the gallery — see `event_id`.
 */
class Booking extends Model
{
    public const STATUS_PENDING   = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_NO_SHOW   = 'no_show';

    public const CANCELLED_BY_CUSTOMER     = 'customer';
    public const CANCELLED_BY_PHOTOGRAPHER = 'photographer';
    public const CANCELLED_BY_ADMIN        = 'admin';

    protected $fillable = [
        'customer_user_id', 'photographer_id', 'event_id',
        'title', 'description',
        'scheduled_at', 'duration_minutes',
        'location', 'location_lat', 'location_lng',
        'package_name', 'expected_photos', 'agreed_price', 'deposit_paid',
        'customer_phone', 'customer_notes', 'photographer_notes',
        'status', 'cancellation_reason', 'cancelled_by',
        'cancelled_at', 'confirmed_at', 'completed_at',
        'reminder_3d_sent_at', 'reminder_1d_sent_at',
        'reminder_1h_sent_at', 'reminder_day_sent_at',
        'post_shoot_review_sent_at',
        // Phase 2 columns
        'is_waitlist', 'waitlist_for_id', 'promoted_from_waitlist_at',
        'deposit_required_pct', 'deposit_paid_at', 'deposit_payment_id',
        'gcal_event_id', 'gcal_synced_at',
        'admin_id', 'admin_notes',
        // Phase 3 (booking integrity & integration audit)
        'idempotency_key', 'deposit_idempotency_key',
        // Phase 4 (recurrence + tz support)
        'series_id', 'timezone',
    ];

    protected $casts = [
        'scheduled_at'                => 'datetime',
        'cancelled_at'                => 'datetime',
        'confirmed_at'                => 'datetime',
        'completed_at'                => 'datetime',
        'reminder_3d_sent_at'         => 'datetime',
        'reminder_1d_sent_at'         => 'datetime',
        'reminder_1h_sent_at'         => 'datetime',
        'reminder_day_sent_at'        => 'datetime',
        'post_shoot_review_sent_at'   => 'datetime',
        'promoted_from_waitlist_at'   => 'datetime',
        'deposit_paid_at'             => 'datetime',
        'gcal_synced_at'              => 'datetime',
        'agreed_price'                => 'decimal:2',
        'deposit_paid'                => 'decimal:2',
        'location_lat'                => 'decimal:7',
        'location_lng'                => 'decimal:7',
        'is_waitlist'                 => 'boolean',
    ];

    /**
     * In-PHP attribute defaults. The DB also has DEFAULT 0 for is_waitlist,
     * but a freshly Booking::create() instance won't refresh from DB so the
     * attribute would be unset → null → the boolean cast skips null.
     * Setting it here guarantees `$booking->is_waitlist === false` even
     * before a refresh.
     */
    protected $attributes = [
        'is_waitlist'          => false,
        'deposit_required_pct' => 0,
        'deposit_paid'         => 0,
    ];

    // ─────────────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────────────

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    public function photographer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'photographer_id');
    }

    public function photographerProfile()
    {
        return $this->belongsTo(PhotographerProfile::class, 'photographer_id', 'user_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Status helpers
    // ─────────────────────────────────────────────────────────────────────

    public function isPending(): bool   { return $this->status === self::STATUS_PENDING; }
    public function isConfirmed(): bool { return $this->status === self::STATUS_CONFIRMED; }
    public function isCompleted(): bool { return $this->status === self::STATUS_COMPLETED; }
    public function isCancelled(): bool { return in_array($this->status, [self::STATUS_CANCELLED, self::STATUS_NO_SHOW], true); }
    public function isUpcoming(): bool  { return $this->scheduled_at && $this->scheduled_at->isFuture() && !$this->isCancelled(); }
    public function isPast(): bool      { return $this->scheduled_at && $this->scheduled_at->isPast(); }
    public function isToday(): bool     { return $this->scheduled_at && $this->scheduled_at->isToday(); }

    /**
     * Calendar event color — keys to FullCalendar's color prop.
     * Drives the visual hint at-a-glance on the photographer dashboard.
     */
    public function getColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING   => '#f59e0b', // amber — needs action
            self::STATUS_CONFIRMED => '#10b981', // emerald — booked
            self::STATUS_COMPLETED => '#6366f1', // indigo — done
            self::STATUS_CANCELLED => '#ef4444', // red
            self::STATUS_NO_SHOW   => '#6b7280', // gray
            default                => '#94a3b8',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING   => 'รอยืนยัน',
            self::STATUS_CONFIRMED => 'ยืนยันแล้ว',
            self::STATUS_COMPLETED => 'เสร็จสิ้น',
            self::STATUS_CANCELLED => 'ยกเลิก',
            self::STATUS_NO_SHOW   => 'ไม่มาตามนัด',
            default                => $this->status,
        };
    }

    // ─────────────────────────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────────────────────────

    public function scopeForPhotographer($query, int $photographerId)
    {
        return $query->where('photographer_id', $photographerId);
    }

    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_user_id', $customerId);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('scheduled_at', '>=', now())
                     ->whereIn('status', [self::STATUS_PENDING, self::STATUS_CONFIRMED]);
    }

    public function scopePast($query)
    {
        return $query->where('scheduled_at', '<', now());
    }

    /**
     * Detect time-window overlap with another booking for the same photographer.
     * Used by BookingService to prevent double-booking on confirm.
     */
    public function scopeOverlapping($query, int $photographerId, $startsAt, $endsAt, ?int $excludeId = null)
    {
        $startsAt = $startsAt instanceof \Carbon\Carbon ? $startsAt : \Carbon\Carbon::parse($startsAt);
        $endsAt   = $endsAt   instanceof \Carbon\Carbon ? $endsAt   : \Carbon\Carbon::parse($endsAt);

        // "scheduled_at + duration_minutes minutes" expressed in each driver's
        // dialect. Two intervals overlap when start < their end AND end > their start.
        $driver = \Illuminate\Support\Facades\DB::connection()->getDriverName();
        $endExpr = match ($driver) {
            // Postgres: native interval arithmetic (composite literal).
            'pgsql'  => "scheduled_at + (duration_minutes || ' minutes')::interval",
            // MySQL/MariaDB: DATE_ADD with INTERVAL keyword.
            'mysql', 'mariadb' => 'DATE_ADD(scheduled_at, INTERVAL duration_minutes MINUTE)',
            // SQLite: datetime() with the +N minutes modifier built from the column.
            //   datetime(scheduled_at, '+' || duration_minutes || ' minutes')
            'sqlite' => "datetime(scheduled_at, '+' || duration_minutes || ' minutes')",
            default  => "scheduled_at",   // fallback — fail to "always overlap" rather than crash
        };

        return $query->where('photographer_id', $photographerId)
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_CONFIRMED])
            ->where(function ($q) use ($startsAt, $endsAt, $endExpr) {
                $q->where(function ($qq) use ($startsAt, $endsAt, $endExpr) {
                    $qq->where('scheduled_at', '<', $endsAt)
                       ->whereRaw("{$endExpr} > ?", [$startsAt]);
                });
            })
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId));
    }

    /**
     * Returns the end time of this booking (start + duration).
     */
    public function getEndsAtAttribute(): ?\Carbon\Carbon
    {
        if (!$this->scheduled_at) return null;
        return $this->scheduled_at->copy()->addMinutes((int) ($this->duration_minutes ?? 120));
    }

    // ─────────────────────────────────────────────────────────────────────
    // Phase 2: Deposit + Waitlist + GCal helpers
    // ─────────────────────────────────────────────────────────────────────

    /** Required deposit amount in baht (0 if no deposit gating). */
    public function getDepositAmountAttribute(): float
    {
        if (!$this->agreed_price || !$this->deposit_required_pct) return 0;
        return round((float) $this->agreed_price * (int) $this->deposit_required_pct / 100, 2);
    }

    public function getDepositRemainingAttribute(): float
    {
        return max(0, $this->deposit_amount - (float) $this->deposit_paid);
    }

    public function isDepositRequired(): bool
    {
        return $this->deposit_required_pct > 0 && $this->agreed_price > 0;
    }

    public function isDepositPaid(): bool
    {
        return $this->deposit_paid_at !== null
            || (!$this->isDepositRequired())
            || ((float) $this->deposit_paid >= $this->deposit_amount);
    }

    public function scopeWaitlist($query)
    {
        return $query->where('is_waitlist', true);
    }

    public function scopePrimary($query)
    {
        return $query->where('is_waitlist', false);
    }

    public function scopeNeedingGcalSync($query)
    {
        return $query->whereNull('gcal_event_id')
                     ->whereIn('status', [self::STATUS_CONFIRMED, self::STATUS_COMPLETED]);
    }
}
