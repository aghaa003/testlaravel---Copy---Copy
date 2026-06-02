<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAssignment extends Model
{
    protected $fillable = [
        'user_id',
        'assignment_id',
        'solution',
        'language',
        'score',
        'status',
        'is_completed',
        'feedback',
        'submitted_at',
        'completed_at',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }
}
