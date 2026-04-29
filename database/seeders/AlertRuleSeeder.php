<?php

namespace Database\Seeders;

use App\Models\AlertRule;
use Illuminate\Database\Seeder;

class AlertRuleSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            [
                'name'             => 'Disk ใกล้เต็ม',
                'description'      => 'แจ้งเตือนเมื่อพื้นที่ดิสก์ถูกใช้เกิน 85%',
                'metric'           => 'disk_used_pct',
                'operator'         => '>',
                'threshold'        => 85,
                'channels'         => ['admin', 'email'],
                'severity'         => 'critical',
                'cooldown_minutes' => 180,
                'is_active'        => true,
            ],
            [
                'name'             => 'CPU สูงต่อเนื่อง',
                'description'      => 'แจ้งเตือนเมื่อ CPU load เกิน 85%',
                'metric'           => 'cpu_pct',
                'operator'         => '>',
                'threshold'        => 85,
                'channels'         => ['admin'],
                'severity'         => 'warn',
                'cooldown_minutes' => 30,
                'is_active'        => true,
            ],
            [
                'name'             => 'RAM ใกล้เต็ม',
                'description'      => 'PHP process ใช้ RAM เกิน 80% ของ limit',
                'metric'           => 'memory_pct',
                'operator'         => '>',
                'threshold'        => 80,
                'channels'         => ['admin'],
                'severity'         => 'warn',
                'cooldown_minutes' => 60,
                'is_active'        => true,
            ],
            [
                'name'             => 'DB Connections ใกล้เต็ม',
                'description'      => 'Connection ถูกใช้เกิน 75% ของ max',
                'metric'           => 'db_connections_pct',
                'operator'         => '>',
                'threshold'        => 75,
                'channels'         => ['admin', 'email'],
                'severity'         => 'critical',
                'cooldown_minutes' => 30,
                'is_active'        => true,
            ],
            [
                'name'             => 'Queue ค้างเยอะ',
                'description'      => 'มี job รออยู่ใน queue เกิน 200 ตัว',
                'metric'           => 'queue_pending',
                'operator'         => '>',
                'threshold'        => 200,
                'channels'         => ['admin'],
                'severity'         => 'warn',
                'cooldown_minutes' => 30,
                'is_active'        => true,
            ],
            [
                'name'             => 'Failed Jobs ใน 24 ชั่วโมง',
                'description'      => 'Job ล้มเหลวใน 24 ชั่วโมงเกิน 50 ตัว',
                'metric'           => 'queue_failed_24h',
                'operator'         => '>',
                'threshold'        => 50,
                'channels'         => ['admin', 'email'],
                'severity'         => 'warn',
                'cooldown_minutes' => 240,
                'is_active'        => true,
            ],
            [
                'name'             => 'Capacity ใกล้ชน',
                'description'      => 'ผู้ใช้ออนไลน์เกิน 80% ของ safe concurrent',
                'metric'           => 'capacity_util_pct',
                'operator'         => '>',
                'threshold'        => 80,
                'channels'         => ['admin', 'line'],
                'severity'         => 'critical',
                'cooldown_minutes' => 15,
                'is_active'        => true,
            ],
            [
                'name'             => 'สลิปค้างรอตรวจ',
                'description'      => 'มีสลิปรอตรวจสอบเกิน 20 ใบ',
                'metric'           => 'pending_slips',
                'operator'         => '>',
                'threshold'        => 20,
                'channels'         => ['admin'],
                'severity'         => 'info',
                'cooldown_minutes' => 120,
                'is_active'        => true,
            ],
            [
                'name'             => 'ออเดอร์รอยืนยัน',
                'description'      => 'มีออเดอร์รอยืนยันเกิน 15 รายการ',
                'metric'           => 'pending_orders',
                'operator'         => '>',
                'threshold'        => 15,
                'channels'         => ['admin'],
                'severity'         => 'info',
                'cooldown_minutes' => 180,
                'is_active'        => true,
            ],
            [
                'name'             => 'รูปถูก flag รอตรวจ',
                'description'      => 'มีรูปที่ถูกปักธง/รอตรวจเกิน 30 รูป',
                'metric'           => 'flagged_photos',
                'operator'         => '>',
                'threshold'        => 30,
                'channels'         => ['admin'],
                'severity'         => 'warn',
                'cooldown_minutes' => 360,
                'is_active'        => true,
            ],
        ];

        foreach ($defaults as $row) {
            AlertRule::updateOrCreate(
                ['name' => $row['name']],
                $row
            );
        }
    }
}
