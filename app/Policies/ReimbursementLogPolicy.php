<?php

namespace App\Policies;

use App\Models\ReimbursementLog;
use App\Models\User;

class ReimbursementLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Superadmin', 'Admin', 'Manager']);
    }

    public function view(User $user, ReimbursementLog $reimbursementLog): bool
    {
        return $user->hasAnyRole(['Superadmin', 'Admin', 'Manager']);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ReimbursementLog $reimbursementLog): bool
    {
        return false;
    }

    public function delete(User $user, ReimbursementLog $reimbursementLog): bool
    {
        return false;
    }
}

