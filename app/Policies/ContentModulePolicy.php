<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ContentModule;

class ContentModulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isClient();
    }

    public function view(User $user, ContentModule $contentModule): bool
    {
        return $user->isAdmin() ||
               ($user->isClient() && $user->tenant_id === $contentModule->tenant_id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isClient();
    }

    public function update(User $user, ContentModule $contentModule): bool
    {
        return $user->isAdmin() ||
               ($user->isClient() && $user->tenant_id === $contentModule->tenant_id);
    }

    public function delete(User $user, ContentModule $contentModule): bool
    {
        return $user->isAdmin() ||
               ($user->isClient() && $user->tenant_id === $contentModule->tenant_id);
    }

    public function toggle(User $user, ContentModule $contentModule): bool
    {
        return $user->isAdmin() ||
               ($user->isClient() && $user->tenant_id === $contentModule->tenant_id);
    }

    public function testConnection(User $user, ContentModule $contentModule): bool
    {
        return $user->isAdmin() ||
               ($user->isClient() && $user->tenant_id === $contentModule->tenant_id);
    }

    public function preview(User $user, ContentModule $contentModule): bool
    {
        return $user->isAdmin() ||
               ($user->isClient() && $user->tenant_id === $contentModule->tenant_id);
    }

    public function bulkActions(User $user): bool
    {
        return $user->isAdmin() || $user->isClient();
    }
}