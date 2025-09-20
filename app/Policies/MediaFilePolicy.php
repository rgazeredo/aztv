<?php

namespace App\Policies;

use App\Models\User;
use App\Models\MediaFile;

class MediaFilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isClient();
    }

    public function view(User $user, MediaFile $mediaFile): bool
    {
        return $user->isAdmin() ||
               ($user->isClient() && $user->tenant_id === $mediaFile->tenant_id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isClient();
    }

    public function update(User $user, MediaFile $mediaFile): bool
    {
        return $user->isAdmin() ||
               ($user->isClient() && $user->tenant_id === $mediaFile->tenant_id);
    }

    public function delete(User $user, MediaFile $mediaFile): bool
    {
        if ($mediaFile->playlists()->count() > 0) {
            return false;
        }

        return $user->isAdmin() ||
               ($user->isClient() && $user->tenant_id === $mediaFile->tenant_id);
    }

    public function download(User $user, MediaFile $mediaFile): bool
    {
        return $user->isAdmin() ||
               ($user->isClient() && $user->tenant_id === $mediaFile->tenant_id);
    }

    public function bulkActions(User $user): bool
    {
        return $user->isAdmin() || $user->isClient();
    }

    public function manageFolders(User $user): bool
    {
        return $user->isAdmin() || $user->isClient();
    }
}