<?php

namespace App\Policies;

use App\Models\SppdCost;
use App\Models\User;

class SppdCostPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Superadmin', 'Admin', 'HR'])
            || $user->can('view_any_sppd::cost');
    }

    public function view(User $user, SppdCost $sppdCost): bool
    {
        return $user->hasAnyRole(['Superadmin', 'Admin', 'HR', 'Manager', 'Supervisor'])
            || $user->can('view_sppd::cost');
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Superadmin', 'Admin', 'HR'])
            || $user->can('create_sppd::cost');
    }

    public function update(User $user, SppdCost $sppdCost): bool
    {
        return $user->hasAnyRole(['Superadmin', 'Admin', 'HR'])
            || $user->can('update_sppd::cost');
    }

    public function delete(User $user, SppdCost $sppdCost): bool
    {
        return $user->hasAnyRole(['Superadmin', 'Admin', 'HR'])
            || $user->can('delete_sppd::cost');
    }
}

