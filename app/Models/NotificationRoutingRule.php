<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per (event_key, audience) — admin-controlled toggles for
 * which channels carry which notifications to which audience.
 *
 * Read-heavy model: NotificationRouter caches the entire table in
 * memory at request boot for O(1) lookups. Cache is invalidated by
 * the controller's update path on save.
 */
class NotificationRoutingRule extends Model
{
    protected $table = 'notification_routing_rules';

    protected $fillable = [
        'event_key',
        'audience',
        'in_app_enabled',
        'email_enabled',
        'line_enabled',
        'sms_enabled',
        'push_enabled',
        'is_enabled',
        'note',
    ];

    protected $casts = [
        'in_app_enabled' => 'boolean',
        'email_enabled'  => 'boolean',
        'line_enabled'   => 'boolean',
        'sms_enabled'    => 'boolean',
        'push_enabled'   => 'boolean',
        'is_enabled'     => 'boolean',
    ];

    /** Audiences that the routing matrix covers. */
    public const AUDIENCES = ['customer', 'photographer', 'admin'];

    /** Channels per audience row. Order is the order they appear in the UI. */
    public const CHANNELS = ['in_app', 'email', 'line', 'sms', 'push'];
}
