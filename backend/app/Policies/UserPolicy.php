<?php

namespace App\Policies;

use App\Models\User;

final class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    public function view(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return true;
        }

        return $user->can('users.view');
    }

    public function create(User $user): bool
    {
        return $user->can('users.create');
    }

    public function update(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return true;
        }

        return $user->can('users.update');
    }

    public function delete(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return false; // cannot delete own account
        }

        return $user->can('users.delete');
    }
}
