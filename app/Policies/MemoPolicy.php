<?php

namespace App\Policies;

use App\Models\Memo;
use App\Models\User;

class MemoPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if ($user->hasAnyRole(['Manager', 'Supervisor']) && ! $user->hasAnyRole(['Admin', 'Superadmin', 'HR'])) {
            return false;
        }

        return $user->hasAnyRole(['Admin', 'Superadmin', 'HR', 'Staff'])
            || $user->can('view_any_memo');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Memo $memo): bool
    {
        if ($user->hasAnyRole(['Admin', 'Superadmin', 'HR'])) {
            return true;
        }

        $employee = $user->employee;
        if (! $employee) {
            return $user->can('view_memo');
        }

        // Managers, Supervisors, and HR can view memos in their department or memos they created
        if ($user->hasAnyRole(['Manager', 'Supervisor', 'HR'])) {
            return $memo->department_id === $employee->department_id 
                || (int) $memo->creator_employee_id === (int) $employee->id
                || $user->can('view_memo');
        }

        // Staff can view their own memos or memos where they are assigned as participant
        if ((int) $memo->creator_employee_id === (int) $employee->id) {
            return true;
        }

        $participants = $memo->participants ?? [];
        foreach ($participants as $p) {
            if ((int) ($p['employee_id'] ?? 0) === (int) $employee->id) {
                return true;
            }
        }

        return $user->can('view_memo');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Superadmin', 'Manager', 'Supervisor', 'HR', 'Staff'])
            || $user->can('create_memo');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Memo $memo): bool
    {
        if ($user->hasAnyRole(['Admin', 'Superadmin'])) {
            return true;
        }

        $employee = $user->employee;
        if (! $employee) {
            return $user->can('update_memo');
        }

        // Creator can update if still in draft
        if ((int) $memo->creator_employee_id === (int) $employee->id && $memo->status === 'draft') {
            return true;
        }

        return $user->can('update_memo');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Memo $memo): bool
    {
        if ($user->hasAnyRole(['Admin', 'Superadmin'])) {
            return true;
        }

        $employee = $user->employee;
        if (! $employee) {
            return $user->can('delete_memo');
        }

        return (int) $memo->creator_employee_id === (int) $employee->id && $memo->status === 'draft';
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Memo $memo): bool
    {
        return $user->hasAnyRole(['Admin', 'Superadmin']);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Memo $memo): bool
    {
        return $user->hasAnyRole(['Admin', 'Superadmin']);
    }
}

