<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    protected $fillable = [
        'title', 'description', 'thumbnail_url', 'category',
        'level', 'language', 'creator_id', 'average_rating',
        'total_reviews', 'total_lessons', 'total_enrollments',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('order_num', 'asc');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class)->orderBy('assignment_order');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }
}
