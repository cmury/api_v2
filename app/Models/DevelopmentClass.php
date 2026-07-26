<?php

namespace App\Models;

use App\Support\DataDatabase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DevelopmentClass extends Model
{
    protected $table = 'development_classes';

    protected $guarded = [];

    public function getConnectionName(): ?string
    {
        return DataDatabase::name();
    }

    public function types(): HasMany
    {
        return $this->hasMany(DevelopmentType::class);
    }
}
