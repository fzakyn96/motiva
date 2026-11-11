<?php

namespace App\Policies;

use App\Models\Sppd;
use App\Models\User;

class SppdPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->hasAnyRole(['Manager', 'Supervisor']) && ! $user->hasAnyRole(['Admin', 'Superadmin', 'HR'])) {
            return false;
        }

        return $user->hasAnyRole(['Admin', 'Superadmin', 'HR', 'Staff'])
            || $user->can('view_any_sppd');
    }

    public function view(User $user, Sppd $sppd): bool
    {
        if ($user->hasAnyRole(['Admin', 'Superadmin', 'HR'])) {
            return true;
        }

        $employee = $user->employee;
        if (! $employee) {
            return false;
        }

        // Managers and Supervisors can view SPPD in their department
        if ($user->hasAnyRole(['Manager', 'Supervisor'])) {
            return $sppd->employee?->department_id === $employee->department_id;
        }

        // Staff can only view their own SPPD
        return (int) $sppd->employee_id === (int) $employee->id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Superadmin', 'Manager', 'Supervisor', 'HR', 'Staff'])
            || $user->can('create_sppd');
    }

    public function update(User $user, Sppd $sppd): bool
    {
        if ($user->hasAnyRole(['Admin', 'Superadmin', 'HR'])) {
            return true;
        }

        $employee = $user->employee;
        if (! $employee) {
            return false;
        }

        // Staff assigned to this SPPD can edit/process their own draft SPPD
        if ((int) $sppd->employee_id === (int) $employee->id) {
            return in_array($sppd->status, ['draft', 'pending']);
        }

        // Supervisor, Manager in the same unit can update
        if ($user->hasAnyRole(['Manager', 'Supervisor'])) {
            return $sppd->employee?->department_id === $employee->department_id;
        }

        return false;
    }

    public function delete(User $user, Sppd $sppd): bool
    {
        return $user->hasAnyRole(['Admin', 'Superadmin']);
    }
}
