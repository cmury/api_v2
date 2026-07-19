<?php

namespace App\Models;

use App\Support\DataDatabase;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'surname',
        'email',
        'mobile',
        'password',
        'company',
        'is_verified',
        'first_token',
        'verification_code',
        'password_reset_token',
        'stripe_id',
        'card_brand',
        'card_last_four',
        'trial_ends_at',
    ];

    protected $hidden = [
        'password',
        'first_token',
        'verification_code',
        'password_reset_token',
    ];

    public function getConnectionName(): ?string
    {
        return DataDatabase::name();
    }

    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
            'trial_ends_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function preferences(): HasOne
    {
        return $this->hasOne(UserPreference::class);
    }
}
