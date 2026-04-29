<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewHelpfulVote extends Model
{
    protected $table = 'review_helpful_votes';
    public $timestamps = false;

    protected $fillable = ['review_id', 'user_id'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function review()
    {
        return $this->belongsTo(Review::class, 'review_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
