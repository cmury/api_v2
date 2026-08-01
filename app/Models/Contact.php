<?php

namespace App\Models;

use App\Support\DataDatabase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    protected $table = 'contacts';

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
        ];
    }

    public function applicationContacts(): HasMany
    {
        return $this->hasMany(ApplicationContact::class);
    }

    public function applications(): BelongsToMany
    {
        return $this->belongsToMany(Application::class, 'application_contacts')
            ->withPivot([
                'id', 'role', 'is_primary', 'source', 'status',
                'contributed_by_user_id', 'email_override', 'phone_override', 'notes',
            ])
            ->withTimestamps();
    }
}
