<?php

namespace App\Models;

use App\Support\DataDatabase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DevelopmentType extends Model
{
    protected $table = 'development_types';

    protected $guarded = [];

    public function getConnectionName(): ?string
    {
        return DataDatabase::name();
    }

    public function developmentClass(): BelongsTo
    {
        return $this->belongsTo(DevelopmentClass::class);
    }

    public function applications(): BelongsToMany
    {
        return $this->belongsToMany(Application::class, 'application_development_types');
    }
}
