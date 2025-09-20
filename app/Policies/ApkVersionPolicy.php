<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ApkVersion;

class ApkVersionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, ApkVersion $apkVersion): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, ApkVersion $apkVersion): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, ApkVersion $apkVersion): bool
    {
        if ($apkVersion->is_active) {
            return false;
        }

        return $user->isAdmin();
    }

    public function activate(User $user, ApkVersion $apkVersion): bool
    {
        return $user->isAdmin();
    }

    public function deactivate(User $user, ApkVersion $apkVersion): bool
    {
        return $user->isAdmin();
    }

    public function download(User $user, ApkVersion $apkVersion): bool
    {
        return $user->isAdmin() || $user->isClient();
    }

    public function generateQrCode(User $user, ApkVersion $apkVersion): bool
    {
        return $user->isAdmin();
    }

    public function bulkActions(User $user): bool
    {
        return $user->isAdmin();
    }

    public function forceUpdate(User $user, ApkVersion $apkVersion): bool
    {
        return $user->isAdmin();
    }

    public function viewAnalytics(User $user): bool
    {
        return $user->isAdmin();
    }
}