<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view any user');
    }

    public function view(User $user, User $model): bool
    {
        return $user->hasPermissionTo('view user') || $user->id === $model->id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create user');
    }

    public function update(User $user, User $model): bool
    {
        return $user->hasPermissionTo('update user');
    }

    public function delete(User $user, User $model): bool
    {
        return $user->hasPermissionTo('delete user');
    }

    public function restore(User $user, User $model): bool
    {
        return $user->hasPermissionTo('restore user');
    }

    public function forceDelete(User $user, User $model): bool
    {
        return $user->hasPermissionTo('force delete user');
    }
}
