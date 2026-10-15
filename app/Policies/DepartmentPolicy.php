<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;

class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Superadmin', 'HR', 'Manager', 'Supervisor', 'Staff']);
    }

    public function view(User $user, Department $department): bool
    {
        return $user->hasAnyRole(['Admin', 'Superadmin', 'HR', 'Manager', 'Supervisor', 'Staff']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Superadmin', 'HR']);
    }

    public function update(User $user, Department $department): bool
    {
        return $user->hasAnyRole(['Admin', 'Superadmin', 'HR']);
    }

    public function delete(User $user, Department $department): bool
    {
        return $user->hasAnyRole(['Admin', 'Superadmin', 'HR']);
    }
}
