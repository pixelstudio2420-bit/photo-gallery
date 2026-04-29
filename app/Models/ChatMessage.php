<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    protected $table = 'chat_messages';
    public $timestamps = false;

    protected $fillable = [
        'conversation_id', 'sender_type', 'sender_id',
        'message', 'message_type',
        'attachment_url', 'attachment_name', 'attachment_size',
        'is_read', 'read_at', 'edited_at', 'deleted_at',
    ];

    protected $casts = [
        'is_read'    => 'boolean',
        'created_at' => 'datetime',
        'read_at'    => 'datetime',
        'edited_at'  => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ─── Relations ───
    public function conversation()
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    // ─── Scopes ───
    public function scopeUnread($q)
    {
        return $q->where('is_read', false);
    }

    public function scopeNotDeleted($q)
    {
        return $q->whereNull('deleted_at');
    }

    public function scopeSince($q, string $datetime)
    {
        return $q->where('created_at', '>', $datetime);
    }

    public function scopeBySender($q, string $type, int $id = null)
    {
        $q->where('sender_type', $type);
        if ($id !== null) $q->where('sender_id', $id);
        return $q;
    }

    // ─── Helpers ───
    public function hasAttachment(): bool
    {
        return !empty($this->attachment_url);
    }

    public function isImage(): bool
    {
        return $this->message_type === 'image';
    }

    public function getAttachmentSizeFormattedAttribute(): string
    {
        $bytes = $this->attachment_size ?? 0;
        if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
        if ($bytes >= 1024) return round($bytes / 1024, 1) . ' KB';
        return $bytes . ' B';
    }

    public function markAsRead(): void
    {
        if ($this->is_read) return;
        $this->update(['is_read' => true, 'read_at' => now()]);
    }

    public function softDelete(): void
    {
        $this->update(['deleted_at' => now(), 'message' => '[ข้อความถูกลบ]']);
    }
}
