<?php

namespace App\Policies;

use App\Models\SppdCostLog;
use App\Models\User;

class SppdCostLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Superadmin', 'Admin', 'Manager']);
    }

    public function view(User $user, SppdCostLog $sppdCostLog): bool
    {
        return $user->hasAnyRole(['Superadmin', 'Admin', 'Manager']);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, SppdCostLog $sppdCostLog): bool
    {
        return false;
    }

    public function delete(User $user, SppdCostLog $sppdCostLog): bool
    {
        return false;
    }
}

