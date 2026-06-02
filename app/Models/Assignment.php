<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assignment extends Model
{
    protected $fillable = [
        'course_id',
        'title',
        'question',
        'description',
        'requirements',
        'difficulty',
        'language',
        'assignment_order',
        'points',
        'is_active',
        'due_date',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'due_date' => 'datetime',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(UserAssignment::class);
    }
}
