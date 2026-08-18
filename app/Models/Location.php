<?php

namespace App\Models;

use App\Support\DataDatabase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Location extends Model
{
    protected $table = 'locations';

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
            'location_raw' => 'array',
            'parcel' => 'array',
            'payload' => 'array',
        ];
    }

    public function applications(): BelongsToMany
    {
        return $this->belongsToMany(Application::class, 'application_locations');
    }
}
