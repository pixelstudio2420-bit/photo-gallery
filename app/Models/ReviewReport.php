<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewReport extends Model
{
    protected $table = 'review_reports';
    public $timestamps = false;

    protected $fillable = [
        'review_id', 'user_id', 'reason', 'description',
        'status', 'resolved_by_admin_id', 'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'created_at'  => 'datetime',
    ];

    public const REASONS = [
        'spam'         => 'สแปม / ส่งซ้ำๆ',
        'offensive'    => 'ข้อความไม่เหมาะสม',
        'fake'         => 'รีวิวปลอม',
        'irrelevant'   => 'ไม่เกี่ยวข้องกับผลงาน',
        'private_info' => 'มีข้อมูลส่วนตัว',
        'other'        => 'อื่นๆ',
    ];

    public function review()
    {
        return $this->belongsTo(Review::class, 'review_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopePending($q)
    {
        return $q->where('status', 'pending');
    }

    public function getReasonLabelAttribute(): string
    {
        return self::REASONS[$this->reason] ?? $this->reason;
    }
}
