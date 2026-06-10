<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Example extends Model
{
    protected $fillable = [
        'title',
        'description',
        'category',
        'code',
        'install_command',
        'technologies',
        'created_by',
    ];

    protected $casts = [
        'technologies' => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
