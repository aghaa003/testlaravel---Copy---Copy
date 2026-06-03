<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Challenge extends Model
{
    protected $fillable = [
        'creator_id',
        'title',
        'description',
        'input_format',
        'output_format',
        'examples',
        'constraints',
        'time_limit',
        'tags',
        'section',
        'difficulty',
        'category',
        'points',
        'total_submissions',
        'success_rate',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ChallengeSubmission::class);
    }
}
