<?php

namespace App\Models;

use App\Support\DataDatabase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Certifier extends Model
{
    protected $table = 'certifiers';

    protected $guarded = [];

    public const ENRICHMENT_PENDING = 'pending';

    public const ENRICHMENT_ENRICHED = 'enriched';

    public const ENRICHMENT_NOT_FOUND = 'not_found';

    public const ENRICHMENT_FAILED = 'failed';

    public const ENRICHMENT_STATUSES = [
        self::ENRICHMENT_PENDING,
        self::ENRICHMENT_ENRICHED,
        self::ENRICHMENT_NOT_FOUND,
        self::ENRICHMENT_FAILED,
    ];

    public const REGISTRATION_TYPES = [
        'individual',
        'partnership',
        'body_corporate',
    ];

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
            'classes' => 'array',
            'payload' => 'array',
            'registered_at' => 'date',
            'ceased_at' => 'date',
            'enriched_at' => 'datetime',
        ];
    }

    public function applicationCertifiers(): HasMany
    {
        return $this->hasMany(ApplicationCertifier::class);
    }

    public function applications(): BelongsToMany
    {
        return $this->belongsToMany(Application::class, 'application_certifiers')
            ->withPivot([
                'id', 'certifier_application_no', 'decision', 'is_primary', 'source',
            ])
            ->withTimestamps();
    }
}
