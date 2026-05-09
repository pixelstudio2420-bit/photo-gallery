<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Admin-managed navigation menu entry.
 *
 * Read by App\Services\NavigationService at render time (cached for
 * 5 minutes, invalidated on any save here via the booted() hook).
 * Written via /admin/navigation CRUD.
 *
 * @property int    $id
 * @property string $label
 * @property string $url
 * @property string|null $icon
 * @property string $location          navbar | footer | both | hidden
 * @property string $audience          public | guest | authenticated | photographer
 * @property string $cta_style         default | primary | accent
 * @property string|null $badge_text
 * @property string|null $badge_color
 * @property bool   $open_in_new_tab
 * @property bool   $is_active
 * @property int    $sort_order
 * @property string|null $visibility_route_pattern
 */
class NavMenuItem extends Model
{
    protected $fillable = [
        'label', 'url', 'icon',
        'location', 'audience', 'cta_style',
        'badge_text', 'badge_color',
        'open_in_new_tab', 'is_active', 'sort_order',
        'visibility_route_pattern',
    ];

    protected $casts = [
        'open_in_new_tab' => 'boolean',
        'is_active'       => 'boolean',
        'sort_order'      => 'integer',
    ];

    public const LOCATIONS = [
        'navbar' => 'แสดงเฉพาะใน Navbar (ด้านบน)',
        'footer' => 'แสดงเฉพาะใน Footer',
        'both'   => 'แสดงทั้ง Navbar และ Footer',
        'hidden' => 'ซ่อน (ไม่แสดงที่ใดเลย)',
    ];

    public const AUDIENCES = [
        'public'        => 'ทุกคนเห็น (default)',
        'guest'         => 'เฉพาะคนที่ยังไม่ได้ login',
        'authenticated' => 'เฉพาะคนที่ login แล้ว',
        'photographer'  => 'เฉพาะช่างภาพที่ approved',
    ];

    public const CTA_STYLES = [
        'default' => 'ลิงก์ปกติ',
        'primary' => 'ปุ่มเด่น (สีฟ้า/ม่วง)',
        'accent'  => 'ปุ่มเรียกร้อง (สีเหลือง — เช่น "เริ่มขายรูป")',
    ];

    public const BADGE_COLORS = [
        'amber'    => 'เหลือง — NEW / โปรโมชั่น',
        'rose'     => 'แดง — ด่วน / สำคัญ',
        'emerald'  => 'เขียว — ใหม่ดี / safe',
        'indigo'   => 'น้ำเงิน — feature ใหม่',
        'slate'    => 'เทา — เกี่ยวข้อง',
    ];

    /**
     * Bust the NavigationService cache whenever any item is saved /
     * deleted. Without this, an admin's drag-drop reorder wouldn't
     * show up to users for up to 5 minutes (the cache TTL). Cheap
     * because the cache layer just does a single CACHE::forget per
     * key on the next read.
     */
    protected static function booted(): void
    {
        $invalidate = function () {
            try {
                app(\App\Services\NavigationService::class)->flushCache();
            } catch (\Throwable) {
                // Service-binding hiccup → cache will self-expire.
            }
        };
        static::saved($invalidate);
        static::deleted($invalidate);
    }
}
