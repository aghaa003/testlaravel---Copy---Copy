<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Repository extends Model
{
    protected $fillable = [
        'title', 'description', 'thumbnail_url', 'owner_id',
        'technologies', 'visibility',
        'likes_count', 'stars_count', 'forks_count',           // ← missing
        'project_url', 'github_url',                           // ← github_url missing
        'is_draft', 'live_demo_url',                           // ← missing
        'code_files_urls', 'pdf_files_urls', 'cover_image_url', // ← missing
        'source_project',
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
