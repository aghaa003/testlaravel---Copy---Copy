<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Challenge extends Model
{
    protected $fillable = [
        'title', 'description', 'difficulty', 'category', 'points', 'total_submissions', 'success_rate',
    ];

    public function submissions(): HasMany
    {
        return $this->hasMany(ChallengeSubmission::class);
    }
}
