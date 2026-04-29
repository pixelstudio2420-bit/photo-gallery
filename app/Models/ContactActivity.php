<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactActivity extends Model
{
    protected $table = 'contact_activities';
    public $timestamps = false;

    protected $fillable = [
        'ticket_id', 'type',
        'actor_admin_id', 'actor_user_id', 'actor_name',
        'meta', 'description',
    ];

    protected $casts = [
        'meta'       => 'array',
        'created_at' => 'datetime',
    ];

    public function ticket()
    {
        return $this->belongsTo(ContactMessage::class, 'ticket_id');
    }

    public function getIconAttribute(): string
    {
        return match ($this->type) {
            'created'         => 'bi-file-earmark-plus',
            'replied'         => 'bi-reply',
            'status_changed'  => 'bi-arrow-right-circle',
            'priority_changed' => 'bi-flag',
            'assigned'        => 'bi-person-check',
            'unassigned'      => 'bi-person-dash',
            'category_changed' => 'bi-tag',
            'resolved'        => 'bi-check-circle',
            'reopened'        => 'bi-arrow-clockwise',
            'closed'          => 'bi-lock',
            'merged'          => 'bi-git',
            'note_added'      => 'bi-sticky',
            default           => 'bi-circle',
        };
    }

    public function getColorAttribute(): string
    {
        return match ($this->type) {
            'created', 'replied'         => 'blue',
            'assigned', 'resolved'       => 'emerald',
            'status_changed'             => 'indigo',
            'priority_changed'           => 'amber',
            'unassigned', 'reopened'     => 'gray',
            'closed'                     => 'red',
            'note_added'                 => 'purple',
            default                      => 'gray',
        };
    }
}
