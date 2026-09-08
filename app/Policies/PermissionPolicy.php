<?php

namespace App\Policies;

use App\Models\Permission;
use App\Models\User;
use App\Services\Authorization\AuthorizationService;

class PermissionPolicy
{
    public function viewAny(User $user): bool
    {
        return app(AuthorizationService::class)
            ->can($user, 'permission.view');
    }

    public function view(User $user, Permission $permission): bool
    {
        return app(AuthorizationService::class)
            ->can($user, 'permission.view');
    }

    public function create(User $user): bool
    {
        return app(AuthorizationService::class)
            ->can($user, 'permission.manage');
    }

    public function update(User $user, Permission $permission): bool
    {
        return app(AuthorizationService::class)
            ->can($user, 'permission.manage');
    }

    public function delete(User $user, Permission $permission): bool
    {
        return false;
    }

    public function restore(User $user, Permission $permission): bool
    {
        return false;
    }

    public function forceDelete(User $user, Permission $permission): bool
    {
        return false;
    }
}
