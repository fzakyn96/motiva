<?php

namespace App\Policies;

use App\Models\User;

class AuthorizationTestPolicy
{
    public function allow(User $user): bool
    {
        return true;
    }

    public function deny(User $user): bool
    {
        return false;
    }
}