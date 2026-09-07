<?php

namespace App\Services\Authorization;

use App\Models\User;

class AuthorizationService
{
    public function can(User $user, string $permission): bool
    {
        return $this->permissionsFor($user)
            ->contains(
                fn ($item) => $item->code === $permission
            );
    }

    public function hasRole(User $user, string $role): bool
    {
        return $this->rolesFor($user)
            ->contains(
                fn ($item) => $item->code === $role
            );
    }

    public function rolesFor(User $user)
    {
        $user->loadMissing('roles');

        return $user->roles
            ->filter(
                fn ($role) => $role->is_active
            )
            ->values();
    }

    public function permissionsFor(User $user)
    {
        $user->loadMissing('roles.permissions');

        return $user->roles
            ->filter(
                fn ($role) => $role->is_active
            )
            ->flatMap(
                fn ($role) => $role->permissions
            )
            ->unique('id')
            ->values();
    }
}