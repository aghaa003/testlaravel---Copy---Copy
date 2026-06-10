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

    protected $appends = [
        'isPublic', 'codeFilesUrls', 'pdfFilesUrls', 'coverImageUrl',
        'liveDemoUrl', 'repoUrl', 'likes',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function getIsPublicAttribute(): bool
    {
        return $this->visibility === 'public';
    }

    public function getCodeFilesUrlsAttribute(): array
    {
        return $this->attributes['code_files_urls'] ? (array) $this->castAttribute('code_files_urls', $this->attributes['code_files_urls']) : [];
    }

    public function getPdfFilesUrlsAttribute(): array
    {
        return $this->attributes['pdf_files_urls'] ? (array) $this->castAttribute('pdf_files_urls', $this->attributes['pdf_files_urls']) : [];
    }

    public function getCoverImageUrlAttribute(): ?string
    {
        return $this->attributes['cover_image_url'] ?? null;
    }

    public function getLiveDemoUrlAttribute(): ?string
    {
        return $this->attributes['live_demo_url'] ?? null;
    }

    public function getRepoUrlAttribute(): ?string
    {
        return $this->attributes['github_url'] ?? null;
    }

    public function getLikesAttribute(): int
    {
        return $this->likes_count ?? 0;
    }

    public function toArray()
    {
        $array = parent::toArray();
        $array['createdAt'] = $this->created_at;

        return $array;
    }
}
