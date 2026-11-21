<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;

final class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('branches.view');
    }

    public function view(User $user, Branch $branch): bool
    {
        if ($user->can('branches.view')) {
            return true;
        }

        return $user->branch_id === $branch->id;
    }

    public function create(User $user): bool
    {
        return $user->can('branches.create');
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->can('branches.update');
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $user->can('branches.delete');
    }
}
