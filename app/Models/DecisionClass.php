<?php

namespace App\Models;

use App\Support\DataDatabase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DecisionClass extends Model
{
    protected $table = 'decision_classes';

    protected $guarded = [];

    public function getConnectionName(): ?string
    {
        return DataDatabase::name();
    }

    public function types(): HasMany
    {
        return $this->hasMany(DecisionType::class);
    }
}
