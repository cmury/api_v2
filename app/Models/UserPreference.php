<?php

namespace App\Models;

use App\Support\DataDatabase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPreference extends Model
{
    protected $table = 'users_preferences';

    protected $fillable = [
        'user_id',
        'map_type',
        'default_search_id',
        'date_range',
        'new_application_email_frequency',
        'locale',
    ];

    public function getConnectionName(): ?string
    {
        return DataDatabase::name();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
