<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;

class MarketingEvent extends Model
{
    protected $table = 'marketing_events';

    protected $fillable = [
        'event_name', 'user_id', 'session_id', 'url', 'referrer',
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
        'lp_id', 'campaign_id', 'push_campaign_id', 'order_id',
        'value', 'currency', 'meta', 'ip', 'country', 'device', 'occurred_at',
    ];

    protected $casts = [
        'meta'        => 'array',
        'value'       => 'float',
        'occurred_at' => 'datetime',
    ];

    // Canonical event names (use these to avoid typos)
    public const EV_PAGE_VIEW       = 'page_view';
    public const EV_LP_VIEW         = 'lp_view';
    public const EV_LP_CTA          = 'lp_cta_click';
    public const EV_VIEW_PRODUCT    = 'view_product';
    public const EV_ADD_TO_CART     = 'add_to_cart';
    public const EV_BEGIN_CHECKOUT  = 'begin_checkout';
    public const EV_PURCHASE        = 'purchase';
    public const EV_SIGNUP          = 'signup';
    public const EV_NEWSLETTER_SUB  = 'newsletter_subscribe';
    public const EV_PUSH_SUBSCRIBE  = 'push_subscribe';
    public const EV_PUSH_CLICK      = 'push_click';

    public function scopeName($q, string $name)
    {
        return $q->where('event_name', $name);
    }

    public function scopeBetween($q, $from, $to)
    {
        return $q->whereBetween('occurred_at', [$from, $to]);
    }
}
