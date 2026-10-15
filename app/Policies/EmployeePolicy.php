<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_employee') || $user->hasAnyRole(['Admin', 'Superadmin', 'HR', 'Manager']);
    }

    public function view(User $user, Employee $employee): bool
    {
        return $user->can('view_employee') || $user->hasAnyRole(['Admin', 'Superadmin', 'HR', 'Manager']);
    }

    public function create(User $user): bool
    {
        return $user->can('create_employee') || $user->hasAnyRole(['Admin', 'Superadmin', 'HR']);
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->can('update_employee') || $user->hasAnyRole(['Admin', 'Superadmin', 'HR']);
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $user->can('delete_employee') || $user->hasAnyRole(['Admin', 'Superadmin']);
    }
}
