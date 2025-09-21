<?php

namespace App\Policies;

use App\Models\PlayerLog;
use App\Models\User;

class PlayerLogPolicy
{
    public function viewAny(User $user): bool
    {
        // Only admins and tenant users can view player logs
        return $user->isAdmin() || $user->isClient();
    }

    public function view(User $user, PlayerLog $playerLog): bool
    {
        // Admins can view all logs
        if ($user->isAdmin()) {
            return true;
        }

        // Tenant users can only view logs from their own tenant
        return $user->tenant_id === $playerLog->tenant_id;
    }

    public function create(User $user): bool
    {
        // Player logs are created automatically via API, no manual creation through web interface
        return false;
    }

    public function update(User $user, PlayerLog $playerLog): bool
    {
        // Player logs are immutable
        return false;
    }

    public function delete(User $user, PlayerLog $playerLog): bool
    {
        // Only admins can delete player logs
        return $user->isAdmin();
    }

    public function restore(User $user, PlayerLog $playerLog): bool
    {
        // Only admins can restore player logs
        return $user->isAdmin();
    }

    public function forceDelete(User $user, PlayerLog $playerLog): bool
    {
        // Only admins can permanently delete player logs
        return $user->isAdmin();
    }

    public function export(User $user): bool
    {
        // Both admins and tenant users can export logs from their scope
        return $user->isAdmin() || $user->isClient();
    }
}