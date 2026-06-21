<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Project extends Model
{
    protected $fillable = [
        'track',
        'title',
        'description',
        'difficulty',
        'tags',
        'category',
        'created_by',
        'is_active',
    ];

    protected $casts = [
        'tags' => 'array',
        'difficulty' => 'integer',
        'is_active' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
