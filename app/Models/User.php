<?php

namespace App\Models;

use App\Support\DataDatabase;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use Laravel\Cashier\Cashier;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\PasskeyAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use Billable, HasApiTokens, HasFactory, Notifiable, PasskeyAuthenticatable;

    protected $fillable = [
        'name',
        'surname',
        'email',
        'mobile',
        'password',
        'company',
        'contact_id',
        'is_verified',
        'stripe_id',
        'pm_type',
        'pm_last_four',
        'trial_ends_at',
    ];

    protected $hidden = [
        'password',
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

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function searches(): HasMany
    {
        return $this->hasMany(UserSearch::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(UserLog::class);
    }

    public function getPasskeyDisplayName(): string
    {
        $fullName = trim(implode(' ', array_filter([$this->name, $this->surname])));

        return $fullName !== '' ? $fullName : (string) ($this->email ?? $this->getAuthIdentifier());
    }

    public function stripeName(): ?string
    {
        $fullName = trim(implode(' ', array_filter([$this->name, $this->surname])));

        return $fullName !== '' ? $fullName : null;
    }

    public function stripePhone(): ?string
    {
        return $this->mobile;
    }

    /**
     * Prefer the app Subscription model (users_subscriptions on the data connection).
     *
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Cashier::$subscriptionModel, $this->getForeignKey())
            ->orderByDesc('created_at');
    }
}
