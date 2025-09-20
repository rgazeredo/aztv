<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Tenant;

class TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Tenant $tenant): bool
    {
        return $user->isAdmin() || $user->tenant_id === $tenant->id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Tenant $tenant): bool
    {
        return $user->isAdmin();
    }

    public function impersonate(User $user, Tenant $tenant): bool
    {
        return $user->isAdmin();
    }

    public function managePlayers(User $user, Tenant $tenant): bool
    {
        return $user->isAdmin() || $user->tenant_id === $tenant->id;
    }

    public function manageMedia(User $user, Tenant $tenant): bool
    {
        return $user->isAdmin() || $user->tenant_id === $tenant->id;
    }

    public function managePlaylists(User $user, Tenant $tenant): bool
    {
        return $user->isAdmin() || $user->tenant_id === $tenant->id;
    }

    public function manageContentModules(User $user, Tenant $tenant): bool
    {
        return $user->isAdmin() || $user->tenant_id === $tenant->id;
    }
}