<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Illuminate\Auth\Access\Response;

class PermissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view any permission');
    }

    public function view(User $user, Permission $permission): bool
    {
        return $user->hasPermissionTo('view permission');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create permission');
    }

    public function update(User $user, Permission $permission): bool
    {
        return $user->hasPermissionTo('update permission');
    }

    public function delete(User $user, Permission $permission): bool
    {
        return $user->hasPermissionTo('delete permission');
    }
}
