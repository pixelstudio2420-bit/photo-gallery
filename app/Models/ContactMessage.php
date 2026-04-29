<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    protected $table = 'contact_messages';

    protected $fillable = [
        'ticket_number', 'user_id', 'name', 'email',
        'subject', 'category', 'priority', 'message',
        'status', 'admin_reply',
        'assigned_to_admin_id',
        'first_response_at', 'resolved_at', 'resolved_by_admin_id',
        'sla_deadline', 'last_activity_at',
        'reply_count', 'satisfaction_rating', 'satisfaction_comment',
    ];

    protected $casts = [
        'first_response_at' => 'datetime',
        'resolved_at'       => 'datetime',
        'sla_deadline'      => 'datetime',
        'last_activity_at'  => 'datetime',
    ];

    // ─── Constants ───
    public const STATUSES = [
        'new'         => 'ใหม่',
        'open'        => 'เปิดอยู่',
        'in_progress' => 'กำลังดำเนินการ',
        'waiting'     => 'รอลูกค้า',
        'resolved'    => 'แก้ไขแล้ว',
        'closed'      => 'ปิด',
    ];

    public const PRIORITIES = [
        'low'    => ['label' => 'ต่ำ',    'color' => 'gray',   'sla_hours' => 72],
        'normal' => ['label' => 'ปกติ',   'color' => 'blue',   'sla_hours' => 24],
        'high'   => ['label' => 'สูง',    'color' => 'amber',  'sla_hours' => 8],
        'urgent' => ['label' => 'เร่งด่วน', 'color' => 'red',   'sla_hours' => 2],
    ];

    public const CATEGORIES = [
        'general'      => 'สอบถามทั่วไป',
        'billing'      => 'การชำระเงิน',
        'technical'    => 'ปัญหาเทคนิค',
        'account'      => 'บัญชีผู้ใช้',
        'refund'       => 'ขอคืนเงิน',
        'photographer' => 'สำหรับช่างภาพ',
        'other'        => 'อื่นๆ',
    ];

    // ─── Relations ───
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function replies()
    {
        return $this->hasMany(ContactReply::class, 'ticket_id')->orderBy('created_at');
    }

    public function publicReplies()
    {
        return $this->hasMany(ContactReply::class, 'ticket_id')
            ->where('is_internal_note', false)
            ->orderBy('created_at');
    }

    public function activities()
    {
        return $this->hasMany(ContactActivity::class, 'ticket_id')->orderByDesc('created_at');
    }

    public function assignedAdmin()
    {
        return $this->belongsTo(Admin::class, 'assigned_to_admin_id');
    }

    public function resolvedByAdmin()
    {
        return $this->belongsTo(Admin::class, 'resolved_by_admin_id');
    }

    // ─── Scopes ───
    public function scopeNew($q)       { return $q->where('status', 'new'); }
    public function scopeOpen($q)      { return $q->whereIn('status', ['new', 'open', 'in_progress', 'waiting']); }
    public function scopeResolved($q)  { return $q->whereIn('status', ['resolved', 'closed']); }
    public function scopeAssignedTo($q, int $adminId) { return $q->where('assigned_to_admin_id', $adminId); }
    public function scopeUnassigned($q) { return $q->whereNull('assigned_to_admin_id'); }
    public function scopeOverdue($q)   { return $q->whereNotNull('sla_deadline')->where('sla_deadline', '<', now())->whereIn('status', ['new', 'open', 'in_progress', 'waiting']); }
    public function scopeByPriority($q, string $priority) { return $q->where('priority', $priority); }
    public function scopeByCategory($q, string $category) { return $q->where('category', $category); }

    // ─── Methods ───

    public static function generateTicketNumber(): string
    {
        do {
            $num = 'TKT-' . str_pad((string) mt_rand(100000, 999999), 6, '0', STR_PAD_LEFT);
        } while (static::where('ticket_number', $num)->exists());
        return $num;
    }

    public function calculateSlaDeadline(?string $priority = null): \Carbon\Carbon
    {
        $priority = $priority ?: $this->priority ?: 'normal';
        $hours = self::PRIORITIES[$priority]['sla_hours'] ?? 24;
        return ($this->created_at ?: now())->copy()->addHours($hours);
    }

    public function isOverdue(): bool
    {
        if (!$this->sla_deadline) return false;
        if (in_array($this->status, ['resolved', 'closed'])) return false;
        return $this->sla_deadline->isPast();
    }

    public function slaTimeRemaining(): ?string
    {
        if (!$this->sla_deadline) return null;
        if ($this->sla_deadline->isPast()) return 'เกิน ' . $this->sla_deadline->diffForHumans(null, true);
        return 'เหลือ ' . $this->sla_deadline->diffForHumans(null, true);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getPriorityLabelAttribute(): string
    {
        return self::PRIORITIES[$this->priority]['label'] ?? $this->priority;
    }

    public function getPriorityColorAttribute(): string
    {
        return self::PRIORITIES[$this->priority]['color'] ?? 'gray';
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    public function changeStatus(string $newStatus, ?int $adminId = null): void
    {
        $oldStatus = $this->status;
        if ($oldStatus === $newStatus) return;

        $updates = ['status' => $newStatus, 'last_activity_at' => now()];

        if (in_array($newStatus, ['resolved', 'closed']) && !$this->resolved_at) {
            $updates['resolved_at'] = now();
            $updates['resolved_by_admin_id'] = $adminId;
        }

        if ($newStatus === 'open' && $this->resolved_at) {
            $updates['resolved_at'] = null;
            $updates['resolved_by_admin_id'] = null;
        }

        $this->update($updates);

        $this->logActivity('status_changed', $adminId, [
            'old' => $oldStatus,
            'new' => $newStatus,
        ], "เปลี่ยนสถานะจาก " . (self::STATUSES[$oldStatus] ?? $oldStatus) . " → " . (self::STATUSES[$newStatus] ?? $newStatus));
    }

    public function logActivity(string $type, ?int $adminId = null, array $meta = [], ?string $description = null): ContactActivity
    {
        $actorName = $adminId
            ? (optional(Admin::find($adminId))->full_name ?? 'Admin')
            : (auth()->user()?->first_name ?? 'System');

        return ContactActivity::create([
            'ticket_id'      => $this->id,
            'type'           => $type,
            'actor_admin_id' => $adminId,
            'actor_user_id'  => $adminId ? null : auth()->id(),
            'actor_name'     => $actorName,
            'meta'           => $meta,
            'description'    => $description,
        ]);
    }
}
