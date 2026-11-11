<?php

namespace App\Policies;

use App\Models\SppdRateStandard;
use App\Models\User;

class SppdRateStandardPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Superadmin', 'Admin', 'HR'])
            || $user->can('view_any_sppd::rate::standard');
    }

    public function view(User $user, SppdRateStandard $rateStandard): bool
    {
        return $user->hasAnyRole(['Superadmin', 'Admin', 'HR'])
            || $user->can('view_sppd::rate::standard');
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Superadmin', 'Admin', 'HR'])
            || $user->can('create_sppd::rate::standard');
    }

    public function update(User $user, SppdRateStandard $rateStandard): bool
    {
        return $user->hasAnyRole(['Superadmin', 'Admin', 'HR'])
            || $user->can('update_sppd::rate::standard');
    }

    public function delete(User $user, SppdRateStandard $rateStandard): bool
    {
        return $user->hasAnyRole(['Superadmin', 'Admin', 'HR'])
            || $user->can('delete_sppd::rate::standard');
    }
}

