<?php

namespace App\Policies;

use App\Models\MemoLog;
use App\Models\User;

class MemoLogPolicy
{
    /**
     * Log Memo hanya boleh diakses oleh Manajer dan Superadmin.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Superadmin', 'Admin', 'Manager']);
    }

    public function view(User $user, MemoLog $memoLog): bool
    {
        return $user->hasAnyRole(['Superadmin', 'Admin', 'Manager']);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, MemoLog $memoLog): bool
    {
        return false;
    }

    public function delete(User $user, MemoLog $memoLog): bool
    {
        return false;
    }
}

