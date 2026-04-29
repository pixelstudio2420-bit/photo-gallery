<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogAffiliateClick extends Model
{
    protected $table = 'blog_affiliate_clicks';

    public $timestamps = false;

    protected $fillable = [
        'affiliate_link_id', 'post_id', 'user_id',
        'ip_address', 'user_agent', 'referrer',
        'country', 'device_type', 'clicked_at',
    ];

    protected $casts = [
        'affiliate_link_id' => 'integer',
        'post_id'           => 'integer',
        'user_id'           => 'integer',
        'clicked_at'        => 'datetime',
    ];

    /* ──────────────────────────── Relationships ──────────────────────────── */

    public function affiliateLink()
    {
        return $this->belongsTo(BlogAffiliateLink::class, 'affiliate_link_id');
    }

    public function post()
    {
        return $this->belongsTo(BlogPost::class, 'post_id');
    }
}
