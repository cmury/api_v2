<?php

namespace App\Models;

use App\Support\DataDatabase;
use Illuminate\Database\Eloquent\Model;

class TransitStop extends Model
{
    protected $table = 'transit_stops';

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
            'payload' => 'array',
            'source_modified_at' => 'datetime',
        ];
    }
}
