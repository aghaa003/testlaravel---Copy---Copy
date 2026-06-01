<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunityPostLike extends Model
{
    public $timestamps = false;

    protected $fillable = ['post_id', 'user_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function post()
    {
        return $this->belongsTo(CommunityPost::class, 'post_id');
    }
}
