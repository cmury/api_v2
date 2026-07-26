<?php

namespace App\Models;

use App\Support\DataDatabase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ApplicationType extends Model
{
    protected $table = 'application_types';

    protected $guarded = [];

    public function getConnectionName(): ?string
    {
        return DataDatabase::name();
    }

    public function applicationClass(): BelongsTo
    {
        return $this->belongsTo(ApplicationClass::class);
    }

    public function applications(): BelongsToMany
    {
        return $this->belongsToMany(Application::class, 'application_application_types');
    }
}
