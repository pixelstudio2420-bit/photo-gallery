<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    protected $table = 'email_logs';
    public $timestamps = false;
    protected $fillable = [
        'to_email',
        'subject',
        'type',
        'status',
        'error_message',
        'driver',
        'created_at',
    ];
}
