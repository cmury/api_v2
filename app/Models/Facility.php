<?php

namespace App\Models;

use App\Support\DataDatabase;
use Illuminate\Database\Eloquent\Model;

/**
 * Point facilities (transport, education, …) in the warehouse `facilities` table.
 */
class Facility extends Model
{
    protected $table = 'facilities';

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
