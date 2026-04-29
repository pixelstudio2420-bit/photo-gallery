<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class Admin extends Authenticatable
{
    protected $table = 'auth_admins';
    public $timestamps = false;

    protected $fillable = [
        'email', 'password_hash', 'first_name', 'last_name',
        'role', 'permissions', 'is_active', 'last_login_at',
    ];

    protected $hidden = ['password_hash'];

    protected $casts = [
        'created_at'    => 'datetime',
        'last_login_at' => 'datetime',
        'permissions'   => 'array',
        'is_active'     => 'boolean',
    ];

    /* ──────────────────────────── Auth ──────────────────────────── */
    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    /* ──────────────────────────── Accessors ──────────────────────────── */
    public function getFullNameAttribute()
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /* ──────────────────────────── Role Constants ──────────────────────────── */
    const ROLE_SUPERADMIN = 'superadmin';
    const ROLE_ADMIN      = 'admin';
    const ROLE_EDITOR     = 'editor';

    const ROLES = [
        self::ROLE_SUPERADMIN => [
            'label' => 'Super Admin',
            'thai'  => 'เจ้าของเว็บ',
            'color' => '#ef4444',
            'icon'  => 'bi-shield-fill-check',
            'desc'  => 'เข้าถึงทุกฟีเจอร์ จัดการแอดมินคนอื่นได้',
        ],
        self::ROLE_ADMIN => [
            'label' => 'Admin',
            'thai'  => 'แอดมิน',
            'color' => '#2563eb',
            'icon'  => 'bi-shield-fill',
            'desc'  => 'เข้าถึงเมนูตามที่ Super Admin กำหนด',
        ],
        self::ROLE_EDITOR => [
            'label' => 'Editor',
            'thai'  => 'เอดิเตอร์',
            'color' => '#10b981',
            'icon'  => 'bi-pencil-square',
            'desc'  => 'จัดการเนื้อหาตามที่ Super Admin กำหนด',
        ],
    ];

    /* ──────────────────────────── Permission Map ──────────────────────────── */
    /**
     * All available permissions with metadata.
     * Grouped by sidebar sections for easy rendering.
     */
    const PERMISSION_GROUPS = [
        'แดชบอร์ด' => [
            'dashboard' => ['label' => 'แดชบอร์ด', 'icon' => 'bi-grid-1x2-fill'],
        ],
        'จัดการเนื้อหา' => [
            'events'        => ['label' => 'งานอีเวนต์', 'icon' => 'bi-calendar-event'],
            'categories'    => ['label' => 'หมวดหมู่', 'icon' => 'bi-tags'],
            'photographers' => ['label' => 'ช่างภาพ', 'icon' => 'bi-camera'],
            'pricing'       => ['label' => 'ราคา & แพ็กเกจ', 'icon' => 'bi-tags'],
            'coupons'       => ['label' => 'คูปองส่วนลด', 'icon' => 'bi-ticket-perforated'],
            'products'      => ['label' => 'สินค้าดิจิทัล', 'icon' => 'bi-box-seam'],
        ],
        'การขาย' => [
            'orders'        => ['label' => 'คำสั่งซื้อ', 'icon' => 'bi-bag-check'],
            'payment_slips' => ['label' => 'ตรวจสอบสลิป', 'icon' => 'bi-receipt-cutoff'],
            'reviews'       => ['label' => 'รีวิว', 'icon' => 'bi-star'],
        ],
        'การเงิน' => [
            'finance'         => ['label' => 'ภาพรวมการเงิน', 'icon' => 'bi-graph-up-arrow'],
            'payment_methods' => ['label' => 'ช่องทางชำระเงิน', 'icon' => 'bi-wallet2'],
            'messages'        => ['label' => 'ข้อความติดต่อ', 'icon' => 'bi-envelope'],
        ],
        'ผู้ใช้งาน' => [
            'users'        => ['label' => 'จัดการผู้ใช้', 'icon' => 'bi-people'],
            'online_users' => ['label' => 'สถานะออนไลน์', 'icon' => 'bi-broadcast'],
        ],
        'ตั้งค่าระบบ' => [
            'settings' => ['label' => 'ตั้งค่าทั้งหมด', 'icon' => 'bi-gear'],
        ],
    ];

    /**
     * Flat list of all permission keys.
     */
    public static function allPermissionKeys(): array
    {
        $keys = [];
        foreach (self::PERMISSION_GROUPS as $perms) {
            $keys = array_merge($keys, array_keys($perms));
        }
        return $keys;
    }

    /**
     * Default permissions for each role (used when creating new admin).
     */
    public static function defaultPermissions(string $role): array
    {
        return match ($role) {
            self::ROLE_SUPERADMIN => self::allPermissionKeys(),
            self::ROLE_ADMIN     => self::allPermissionKeys(), // all by default, superadmin can restrict
            self::ROLE_EDITOR    => ['dashboard', 'events', 'categories', 'photographers', 'products'],
            default              => ['dashboard'],
        };
    }

    /* ──────────────────────────── Role Checks ──────────────────────────── */
    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPERADMIN;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isEditor(): bool
    {
        return $this->role === self::ROLE_EDITOR;
    }

    /**
     * Get the role display info.
     */
    public function getRoleInfoAttribute(): array
    {
        return self::ROLES[$this->role] ?? self::ROLES[self::ROLE_EDITOR];
    }

    /* ──────────────────────────── Permission Checks ──────────────────────────── */
    /**
     * Superadmin always has all permissions.
     * Other roles check their stored permissions array.
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $perms = $this->permissions ?? [];
        return in_array($permission, $perms, true);
    }

    /**
     * Check if admin has ANY of the given permissions.
     */
    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $perm) {
            if ($this->hasPermission($perm)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get effective permissions list.
     */
    public function getEffectivePermissions(): array
    {
        if ($this->isSuperAdmin()) {
            return self::allPermissionKeys();
        }
        return $this->permissions ?? [];
    }
}
