<?php

namespace App\Policies;

use App\Models\Contact;
use App\Models\User;

final class ContactPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('contacts.view');
    }

    public function view(User $user, Contact $contact): bool
    {
        if (! $user->can('contacts.view')) {
            return false;
        }

        // Branch boundary check (BOLA prevention)
        if ($user->branch_id && $contact->branch_id && $user->branch_id !== $contact->branch_id) {
            return false;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('contacts.create');
    }

    public function update(User $user, Contact $contact): bool
    {
        if (! $user->can('contacts.update')) {
            return false;
        }

        if ($user->branch_id && $contact->branch_id && $user->branch_id !== $contact->branch_id) {
            return false;
        }

        return true;
    }

    public function delete(User $user, Contact $contact): bool
    {
        if (! $user->can('contacts.delete')) {
            return false;
        }

        if ($user->branch_id && $contact->branch_id && $user->branch_id !== $contact->branch_id) {
            return false;
        }

        return true;
    }
}
