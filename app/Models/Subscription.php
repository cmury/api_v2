<?php

namespace App\Models;

use App\Support\DataDatabase;
use Laravel\Cashier\Subscription as CashierSubscription;

class Subscription extends CashierSubscription
{
    protected $table = 'users_subscriptions';

    public function getConnectionName(): ?string
    {
        return DataDatabase::name();
    }
}
