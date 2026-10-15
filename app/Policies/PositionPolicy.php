<?php

namespace App\Policies;

use App\Models\Position;
use App\Models\User;

class PositionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Superadmin', 'HR', 'Manager', 'Supervisor', 'Staff']);
    }

    public function view(User $user, Position $position): bool
    {
        return $user->hasAnyRole(['Admin', 'Superadmin', 'HR', 'Manager', 'Supervisor', 'Staff']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Superadmin', 'HR']);
    }

    public function update(User $user, Position $position): bool
    {
        return $user->hasAnyRole(['Admin', 'Superadmin', 'HR']);
    }

    public function delete(User $user, Position $position): bool
    {
        return $user->hasAnyRole(['Admin', 'Superadmin', 'HR']);
    }
}
