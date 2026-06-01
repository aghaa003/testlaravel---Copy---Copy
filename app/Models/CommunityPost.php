<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunityPost extends Model
{
    protected $fillable = [
        'user_id', 'title', 'content', 'body',
        'tags', 'category', 'likes_count', 'comments_count',
    ];

    protected $casts = ['tags' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function comments()
    {
        return $this->hasMany(CommunityComment::class, 'post_id');
    }

    public function likes()
    {
        return $this->hasMany(CommunityPostLike::class, 'post_id');
    }
}
