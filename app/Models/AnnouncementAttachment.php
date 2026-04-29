<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Inline image attachment on an Announcement.
 * Cover image lives directly on `announcements.cover_image_path` —
 * this table holds gallery / inline-body images.
 */
class AnnouncementAttachment extends Model
{
    protected $table = 'announcement_attachments';

    protected $fillable = ['announcement_id', 'image_path', 'caption', 'sort_order'];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }
}
