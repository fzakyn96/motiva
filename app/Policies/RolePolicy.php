<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Auth\Access\Response;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Superadmin', 'Admin']);
    }

    public function view(User $user, Role $role): bool
    {
        return $user->hasAnyRole(['Superadmin', 'Admin']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Superadmin', 'Admin']);
    }

    public function update(User $user, Role $role): bool
    {
        return $user->hasAnyRole(['Superadmin', 'Admin']);
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->hasAnyRole(['Superadmin', 'Admin']);
    }
}
