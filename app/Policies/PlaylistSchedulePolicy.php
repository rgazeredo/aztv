<?php

namespace App\Policies;

use App\Models\PlaylistSchedule;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PlaylistSchedulePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isClient() || $user->isAdmin();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PlaylistSchedule $playlistSchedule): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isClient() && $user->tenant_id === $playlistSchedule->tenant_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isClient() || $user->isAdmin();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PlaylistSchedule $playlistSchedule): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isClient() && $user->tenant_id === $playlistSchedule->tenant_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PlaylistSchedule $playlistSchedule): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isClient() && $user->tenant_id === $playlistSchedule->tenant_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, PlaylistSchedule $playlistSchedule): bool
    {
        return $this->delete($user, $playlistSchedule);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, PlaylistSchedule $playlistSchedule): bool
    {
        return $this->delete($user, $playlistSchedule);
    }
}
