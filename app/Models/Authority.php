<?php

namespace App\Models;

use App\Support\DataDatabase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Authority extends Model
{
    protected $table = 'authorities';

    protected $guarded = [];

    public function getConnectionName(): ?string
    {
        return DataDatabase::name();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tracking' => 'boolean',
            'start_date' => 'date',
            'end_date' => 'date',
            'boundary_payload' => 'array',
            'boundary_updated_at' => 'datetime',
            'boundary_modified_at' => 'datetime',
        ];
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'authority_locations');
    }

    public function amalgamatedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'amalgamated');
    }

    public function predecessors(): HasMany
    {
        return $this->hasMany(self::class, 'amalgamated');
    }

    public function scopeCurrent($query)
    {
        return $query->whereNull('amalgamated');
    }
}
