<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * OrderStatusHistory — immutable audit trail of every status change on an order.
 *
 * Stores who made the change (admin/user/system/gateway), the resulting status,
 * a human description, and any context in meta.
 */
class OrderStatusHistory extends Model
{
    protected $table = 'order_status_history';
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'status',
        'description',
        'changed_by_admin_id',
        'changed_by_user_id',
        'actor_name',
        'source',
        'meta',
    ];

    protected $casts = [
        'meta'       => 'array',
        'created_at' => 'datetime',
    ];

    /* ──────────────────────────── Relations ──────────────────────────── */

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'changed_by_admin_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }

    /* ──────────────────────────── Presentation Accessors ──────────────────────────── */

    /**
     * Bootstrap icon name matching the status.
     */
    public function getIconAttribute(): string
    {
        return match ($this->status) {
            'pending'           => 'clock',
            'paid'              => 'check-circle',
            'cancelled'         => 'x-circle',
            'refunded'          => 'arrow-counterclockwise',
            'completed'         => 'check2-all',
            'shipped'           => 'box',
            'failed'            => 'exclamation-triangle',
            'processing'        => 'hourglass-split',
            'under_review'      => 'eye',
            'approved'          => 'check-circle-fill',
            'rejected'          => 'x-octagon',
            default             => 'info-circle',
        };
    }

    /**
     * Tailwind-compatible color token for UI rendering.
     */
    public function getColorAttribute(): string
    {
        return match ($this->status) {
            'pending'           => 'amber',
            'paid'              => 'emerald',
            'cancelled'         => 'gray',
            'refunded'          => 'purple',
            'completed'         => 'green',
            'shipped'           => 'blue',
            'failed'            => 'red',
            'processing'        => 'indigo',
            'under_review'      => 'sky',
            'approved'          => 'emerald',
            'rejected'          => 'red',
            default             => 'slate',
        };
    }
}
