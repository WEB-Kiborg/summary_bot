<?php

namespace App\Models;

use App\Enums\SummaryFrequencyEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Chat extends Model
{
    protected $table = 'chats';
    protected $fillable = [
        'remote_id',
        'name',
        'admin_id',
        'is_allowed_summary',
        'summary_frequency',
        'summary_created_at',
    ];
    protected $casts = [
        'is_allowed_summary' => 'boolean',
        'summary_frequency' => SummaryFrequencyEnum::class,
        'summary_created_at' => 'datetime',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function summaries(): HasMany
    {
        return $this->hasMany(Summary::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
