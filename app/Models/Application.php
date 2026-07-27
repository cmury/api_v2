<?php

namespace App\Models;

use App\Support\DataDatabase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Application extends Model
{
    protected $table = 'applications';

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
            'estimated_cost' => 'float',
            'submitted' => 'date',
            'decision_date' => 'date',
            'record_extracted' => 'datetime',
            'authority_record_modified' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function authority(): BelongsTo
    {
        return $this->belongsTo(Authority::class);
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'application_locations');
    }

    public function legislations(): BelongsToMany
    {
        return $this->belongsToMany(Legislation::class, 'application_legislation');
    }

    public function applicationTypes(): BelongsToMany
    {
        return $this->belongsToMany(ApplicationType::class, 'application_application_types');
    }

    public function developmentTypes(): BelongsToMany
    {
        return $this->belongsToMany(DevelopmentType::class, 'application_development_types');
    }

    public function decisionTypes(): BelongsToMany
    {
        return $this->belongsToMany(DecisionType::class, 'application_decision_types');
    }
}
