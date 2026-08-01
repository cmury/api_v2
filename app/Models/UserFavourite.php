<?php

namespace App\Models;

use App\Support\DataDatabase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFavourite extends Model
{
    protected $table = 'users_favourites';

    protected $fillable = [
        'user_id',
        'application_id',
        'notes',
    ];

    public function getConnectionName(): ?string
    {
        return DataDatabase::name();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
