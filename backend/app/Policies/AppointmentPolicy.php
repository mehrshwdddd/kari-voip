<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

final class AppointmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('appointments.view');
    }

    public function view(User $user, Appointment $appointment): bool
    {
        if (! $user->can('appointments.view')) {
            return false;
        }

        if ($user->branch_id && $appointment->branch_id && $user->branch_id !== $appointment->branch_id) {
            return false;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('appointments.manage');
    }

    public function update(User $user, Appointment $appointment): bool
    {
        if (! $user->can('appointments.manage')) {
            return false;
        }

        if ($user->branch_id && $appointment->branch_id && $user->branch_id !== $appointment->branch_id) {
            return false;
        }

        return true;
    }

    public function delete(User $user, Appointment $appointment): bool
    {
        return $this->update($user, $appointment);
    }
}
