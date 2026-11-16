<?php

namespace App\Policies;

use App\Models\SppdLog;
use App\Models\User;

class SppdLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Superadmin', 'Admin', 'Manager']);
    }

    public function view(User $user, SppdLog $sppdLog): bool
    {
        return $user->hasAnyRole(['Superadmin', 'Admin', 'Manager']);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, SppdLog $sppdLog): bool
    {
        return false;
    }

    public function delete(User $user, SppdLog $sppdLog): bool
    {
        return false;
    }
}

