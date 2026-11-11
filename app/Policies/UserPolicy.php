<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Superadmin', 'Admin']);
    }

    public function view(User $user, User $model): bool
    {
        return $user->hasAnyRole(['Superadmin', 'Admin']) || $user->id === $model->id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Superadmin', 'Admin']);
    }

    public function update(User $user, User $model): bool
    {
        return $user->hasAnyRole(['Superadmin', 'Admin']) || $user->id === $model->id;
    }

    public function delete(User $user, User $model): bool
    {
        return $user->hasAnyRole(['Superadmin', 'Admin']);
    }

    public function restore(User $user, User $model): bool
    {
        return $user->hasAnyRole(['Superadmin', 'Admin']);
    }

    public function forceDelete(User $user, User $model): bool
    {
        return $user->hasAnyRole(['Superadmin', 'Admin']);
    }
}
