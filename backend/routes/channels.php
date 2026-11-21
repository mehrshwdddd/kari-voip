<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('user.{id}', function (User $user, int $id): bool {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('branch.{branchId}', function (User $user, string $branchId): bool {
    if ($user->hasRole('owner')) {
        return true;
    }

    if ($branchId === 'central') {
        return $user->branch_id === null;
    }

    return (int) $user->branch_id === (int) $branchId;
});
