<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactReply extends Model
{
    protected $table = 'contact_replies';
    public $timestamps = false;

    protected $fillable = [
        'ticket_id', 'sender_type', 'sender_id',
        'sender_name', 'sender_email',
        'message', 'is_internal_note', 'attachments', 'read_at',
    ];

    protected $casts = [
        'is_internal_note' => 'boolean',
        'attachments'      => 'array',
        'read_at'          => 'datetime',
        'created_at'       => 'datetime',
    ];

    public function ticket()
    {
        return $this->belongsTo(ContactMessage::class, 'ticket_id');
    }

    public function scopePublic($q)
    {
        return $q->where('is_internal_note', false);
    }

    public function scopeInternal($q)
    {
        return $q->where('is_internal_note', true);
    }

    public function scopeFromAdmins($q)
    {
        return $q->where('sender_type', 'admin');
    }

    public function scopeFromUsers($q)
    {
        return $q->where('sender_type', 'user');
    }
}
