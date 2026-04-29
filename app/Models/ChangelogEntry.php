<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChangelogEntry extends Model
{
    protected $fillable = [
        'version', 'released_on', 'title', 'type', 'body', 'audience', 'is_published',
    ];

    protected $casts = [
        'released_on'  => 'date',
        'is_published' => 'boolean',
    ];

    public static function types(): array
    {
        return [
            'feature'     => ['label' => 'ฟีเจอร์ใหม่',    'icon' => 'bi-stars',         'color' => 'indigo'],
            'improvement' => ['label' => 'ปรับปรุง',       'icon' => 'bi-graph-up',      'color' => 'sky'],
            'fix'         => ['label' => 'แก้บั๊ก',         'icon' => 'bi-bandaid',        'color' => 'emerald'],
            'security'    => ['label' => 'ความปลอดภัย',    'icon' => 'bi-shield-check',   'color' => 'rose'],
            'deprecation' => ['label' => 'เลิกใช้งาน',     'icon' => 'bi-exclamation-triangle', 'color' => 'amber'],
        ];
    }

    public static function audiences(): array
    {
        return [
            'all'          => 'ทุกคน',
            'admin'        => 'แอดมิน',
            'photographer' => 'ช่างภาพ',
            'public'       => 'ลูกค้า',
        ];
    }

    public function scopePublished($q) { return $q->where('is_published', true); }

    public function scopeForAudience($q, string $audience)
    {
        return $q->whereIn('audience', ['all', $audience]);
    }
}
