<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Player;

class PlayerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isClient();
    }

    public function view(User $user, Player $player): bool
    {
        return $user->isAdmin() ||
               ($user->isClient() && $user->tenant_id === $player->tenant_id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isClient();
    }

    public function update(User $user, Player $player): bool
    {
        return $user->isAdmin() ||
               ($user->isClient() && $user->tenant_id === $player->tenant_id);
    }

    public function delete(User $user, Player $player): bool
    {
        return $user->isAdmin() ||
               ($user->isClient() && $user->tenant_id === $player->tenant_id);
    }

    public function sendCommands(User $user, Player $player): bool
    {
        return $user->isAdmin() ||
               ($user->isClient() && $user->tenant_id === $player->tenant_id);
    }

    public function viewLogs(User $user, Player $player): bool
    {
        return $user->isAdmin() ||
               ($user->isClient() && $user->tenant_id === $player->tenant_id);
    }

    public function regenerateToken(User $user, Player $player): bool
    {
        return $user->isAdmin() ||
               ($user->isClient() && $user->tenant_id === $player->tenant_id);
    }
}