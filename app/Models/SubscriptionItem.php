<?php

namespace App\Models;

use App\Support\DataDatabase;
use Laravel\Cashier\SubscriptionItem as CashierSubscriptionItem;

class SubscriptionItem extends CashierSubscriptionItem
{
    protected $table = 'users_subscription_items';

    public function getConnectionName(): ?string
    {
        return DataDatabase::name();
    }
}
