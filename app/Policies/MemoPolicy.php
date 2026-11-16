<?php

namespace App\Policies;

use App\Models\Memo;
use App\Models\User;

class MemoPolicy
{
    /**
     * Determine whether the user can view any models.
     * Staff tidak boleh mendapatkan akses Memo.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Superadmin', 'HR']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Memo $memo): bool
    {
        if ($user->hasAnyRole(['Admin', 'Superadmin', 'HR'])) {
            return true;
        }

        // Manager dapat melihat memo untuk keperluan review/approval
        if ($user->hasRole('Manager') || $user->can('approve_memo')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     * Staff tidak diizinkan membuat Memo.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Superadmin', 'HR']);
    }

    /**
     * Determine whether the user can update the model.
     * Staff tidak diizinkan mengedit Memo.
     */
    public function update(User $user, Memo $memo): bool
    {
        if ($user->hasAnyRole(['Admin', 'Superadmin'])) {
            return true;
        }

        if ($user->hasRole('HR')) {
            return $memo->status === 'draft';
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     * HR dan Superadmin boleh menghapus Memo (termasuk yang sudah disetujui dengan alasan audit log).
     * Staff tidak diizinkan menghapus Memo.
     */
    public function delete(User $user, Memo $memo): bool
    {
        return $user->hasAnyRole(['Admin', 'Superadmin', 'HR']);
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
