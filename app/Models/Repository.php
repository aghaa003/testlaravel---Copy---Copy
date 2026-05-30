<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Repository extends Model
{
    protected $fillable = [
        'title', 'description', 'thumbnail_url', 'owner_id',
        'technologies', 'visibility', 'likes_count', 'project_url',
    ];

    // تحويل حقل التقنيات تلقائياً من JSON في قاعدة البيانات إلى مصفوفة PHP والعكس
    protected $casts = [
        'technologies' => 'array',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function likes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'repository_likes');
    }
}
