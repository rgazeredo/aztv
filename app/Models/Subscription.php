<?php

namespace App\Models;

use Laravel\Cashier\Subscription as CashierSubscription;

class Subscription extends CashierSubscription
{
    /**
     * Get the tenant that owns the subscription.
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the user that owns the subscription.
     * Override to use tenant instead of user.
     */
    public function user()
    {
        return $this->tenant();
    }

    /**
     * Get the model related to the subscription.
     * Override to return tenant.
     */
    public function owner()
    {
        return $this->tenant();
    }
}
