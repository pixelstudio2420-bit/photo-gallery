<?php

namespace App\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    protected $table = 'financial_journal_entries';

    protected $fillable = [
        'journal_uuid', 'type', 'description',
        'idempotency_key', 'metadata', 'posted_at',
        'posted_by', 'reversed_by_id',
    ];

    protected $casts = [
        'metadata'  => 'array',
        'posted_at' => 'datetime',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class, 'journal_entry_id');
    }

    public function reversedBy()
    {
        return $this->belongsTo(self::class, 'reversed_by_id');
    }
}
