<?php

namespace App\Models;

use App\Support\DataDatabase;
use Illuminate\Database\Eloquent\Model;

class AuthorityStatistic extends Model
{
    protected $table = 'authorities_statistics';

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
            'value' => 'float',
            'year' => 'integer',
            'statistics_code' => 'integer',
            'payload' => 'array',
        ];
    }
}
