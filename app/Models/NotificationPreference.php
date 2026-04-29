<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    protected $table = 'notification_preferences';

    protected $fillable = [
        'user_id', 'type',
        'in_app_enabled', 'email_enabled', 'sms_enabled', 'push_enabled',
    ];

    protected $casts = [
        'in_app_enabled' => 'boolean',
        'email_enabled'  => 'boolean',
        'sms_enabled'    => 'boolean',
        'push_enabled'   => 'boolean',
    ];

    /**
     * All supported notification types with labels.
     */
    public const TYPES = [
        'order'               => ['label' => 'คำสั่งซื้อ',            'desc' => 'แจ้งเมื่อคุณสร้างคำสั่งซื้อ'],
        'payment_approved'    => ['label' => 'การชำระเงินอนุมัติ',     'desc' => 'แจ้งเมื่อการชำระเงินถูกอนุมัติ'],
        'payment_rejected'    => ['label' => 'การชำระเงินปฏิเสธ',      'desc' => 'แจ้งเมื่อสลิปโอนเงินไม่ผ่าน'],
        'download_ready'      => ['label' => 'พร้อมดาวน์โหลด',        'desc' => 'แจ้งเมื่อภาพพร้อมให้ดาวน์โหลด'],
        'refund'              => ['label' => 'การคืนเงิน',             'desc' => 'แจ้งเมื่อได้รับเงินคืน'],
        'new_sale'            => ['label' => 'ยอดขายใหม่',             'desc' => 'สำหรับช่างภาพ - แจ้งเมื่อมีการซื้อภาพ'],
        'payout'              => ['label' => 'โอนเงินรายได้',          'desc' => 'สำหรับช่างภาพ - แจ้งเมื่อโอนเงินเข้าบัญชี'],
        'review'              => ['label' => 'รีวิวใหม่',              'desc' => 'สำหรับช่างภาพ - แจ้งเมื่อมีรีวิวใหม่'],
        'photographer_approved' => ['label' => 'ช่างภาพอนุมัติ',       'desc' => 'แจ้งเมื่อบัญชีช่างภาพได้รับการอนุมัติ'],
        'contact'             => ['label' => 'ตอบกลับข้อความ',         'desc' => 'แจ้งเมื่อทีมงานตอบข้อความ'],
        'system'              => ['label' => 'ประกาศระบบ',             'desc' => 'ประกาศและข่าวสารจากทีมงาน'],
    ];

    /**
     * Get or create preference for a user + type.
     */
    public static function getOrCreate(int $userId, string $type): self
    {
        return static::firstOrCreate(
            ['user_id' => $userId, 'type' => $type],
            [
                'in_app_enabled' => true,
                'email_enabled'  => true,
                'sms_enabled'    => false,
                'push_enabled'   => true,
            ]
        );
    }

    /**
     * Get all preferences for a user (creates defaults for missing types).
     */
    public static function forUser(int $userId): array
    {
        $existing = static::where('user_id', $userId)
            ->get()
            ->keyBy('type');

        $result = [];
        foreach (self::TYPES as $type => $meta) {
            $pref = $existing->get($type);
            $result[$type] = [
                'type'           => $type,
                'label'          => $meta['label'],
                'desc'           => $meta['desc'],
                'in_app_enabled' => $pref ? (bool) $pref->in_app_enabled : true,
                'email_enabled'  => $pref ? (bool) $pref->email_enabled : true,
                'sms_enabled'    => $pref ? (bool) $pref->sms_enabled : false,
                'push_enabled'   => $pref ? (bool) $pref->push_enabled : true,
            ];
        }
        return $result;
    }

    /**
     * Check if a specific channel is enabled for user+type.
     */
    public static function isChannelEnabled(int $userId, string $type, string $channel): bool
    {
        $column = match ($channel) {
            'in_app' => 'in_app_enabled',
            'email'  => 'email_enabled',
            'sms'    => 'sms_enabled',
            'push'   => 'push_enabled',
            default  => null,
        };

        if (!$column) return true;

        try {
            $pref = static::where('user_id', $userId)->where('type', $type)->first();
            if (!$pref) return true; // Default enabled
            return (bool) $pref->$column;
        } catch (\Throwable $e) {
            return true;
        }
    }
}
