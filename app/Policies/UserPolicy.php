<?php

namespace App\Policies;

use App\Models\User;
use App\Services\Authorization\AuthorizationService;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return app(AuthorizationService::class)
            ->can($user, 'user.view');
    }

    public function view(User $user, User $model): bool
    {
        return app(AuthorizationService::class)
            ->can($user, 'user.view');
    }

    public function create(User $user): bool
    {
        return app(AuthorizationService::class)
            ->can($user, 'user.create');
    }

    public function update(User $user, User $model): bool
    {
        return app(AuthorizationService::class)
            ->can($user, 'user.update');
    }

    public function delete(User $user, User $model): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
