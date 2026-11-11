<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Illuminate\Auth\Access\Response;

class PermissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Superadmin', 'Admin']);
    }

    public function view(User $user, Permission $permission): bool
    {
        return $user->hasAnyRole(['Superadmin', 'Admin']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Superadmin', 'Admin']);
    }

    public function update(User $user, Permission $permission): bool
    {
        return $user->hasAnyRole(['Superadmin', 'Admin']);
    }

    public function delete(User $user, Permission $permission): bool
    {
        return $user->hasAnyRole(['Superadmin', 'Admin']);
    }
}
