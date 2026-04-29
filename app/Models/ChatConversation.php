<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatConversation extends Model
{
    protected $table = 'chat_conversations';
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'photographer_id', 'event_id', 'subject',
        'last_message_at', 'status',
        'unread_count_user', 'unread_count_photographer',
        'archived_at', 'archived_by',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'archived_at'     => 'datetime',
        'created_at'      => 'datetime',
    ];

    // ─── Relations ───
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function photographer()
    {
        return $this->belongsTo(PhotographerProfile::class, 'photographer_id');
    }

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function messages()
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id');
    }

    public function visibleMessages()
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id')
            ->whereNull('deleted_at')
            ->orderBy('created_at');
    }

    public function latestMessage()
    {
        return $this->hasOne(ChatMessage::class, 'conversation_id')->latest('created_at');
    }

    // ─── Scopes ───
    public function scopeActive($q)
    {
        return $q->whereNull('archived_at');
    }

    public function scopeArchived($q)
    {
        return $q->whereNotNull('archived_at');
    }

    public function scopeForUser($q, int $userId)
    {
        return $q->where('user_id', $userId);
    }

    public function scopeForPhotographer($q, int $photographerProfileId)
    {
        return $q->where('photographer_id', $photographerProfileId);
    }

    // ─── Helpers ───
    public function archive(string $by): void
    {
        $this->update(['archived_at' => now(), 'archived_by' => $by, 'status' => 'archived']);
    }

    public function unarchive(): void
    {
        $this->update(['archived_at' => null, 'archived_by' => null, 'status' => 'active']);
    }

    public function incrementUnread(string $forRole): void
    {
        if ($forRole === 'user') $this->increment('unread_count_user');
        elseif ($forRole === 'photographer') $this->increment('unread_count_photographer');
    }

    public function resetUnread(string $forRole): void
    {
        $column = $forRole === 'user' ? 'unread_count_user' : 'unread_count_photographer';
        $this->update([$column => 0]);
    }

    public function otherParty(int $viewerUserId): array
    {
        if ($this->user_id === $viewerUserId) {
            $photog = $this->photographer?->user;
            return [
                'id'     => $photog?->id,
                'name'   => $photog?->first_name ?? 'ช่างภาพ',
                'role'   => 'photographer',
                'avatar' => $photog?->avatar,
            ];
        }
        return [
            'id'     => $this->user_id,
            'name'   => $this->user?->first_name ?? 'ลูกค้า',
            'role'   => 'user',
            'avatar' => $this->user?->avatar,
        ];
    }
}
