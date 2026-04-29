<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataExportRequest extends Model
{
    protected $fillable = [
        'user_id', 'request_type', 'status', 'reason', 'admin_note',
        'file_path', 'file_disk', 'file_size_bytes', 'download_token',
        'expires_at', 'processed_at', 'processed_by',
    ];

    protected $casts = [
        'expires_at'    => 'datetime',
        'processed_at'  => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'processed_by');
    }

    public static function types(): array
    {
        return [
            'export' => 'ขอสำเนาข้อมูล',
            'delete' => 'ขอลบข้อมูล',
        ];
    }

    public static function statuses(): array
    {
        return [
            'pending'    => 'รออนุมัติ',
            'processing' => 'กำลังดำเนินการ',
            'ready'      => 'พร้อมดาวน์โหลด',
            'rejected'   => 'ปฏิเสธ',
            'cancelled'  => 'ยกเลิกแล้ว',
        ];
    }

    public function isReady(): bool
    {
        return $this->status === 'ready' && $this->file_path && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
