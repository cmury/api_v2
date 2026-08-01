<?php

namespace App\Models;

use App\Support\DataDatabase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationContact extends Model
{
    protected $table = 'application_contacts';

    protected $guarded = [];

    public const ROLES = [
        'architect', 'planner', 'builder', 'applicant', 'developer', 'certifier', 'other',
    ];

    public const STATUSES = [
        'pending', 'published', 'rejected',
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
            'is_primary' => 'boolean',
            'payload' => 'array',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function contributedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contributed_by_user_id');
    }
}
