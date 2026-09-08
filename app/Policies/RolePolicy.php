<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\AuthorizationService;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return app(AuthorizationService::class)
            ->can($user, 'role.view');
    }

    public function view(User $user, Role $role): bool
    {
        return app(AuthorizationService::class)
            ->can($user, 'role.view');
    }

    public function create(User $user): bool
    {
        return app(AuthorizationService::class)
            ->can($user, 'role.create');
    }

    public function update(User $user, Role $role): bool
    {
        return app(AuthorizationService::class)
            ->can($user, 'role.update');
    }

    public function delete(User $user, Role $role): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
