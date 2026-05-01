<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * pSEO template — drives auto-generation per page type.
 *
 * One row per page-type pattern. Admins toggle is_auto_enabled to
 * pause generation, edit the *_pattern fields to change wording,
 * and bump min_data_points to gate thin-content pages.
 *
 * Templates DON'T render the page directly — they describe what
 * pages SHOULD exist. PSeoService walks the source data
 * (events / photographers / categories / locations) and produces
 * concrete SeoLandingPage rows by substituting variables into the
 * template patterns.
 */
class SeoPageTemplate extends Model
{
    protected $table = 'seo_page_templates';

    public const TYPE_LOCATION       = 'location';
    public const TYPE_CATEGORY       = 'category';
    public const TYPE_COMBO          = 'combo';
    public const TYPE_PHOTOGRAPHER   = 'photographer';
    public const TYPE_EVENT_ARCHIVE  = 'event_archive';
    public const TYPE_CUSTOM         = 'custom';

    protected $fillable = [
        'type', 'name', 'is_auto_enabled',
        'title_pattern', 'meta_description_pattern',
        'body_template', 'h1_pattern',
        'min_data_points', 'schema_type', 'linking_config',
    ];

    protected $casts = [
        'is_auto_enabled' => 'boolean',
        'min_data_points' => 'integer',
        'linking_config'  => 'array',
    ];

    public function landingPages()
    {
        return $this->hasMany(SeoLandingPage::class, 'template_id');
    }

    /** Static lookup of all known page types. */
    public static function allTypes(): array
    {
        return [
            self::TYPE_LOCATION       => 'หน้าตามจังหวัด/พื้นที่',
            self::TYPE_CATEGORY       => 'หน้าตามประเภทงาน',
            self::TYPE_COMBO          => 'หน้าตามประเภท × พื้นที่ (Combo)',
            self::TYPE_PHOTOGRAPHER   => 'หน้าโปรไฟล์ช่างภาพ',
            self::TYPE_EVENT_ARCHIVE  => 'หน้ารวมอีเวนต์',
            self::TYPE_CUSTOM         => 'หน้าที่สร้างเอง',
        ];
    }
}
