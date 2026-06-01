<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Repository extends Model
{
    protected $fillable = [
        'title', 'description', 'thumbnail_url', 'owner_id',
        'technologies', 'visibility',
        'likes_count', 'stars_count', 'forks_count',
        'project_url', 'github_url',
        'is_draft', 'live_demo_url',
        'code_files_urls', 'pdf_files_urls', 'cover_image_url',
        'source_project',
    ];

    protected $casts = [
        'technologies' => 'array',
        'code_files_urls' => 'array',
        'pdf_files_urls' => 'array',
        'is_draft' => 'boolean',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
