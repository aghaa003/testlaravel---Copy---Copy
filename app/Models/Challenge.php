<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Challenge extends Model
{
    protected $fillable = [
        'title', 'description',
        'input_format', 'output_format', 'examples',     // ← missing
        'constraints', 'time_limit', 'tags', 'section',  // ← missing
        'difficulty', 'category', 'points',
        'total_submissions', 'success_rate',
    ];

    public function submissions(): HasMany
    {
        return $this->hasMany(ChallengeSubmission::class);
    }
}
