<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasUuids;

    protected $fillable = [
        'id', 'name', 'username', 'email', 'password',
        'avatar_url', 'bio',
        'github_url', 'linkedin_url', 'website_url', 'skills',
        'role', 'banned', 'points', 'global_rank',
    ];

    protected $casts = [
        'skills' => 'array',
        'banned' => 'boolean',
    ];

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'creator_id');
    }

    public function repositories(): HasMany
    {
        return $this->hasMany(Repository::class, 'owner_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ChallengeSubmission::class);
    }

    public function assignmentSubmissions(): HasMany
    {
        return $this->hasMany(UserAssignment::class);
    }
}
