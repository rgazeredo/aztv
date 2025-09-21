<?php

namespace App\Policies;

use App\Models\AlertRule;
use App\Models\User;

class AlertRulePolicy
{
    /**
     * Determine whether the user can view any alert rules.
     */
    public function viewAny(User $user): bool
    {
        // Only admins and tenant users can view alert rules
        return $user->isAdmin() || $user->isClient();
    }

    /**
     * Determine whether the user can view the alert rule.
     */
    public function view(User $user, AlertRule $alertRule): bool
    {
        // Admins can view all alert rules
        if ($user->isAdmin()) {
            return true;
        }

        // Tenant users can only view alert rules from their own tenant
        return $user->tenant_id === $alertRule->tenant_id;
    }

    /**
     * Determine whether the user can create alert rules.
     */
    public function create(User $user): bool
    {
        // Only admins and tenant users can create alert rules
        return $user->isAdmin() || $user->isClient();
    }

    /**
     * Determine whether the user can update the alert rule.
     */
    public function update(User $user, AlertRule $alertRule): bool
    {
        // Admins can update all alert rules
        if ($user->isAdmin()) {
            return true;
        }

        // Tenant users can only update alert rules from their own tenant
        return $user->tenant_id === $alertRule->tenant_id;
    }

    /**
     * Determine whether the user can delete the alert rule.
     */
    public function delete(User $user, AlertRule $alertRule): bool
    {
        // Admins can delete all alert rules
        if ($user->isAdmin()) {
            return true;
        }

        // Tenant users can only delete alert rules from their own tenant
        return $user->tenant_id === $alertRule->tenant_id;
    }

    /**
     * Determine whether the user can restore the alert rule.
     */
    public function restore(User $user, AlertRule $alertRule): bool
    {
        // Only admins can restore alert rules
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can permanently delete the alert rule.
     */
    public function forceDelete(User $user, AlertRule $alertRule): bool
    {
        // Only admins can permanently delete alert rules
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can test the alert rule.
     */
    public function test(User $user, AlertRule $alertRule): bool
    {
        // Admins can test all alert rules
        if ($user->isAdmin()) {
            return true;
        }

        // Tenant users can only test alert rules from their own tenant
        return $user->tenant_id === $alertRule->tenant_id;
    }
}