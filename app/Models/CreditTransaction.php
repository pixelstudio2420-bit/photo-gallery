<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only ledger for every credit movement.
 *
 * Rules:
 *  • Never UPDATE or DELETE rows.
 *  • `delta` is signed: +N for grants, -N for consumes. Invariant:
 *    sum(delta WHERE photographer_id=X) == photographer's current balance.
 *  • `balance_after` is a snapshot captured at write time — we don't
 *    recompute it on read. Makes the photographer history view a single
 *    query without SUM() aggregation.
 *  • `meta` is the escape hatch for per-kind context (admin note, refund
 *    reason, UTM attribution, etc.) — use sparingly to keep SQL simple.
 */
class CreditTransaction extends Model
{
    use HasFactory;

    public $timestamps = false; // only created_at, set manually

    protected $table = 'credit_transactions';

    protected $fillable = [
        'photographer_id',
        'bundle_id',
        'kind',
        'delta',
        'balance_after',
        'reference_type',
        'reference_id',
        'meta',
        'actor_user_id',
        'created_at',
    ];

    protected $casts = [
        'photographer_id' => 'integer',
        'bundle_id'       => 'integer',
        'delta'           => 'integer',
        'balance_after'   => 'integer',
        'actor_user_id'   => 'integer',
        'meta'            => 'array',
        'created_at'      => 'datetime',
    ];

    // Known kinds — keep in sync with admin UI filter dropdown + seed data.
    public const KIND_PURCHASE = 'purchase';
    public const KIND_CONSUME  = 'consume';
    public const KIND_REFUND   = 'refund';
    public const KIND_GRANT    = 'grant';
    public const KIND_EXPIRE   = 'expire';
    public const KIND_ADJUST   = 'adjust';
    public const KIND_BONUS    = 'bonus';

    public static function allKinds(): array
    {
        return [
            self::KIND_PURCHASE, self::KIND_CONSUME, self::KIND_REFUND,
            self::KIND_GRANT, self::KIND_EXPIRE, self::KIND_ADJUST, self::KIND_BONUS,
        ];
    }

    public function photographer()
    {
        return $this->belongsTo(\App\Models\User::class, 'photographer_id');
    }

    public function bundle()
    {
        return $this->belongsTo(PhotographerCreditBundle::class, 'bundle_id');
    }

    public function actor()
    {
        return $this->belongsTo(\App\Models\User::class, 'actor_user_id');
    }

    /** Localised label for UI (Thai fallback). */
    public function getKindLabelAttribute(): string
    {
        return match ($this->kind) {
            self::KIND_PURCHASE => 'ซื้อแพ็คเก็จ',
            self::KIND_CONSUME  => 'ใช้งาน (อัปโหลด)',
            self::KIND_REFUND   => 'คืนเครดิต',
            self::KIND_GRANT    => 'Admin แจกฟรี',
            self::KIND_EXPIRE   => 'หมดอายุ',
            self::KIND_ADJUST   => 'ปรับยอด',
            self::KIND_BONUS    => 'โบนัส',
            default             => (string) $this->kind,
        };
    }
}
