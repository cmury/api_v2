<?php

namespace App\Models;

use App\Support\DataDatabase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSearch extends Model
{
    protected $table = 'users_searches';

    protected $fillable = [
        'user_id',
        'name',
        'lat',
        'lng',
        'radius',
        'filter',
        'notify',
    ];

    protected function casts(): array
    {
        return [
            'filter' => 'array',
            'notify' => 'boolean',
            'lat' => 'float',
            'lng' => 'float',
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
}
