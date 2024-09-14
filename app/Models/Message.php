<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $table = 'messages';
    protected $fillable = [
        'remote_id',
        'chat_id',
        'first_name',
        'last_name',
        'username',
        'message',
        'created_at',
        'updated_at',
    ];

    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }
}
