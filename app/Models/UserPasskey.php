<?php

namespace App\Models;

use App\Support\DataDatabase;
use Laravel\Passkeys\Passkey as BasePasskey;

class UserPasskey extends BasePasskey
{
    protected $table = 'users_passkeys';

    public function getConnectionName(): ?string
    {
        return DataDatabase::name();
    }
}
