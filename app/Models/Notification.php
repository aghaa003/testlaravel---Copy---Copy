<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'from_user_id', 'from_user_name',
        'title', 'body', 'type',
        'entity_id', 'entity_title', 'message', 'read',
    ];

    protected $casts = [
        'read' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }
}
