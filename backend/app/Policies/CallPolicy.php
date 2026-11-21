<?php

namespace App\Policies;

use App\Models\Call;
use App\Models\User;

final class CallPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('calls.view');
    }

    public function view(User $user, Call $call): bool
    {
        if (! $user->can('calls.view')) {
            return false;
        }

        if ($user->branch_id && $call->branch_id && $user->branch_id !== $call->branch_id) {
            return false;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('calls.make');
    }

    public function control(User $user, Call $call): bool
    {
        if (! $user->can('calls.make')) {
            return false;
        }

        if ($user->branch_id && $call->branch_id && $user->branch_id !== $call->branch_id) {
            return false;
        }

        return true;
    }

    public function listenRecording(User $user, Call $call): bool
    {
        if (! $user->can('calls.recording.listen')) {
            return false;
        }

        if ($user->branch_id && $call->branch_id && $user->branch_id !== $call->branch_id) {
            return false;
        }

        return true;
    }
}
