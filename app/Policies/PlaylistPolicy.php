<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Playlist;

class PlaylistPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isClient();
    }

    public function view(User $user, Playlist $playlist): bool
    {
        return $user->isAdmin() ||
               ($user->isClient() && $user->tenant_id === $playlist->tenant_id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isClient();
    }

    public function update(User $user, Playlist $playlist): bool
    {
        return $user->isAdmin() ||
               ($user->isClient() && $user->tenant_id === $playlist->tenant_id);
    }

    public function delete(User $user, Playlist $playlist): bool
    {
        if ($playlist->is_default) {
            return false;
        }

        if ($playlist->players()->count() > 0) {
            return false;
        }

        return $user->isAdmin() ||
               ($user->isClient() && $user->tenant_id === $playlist->tenant_id);
    }

    public function addMedia(User $user, Playlist $playlist): bool
    {
        return $user->isAdmin() ||
               ($user->isClient() && $user->tenant_id === $playlist->tenant_id);
    }

    public function removeMedia(User $user, Playlist $playlist): bool
    {
        return $user->isAdmin() ||
               ($user->isClient() && $user->tenant_id === $playlist->tenant_id);
    }

    public function reorderItems(User $user, Playlist $playlist): bool
    {
        return $user->isAdmin() ||
               ($user->isClient() && $user->tenant_id === $playlist->tenant_id);
    }

    public function duplicate(User $user, Playlist $playlist): bool
    {
        return $user->isAdmin() ||
               ($user->isClient() && $user->tenant_id === $playlist->tenant_id);
    }

    public function assignToPlayers(User $user, Playlist $playlist): bool
    {
        return $user->isAdmin() ||
               ($user->isClient() && $user->tenant_id === $playlist->tenant_id);
    }

    public function markAsDefault(User $user, Playlist $playlist): bool
    {
        return $user->isAdmin() ||
               ($user->isClient() && $user->tenant_id === $playlist->tenant_id);
    }

    public function bulkActions(User $user): bool
    {
        return $user->isAdmin() || $user->isClient();
    }
}