<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lesson extends Model
{
    protected $fillable = [
        'course_id', 'title', 'description', 'video_url',
        'pdf_url', 'attachment_url', 'attachment_name',
        'duration', 'order_num',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(LessonComment::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(LessonLike::class);
    }

    public function progress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }
}
