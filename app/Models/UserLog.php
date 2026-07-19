<?php

namespace App\Models;

use App\Support\DataDatabase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class UserLog extends Model
{
    protected $table = 'users_log';

    protected $fillable = [
        'user_id',
        'action',
        'actionable_type',
        'actionable_id',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function getConnectionName(): ?string
    {
        return DataDatabase::name();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actionable(): MorphTo
    {
        return $this->morphTo();
    }
}
