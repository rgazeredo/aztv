<?php

namespace App\Policies;

use App\Models\ActivityLog;
use App\Models\User;

class ActivityLogPolicy
{
    public function viewAny(User $user): bool
    {
        // Only admins and tenant users can view activity logs
        return $user->isAdmin() || $user->isClient();
    }

    public function view(User $user, ActivityLog $activityLog): bool
    {
        // Admins can view all logs
        if ($user->isAdmin()) {
            return true;
        }

        // Tenant users can only view logs from their own tenant
        return $user->tenant_id === $activityLog->tenant_id;
    }

    public function create(User $user): bool
    {
        // Activity logs are created automatically, no manual creation
        return false;
    }

    public function update(User $user, ActivityLog $activityLog): bool
    {
        // Activity logs are immutable
        return false;
    }

    public function delete(User $user, ActivityLog $activityLog): bool
    {
        // Only admins can delete activity logs
        return $user->isAdmin();
    }

    public function restore(User $user, ActivityLog $activityLog): bool
    {
        // Only admins can restore activity logs
        return $user->isAdmin();
    }

    public function forceDelete(User $user, ActivityLog $activityLog): bool
    {
        // Only admins can permanently delete activity logs
        return $user->isAdmin();
    }
}